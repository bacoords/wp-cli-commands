# WP-CLI Commands

This repository contains a collection of WP-CLI commands that are useful for debugging and development.

## How to use

For now, you can drop any of these files into your `wp-content/mu-plugins` directory.

## Plugin Debugger

### Cycle

This command cycles through all active plugins, deactivating and reactivating them one at a time.

```bash
wp plugin-debug cycle
```

### Binary Search

This command uses a binary search approach to efficiently find problematic plugins by deactivating plugins in groups.

```bash
wp plugin-debug binary
```
