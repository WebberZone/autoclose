# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Auto-Close Comments, Pingbacks and Trackbacks** (plugin slug: `autoclose`) is a WordPress plugin (v3.1.0) that automatically closes comments, pingbacks, and trackbacks on posts after a configurable age, manages post revision limits, and can block self-pings. It uses a WP-Cron job (`acc_cron_hook`) for scheduled processing. Namespace: `WebberZone\AutoClose`. Requires WordPress 6.6+, PHP 7.4+. No Freemius.

Constants defined in `autoclose.php`: `ACC_PLUGIN_VERSION`, `ACC_PLUGIN_DIR`, `ACC_PLUGIN_URL`, `ACC_PLUGIN_FILE`.

Settings prefix/key: `acc` / `acc_settings` (wp_options). Access via `WebberZone\AutoClose\Util\Options::get_option($key)` inside the codebase, or the legacy procedural wrapper `acc_get_settings()` from `includes/backward-compatibility.php`.

## Commands

### PHP
```bash
composer phpcs          # Lint PHP (WordPress coding standards)
composer phpcbf         # Auto-fix PHP code style
composer phpstan        # Static analysis
composer phpcompat      # Check PHP 7.4-8.5 compatibility
composer test           # Run all checks (phpcs + phpcompat + phpstan)
composer build:vendor   # Install production deps only
```

### JavaScript/CSS
```bash
npm run build:assets    # Minify CSS/JS, generate RTL CSS (node build-assets.js)
npm run zip             # Create distribution zip (wp-scripts plugin-zip)
```

No Gutenberg blocks; no `npm run build` / `npm start` scripts.

## Architecture

### Entry Point
`autoclose.php` defines constants, loads `includes/class-autoloader.php` (a class-based autoloader — `Autoloader::register()`), loads `includes/backward-compatibility.php`, registers activation/deactivation hooks pointing to `Core\Activator` and `Core\Deactivator`, then calls `acc_init()` on `plugins_loaded` which instantiates `AutoClose::get_instance()` and calls `->run()`.

### Main class (`includes/class-autoclose.php`)
Singleton (`AutoClose::get_instance()`). Unlike the other WebberZone plugins, hooks are registered inside the constructor (not in a separate `init()` method called after instantiation). The constructor calls:
- `load_dependencies()` — instantiates `Admin\Settings`
- `set_locale()` — hooks `Util\L10n::load_plugin_textdomain` on `init`
- `define_admin_hooks()` — instantiates `Admin\Admin` and `Admin\Tools`; registers plugin row meta/action links and the tools admin menu page
- `define_feature_hooks()` — instantiates all feature classes and registers their hooks

`run()` is a no-op (hooks are already registered by the time it is called).

### Features (`includes/features/`)
Each feature class is instantiated once in `define_feature_hooks()`; hooks are registered there rather than in the feature class constructors.

- **`Comments`** — `process_comments()` runs on `acc_cron_hook`; closes comments on posts older than the configured age, per post type, with optional term exclusions.
- **`Revisions`** — `process_revisions()` runs on `acc_cron_hook`; deletes revisions beyond the configured limit. `revisions_to_keep()` hooks `wp_revisions_to_keep` to enforce per-post-type limits on new saves.
- **`Block_Pings`** — hooks `pre_ping` to prevent self-pings.
- **`Close_Date`** — handles closing based on a specific date rather than age.
- **`Reopen`** — auto-reopens comments/pings on post update when configured.
- **`Notifications`** — sends an email summary after the cron job completes; template at `includes/features/views/email-cron-summary.php`.

### Cron (`includes/util/class-cron.php`)
`Cron::enable_run($hour, $min, $recurrence)` schedules the `acc_cron_hook` WP-Cron event. Called from `Core\Activator` on activation and from Settings on save when the scheduler option changes.

### Admin (`includes/admin/`)
- **`Settings`** — Settings page under Settings menu (`acc_options_page`). Tabs: General (cron schedule, email notifications), Comments, Pingbacks/Trackbacks, Revisions.
- **`Tools`** — Adds a tools page for one-time manual runs.
- **`Metabox`** — Per-post override meta for keeping comments/pings open regardless of global settings.

### Options access
Feature classes use `WebberZone\AutoClose\Util\Options::get_option($key)` (the internal static class in `includes/util/class-options.php`). `Options_API` in `includes/class-options-api.php` is a richer OOP variant used by settings sanitization. Avoid the legacy `acc_get_settings()` procedural wrapper in new code.

### Backward compatibility
`includes/backward-compatibility.php` provides procedural wrappers (`acc_get_settings()`, `acc_close_comments()`, etc.) for third-party code targeting versions prior to 3.0.
