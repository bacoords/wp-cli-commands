<?php
/**
 * Plugin Name: Plugin Debugger Command
 * Description: WP-CLI command to systematically deactivate and reactivate plugins for debugging
 * Version: 1.0.0
 * Author: Brian Coords
 * Author URI: https://www.briancoords.com
 * License: GPL-2.0-or-later
 *
 * @package WordPress\PluginDebugging
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load WP-CLI command if WP-CLI is present.
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Cycles through active plugins, deactivating and reactivating them one at a time.
	 */
	class Plugin_Debugger_Command {

		/**
		 * Cycles through active plugins for debugging.
		 *
		 * ## DESCRIPTION
		 *
		 * Systematically deactivates and reactivates each active plugin,
		 * waiting for user confirmation between each step.
		 * Excludes must-use plugins and drop-ins from the cycle.
		 *
		 * ## EXAMPLES
		 *
		 *     wp plugin-debug cycle
		 *
		 * @when after_wp_load
		 */
		public function cycle() {
			// Get all active plugins.
			$active_plugins = get_option( 'active_plugins' );

			// Filter out mu-plugins and dropins.
			$filtered_plugins = array();
			foreach ( $active_plugins as $plugin ) {
				// Skip if plugin is in mu-plugins directory.
				if ( strpos( $plugin, 'mu-plugins/' ) !== false ) {
					continue;
				}

				// Skip WordPress dropins.
				$dropin        = basename( $plugin );
				$known_dropins = array(
					'advanced-cache.php',
					'db.php',
					'db-error.php',
					'install.php',
					'maintenance.php',
					'object-cache.php',
					'php-error.php',
					'fatal-error-handler.php',
					'sunrise.php',
				);
				if ( in_array( $dropin, $known_dropins, true ) ) {
					continue;
				}

				$filtered_plugins[] = $plugin;
			}

			if ( empty( $filtered_plugins ) ) {
				WP_CLI::error( 'No regular active plugins found (excluding mu-plugins and dropins).' );
				return;
			}

			// Clear the screen initially
			if ( defined( 'PHP_OS' ) && 'WINNT' === PHP_OS ) {
				system( 'cls' ); // Windows
			} else {
				system( 'clear' ); // Unix-like systems
			}

			// Make sure get_plugin_data is available
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			// Show initial screen with plugin list
			WP_CLI::line( '' );
			WP_CLI::line( '=== Plugin Debug Cycle ===' );
			WP_CLI::line( '' );
			WP_CLI::success( sprintf( 'Found %d active plugins (excluding mu-plugins and dropins):', count( $filtered_plugins ) ) );
			WP_CLI::line( '' );

			// Display initial list of all plugins
			foreach ( $filtered_plugins as $index => $plugin ) {
				$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
				$plugin_data = get_plugin_data( $plugin_file );
				$plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( dirname( $plugin ) ) . '/' . basename( $plugin );
				WP_CLI::line( sprintf( '%d. %s', $index + 1, $plugin_name ) );
			}

			WP_CLI::line( '' );
			WP_CLI::warning( 'This will systematically deactivate and reactivate each plugin.' );
			WP_CLI::line( '' );

			// Ask for confirmation to proceed
			$answer = '';
			while ( ! in_array( $answer, array( 'y', 'n' ), true ) ) {
				if ( function_exists( 'readline' ) ) {
					$answer = strtolower( trim( readline( 'Ready to begin? [y/n] ' ) ) );
				} else {
					WP_CLI::out( 'Ready to begin? [y/n] ' );
					$answer = strtolower( trim( fgets( STDIN ) ) );
				}
			}

			if ( 'n' === $answer ) {
				WP_CLI::line( '' );
				WP_CLI::success( 'Exiting plugin debug cycle.' );
				return;
			}

			// Track current plugin index.
			$current_index = 0;
			$total_plugins = count( $filtered_plugins );

			// Initialize previous message.
			$previous_message = '';

			foreach ( $filtered_plugins as $plugin ) {
				// Clear the terminal screen based on OS at the start of each iteration
				if ( defined( 'PHP_OS' ) && 'WINNT' === PHP_OS ) {
					system( 'cls' ); // Windows
				} else {
					system( 'clear' ); // Unix-like systems
				}

				WP_CLI::line( '=== Current Plugin Status ===' );

				// Display numbered list of all plugins.
				foreach ( $filtered_plugins as $index => $list_plugin ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $list_plugin;
					$plugin_data = get_plugin_data( $plugin_file );
					$plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( dirname( $list_plugin ) ) . '/' . basename( $list_plugin );

					if ( $index === $current_index ) {
						// Highlight currently deactivated plugin in red
						WP_CLI::line(
							WP_CLI::colorize(
								sprintf(
									'%%R%d. %s (currently deactivated)%%n',
									$index + 1,
									$plugin_name
								)
							)
						);
					} else {
						WP_CLI::line( sprintf( '%d. %s', $index + 1, $plugin_name ) );
					}
				}

				WP_CLI::line( '' );

				// Display previous plugin's reactivation message if exists.
				if ( ! empty( $previous_message ) ) {
					WP_CLI::success( $previous_message );
					WP_CLI::line( '' );
				}

				$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
				$plugin_data = get_plugin_data( $plugin_file );
				$plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin;
				WP_CLI::warning( sprintf( 'Deactivating plugin %d of %d: %s', $current_index + 1, $total_plugins, $plugin_name ) );

				// Deactivate the plugin.
				deactivate_plugins( $plugin );

				// Present options to the user.
				WP_CLI::line( '' );
				WP_CLI::line( WP_CLI::colorize( '%BOptions:%n' ) );
				WP_CLI::line( '  y - Reactivate plugin and continue to next one' );
				WP_CLI::line( '  n - Reactivate plugin and exit' );
				WP_CLI::line( '  Ctrl+C - Exit without reactivating (not recommended)' );
				WP_CLI::line( '' );

				// Custom confirmation logic that won't exit the script
				$answer = '';
				while ( ! in_array( $answer, array( 'y', 'n' ), true ) ) {
					// Using readline() for input or falling back to fgets()
					if ( function_exists( 'readline' ) ) {
						$answer = strtolower( trim( readline( 'Continue? [y/n] ' ) ) );
					} else {
						WP_CLI::out( 'Continue? [y/n] ' );
						$answer = strtolower( trim( fgets( STDIN ) ) );
					}
				}
				$continue = 'y' === $answer;

				// Always reactivate the current plugin.
				activate_plugin( $plugin );
				$previous_message = sprintf( 'Plugin %s has been reactivated', $plugin_name );

				if ( ! $continue ) {
					WP_CLI::line( '' );
					WP_CLI::success( $previous_message ); // Show final message before exiting
					WP_CLI::success( 'Exiting plugin debug cycle.' );
					return;
				}

				++$current_index;
			}

			// Show the final plugin's reactivation message
			if ( ! empty( $previous_message ) ) {
				WP_CLI::success( $previous_message );
			}
			WP_CLI::success( 'Plugin debug cycle completed!' );
		}

		/**
		 * Uses binary search to find problematic plugins.
		 *
		 * ## DESCRIPTION
		 *
		 * Systematically deactivates plugins in groups using binary search,
		 * making plugin conflict debugging more efficient.
		 *
		 * The command will:
		 * 1. Deactivate half of the active plugins
		 * 2. Ask if the issue is fixed
		 * 3. Based on response, focus on either the active or inactive half
		 * 4. Repeat until the problematic plugin is identified
		 *
		 * ## EXAMPLES
		 *
		 *     wp plugin-debug binary
		 *
		 * @when after_wp_load
		 */
		public function binary() {
			// Get filtered list of active plugins (excluding mu-plugins and dropins).
			$active_plugins = get_option( 'active_plugins' );

			// Filter out mu-plugins and dropins.
			$filtered_plugins = array();
			foreach ( $active_plugins as $plugin ) {
				// Skip if plugin is in mu-plugins directory.
				if ( strpos( $plugin, 'mu-plugins/' ) !== false ) {
					continue;
				}

				// Skip WordPress dropins.
				$dropin        = basename( $plugin );
				$known_dropins = array(
					'advanced-cache.php',
					'db.php',
					'db-error.php',
					'install.php',
					'maintenance.php',
					'object-cache.php',
					'php-error.php',
					'fatal-error-handler.php',
					'sunrise.php',
				);
				if ( in_array( $dropin, $known_dropins, true ) ) {
					continue;
				}

				$filtered_plugins[] = $plugin;
			}

			if ( empty( $filtered_plugins ) ) {
				WP_CLI::error( 'No regular active plugins found (excluding mu-plugins and dropins).' );
				return;
			}

			// Make sure get_plugin_data is available.
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			// Initialize search space.
			$search_space = $filtered_plugins;
			$step         = 1;

			// Show initial screen.
			$this->clear_screen();
			WP_CLI::line( '' );
			WP_CLI::line( '=== Plugin Debug Binary Search ===' );
			WP_CLI::line( '' );
			WP_CLI::success( sprintf( 'Found %d active plugins (excluding mu-plugins and dropins).', count( $filtered_plugins ) ) );
			WP_CLI::line( '' );
			WP_CLI::warning( 'This will use binary search to find problematic plugins.' );
			WP_CLI::line( '' );

			// Display initial list of all plugins.
			foreach ( $filtered_plugins as $index => $plugin ) {
				$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
				$plugin_data = get_plugin_data( $plugin_file );
				$plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( dirname( $plugin ) ) . '/' . basename( $plugin );
				WP_CLI::line( sprintf( '%d. %s', $index + 1, $plugin_name ) );
			}

			WP_CLI::line( '' );

			// Ask for confirmation to proceed.
			if ( ! $this->confirm( 'Ready to begin?' ) ) {
				WP_CLI::success( 'Exiting plugin debug binary search.' );
				return;
			}

			// Binary search loop.
			while ( count( $search_space ) > 1 ) {
				$this->clear_screen();
				WP_CLI::line( sprintf( '=== Step %d ===', $step ) );
				WP_CLI::line( '' );

				// Split plugins into two groups.
				$mid           = floor( count( $search_space ) / 2 );
				$test_plugins  = array_slice( $search_space, 0, $mid );
				$other_plugins = array_slice( $search_space, $mid );

				// Deactivate test group.
				foreach ( $test_plugins as $plugin ) {
					deactivate_plugins( $plugin );
				}

				// Display current status.
				WP_CLI::warning( sprintf( 'Deactivated %d plugins:', count( $test_plugins ) ) );
				foreach ( $test_plugins as $plugin ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
					$plugin_data = get_plugin_data( $plugin_file );
					$plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( dirname( $plugin ) ) . '/' . basename( $plugin );
					WP_CLI::line( sprintf( '- %s', $plugin_name ) );
				}

				WP_CLI::line( '' );
				WP_CLI::line( 'Remaining active plugins:' );
				foreach ( $other_plugins as $plugin ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
					$plugin_data = get_plugin_data( $plugin_file );
					$plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( dirname( $plugin ) ) . '/' . basename( $plugin );
					WP_CLI::line( sprintf( '- %s', $plugin_name ) );
				}

				WP_CLI::line( '' );

				// Ask if issue is fixed.
				$is_fixed = $this->confirm( 'Is the issue fixed with these plugins deactivated?' );

				// Reactivate test group.
				foreach ( $test_plugins as $plugin ) {
					activate_plugin( $plugin );
				}

				// Update search space based on response.
				if ( $is_fixed ) {
					// Problem is in the deactivated plugins.
					$search_space = $test_plugins;
					WP_CLI::success( 'Problem is in the deactivated group. Narrowing search.' );
				} else {
					// Problem is in the still-active plugins.
					$search_space = $other_plugins;
					WP_CLI::success( 'Problem is in the active group. Narrowing search.' );
				}

				WP_CLI::line( '' );
				if ( ! $this->confirm( 'Continue to next step?' ) ) {
					WP_CLI::success( 'Exiting plugin debug binary search.' );
					return;
				}

				++$step;
			}

			// Final plugin identified.
			$problem_plugin = reset( $search_space );
			$plugin_file    = WP_PLUGIN_DIR . '/' . $problem_plugin;
			$plugin_data    = get_plugin_data( $plugin_file );
			$plugin_name    = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( dirname( $problem_plugin ) ) . '/' . basename( $problem_plugin );

			$this->clear_screen();
			WP_CLI::success( sprintf( 'Identified problematic plugin: %s', $plugin_name ) );
		}

		/**
		 * Clears the terminal screen based on OS.
		 */
		private function clear_screen() {
			if ( defined( 'PHP_OS' ) && 'WINNT' === PHP_OS ) {
				system( 'cls' );
			} else {
				system( 'clear' );
			}
		}

		/**
		 * Asks for user confirmation.
		 *
		 * @param string $question The question to ask.
		 * @return bool True if confirmed, false otherwise.
		 */
		private function confirm( $question ) {
			$answer = '';
			while ( ! in_array( $answer, array( 'y', 'n' ), true ) ) {
				if ( function_exists( 'readline' ) ) {
					$answer = strtolower( trim( readline( "$question [y/n] " ) ) );
				} else {
					WP_CLI::out( "$question [y/n] " );
					$answer = strtolower( trim( fgets( STDIN ) ) );
				}
			}
			return 'y' === $answer;
		}
	}

	// Register the command.
	WP_CLI::add_command( 'plugin-debug', 'Plugin_Debugger_Command' );
}
