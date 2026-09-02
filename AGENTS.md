# AGENTS.md

Guidance for AI coding agents working in this repository.

## Response Rules

- Return only the changed function or section, not the full file
- No explanation unless asked
- No suggestions outside the scope of what was asked
- Skip preamble and trailing summaries

## Links

- GitHub: <https://github.com/WebberZone/autoclose>
- WordPress.org: <https://wordpress.org/plugins/autoclose/>
- Documentation: <https://webberzone.com/support/product/autoclose/>
- webberzone.com: <https://webberzone.com/plugins/autoclose/>

## Plugin Overview

**Auto-Close Comments, Pingbacks and Trackbacks** (slug: `autoclose`) is a WordPress plugin (v3.1.2) that closes comments/pingbacks/trackbacks after a configurable age, manages revision limits, and can block self-pings, via WP-Cron (`acc_cron_hook`). Namespace: `WebberZone\AutoClose`. Requires WordPress 6.6+, PHP 7.4+. No Freemius.

Constants defined in `autoclose.php`: `ACC_PLUGIN_VERSION`, `ACC_PLUGIN_DIR`, `ACC_PLUGIN_URL`, `ACC_PLUGIN_FILE`.

Settings prefix/key: `acc` / `acc_settings` (wp_options). Access via `WebberZone\AutoClose\Options_API::get_option($key)`, or the legacy procedural wrapper `acc_get_settings()` (`includes/backward-compatibility.php`).

## Commands

### PHP

```bash
composer phpcs          # Lint PHP (WordPress coding standards)
composer phpcbf         # Auto-fix PHP code style
composer phpstan        # Static analysis
composer phpcompat      # Check PHP 7.4-8.6 compatibility
composer test           # Run all checks (phpcs + phpcompat + phpstan)
composer build:vendor   # Install production deps only
```

### JavaScript/CSS

```bash
pnpm run build:assets    # Minify CSS/JS, generate RTL CSS (node build-assets.js)
pnpm run zip             # Create distribution zip (wp-scripts plugin-zip)
ncu -u && pnpm install   # Update dependencies to latest and reinstall
```

No Gutenberg blocks; no `pnpm run build` / `pnpm start` scripts.

## Architecture

### Entry Point

`autoclose.php` defines constants, loads `includes/class-autoloader.php` (class-based autoloader — `Autoloader::register()`) and `includes/backward-compatibility.php`, registers activation/deactivation hooks (`AutoClose::activate`/`deactivate`, delegating to `Core\Activator`/`Deactivator`), then calls `acc_init()` on `plugins_loaded`, which instantiates `AutoClose::get_instance()` and calls `->run()`.

### Main class (`includes/class-autoclose.php`)

Singleton (`AutoClose::get_instance()`). Unlike other WebberZone plugins, hooks register inside the constructor, not a separate `init()` called after instantiation. The constructor calls:

- `load_dependencies()` — instantiates `Admin\Settings`
- `set_locale()` — hooks `Util\L10n::load_plugin_textdomain` on `init`
- `define_admin_hooks()` — instantiates `Admin\Admin` and `Admin\Tools`; registers plugin row meta/action links and the tools admin menu page
- `define_feature_hooks()` — instantiates all feature classes and registers their hooks

`run()` is a no-op — hooks are already registered.

### Features (`includes/features/`)

Each feature class is instantiated once in `define_feature_hooks()`. `Comments`, `Revisions`, `Block_Pings`, and `Close_Date` register hooks there; `Reopen` and `Notifications` register hooks in their own constructors. All hook registration goes through `Util\Hook_Registry::add_action()`/`add_filter()`.

- **`Comments`** — `process_comments()` runs on `acc_cron_hook`; closes comments on posts older than the configured age, per post type, with optional term exclusions.
- **`Revisions`** — `process_revisions()` runs on `acc_cron_hook`; deletes revisions beyond the configured limit. `revisions_to_keep()` hooks `wp_revisions_to_keep` to enforce per-post-type limits on new saves.
- **`Block_Pings`** — hooks `pre_ping` to prevent self-pings.
- **`Close_Date`** — closes based on a specific date rather than age.
- **`Reopen`** — auto-reopens comments/pings on post update when configured.
- **`Notifications`** — sends an email summary after the cron job completes; template at `includes/features/views/email-cron-summary.php`.

### Cron (`includes/util/class-cron.php`)

`Cron::enable_run($hour, $min, $recurrence)` schedules the `acc_cron_hook` WP-Cron event; called from `Core\Activator` on activation and from Settings on save when the scheduler option changes.

### Admin (`includes/admin/`)

- **`Settings`** — Settings page under Settings menu (`acc_options_page`). Tabs: General (cron schedule, email notifications), Comments, Pingbacks/Trackbacks, Revisions.
- **`Tools`** — tools page for one-time manual runs.
- **`Metabox`** — Per-post override meta for keeping comments/pings open regardless of global settings.

### Options access

Feature classes use `WebberZone\AutoClose\Options_API::get_option($key)` (`includes/class-options-api.php`) — the only live options layer, with a blog-keyed cache for multisite correctness. Avoid the legacy `acc_get_settings()` wrapper in new code.

`Options_API::get_default_option()` reads the raw `Admin\Settings::get_defaults()` array with the `acc_settings_defaults` filter applied. See the defaults contract in the `Settings_API` repo.

### Backward compatibility

`includes/backward-compatibility.php` provides procedural wrappers (`acc_get_settings()`, `acc_close_comments()`, etc.) for third-party code targeting versions prior to 3.0.

## Shared framework files: `@since` convention

The Settings API (`includes/admin/settings/*.php`) and Admin Banner (`includes/admin/class-admin-banner.php`) are copy-pasted shared framework files; canonical source is the `Settings_API` repo. To keep `@since` tags meaningful and stable across syncs, these files follow special rules:

- Each file carries **exactly one** `@since` tag, on its **class docblock**, set to the plugin version that class was **first introduced** in (per-file — wizard, metabox, and banner classes were generally added later than the core Settings API classes).
- **Do not** add `@since` to methods, functions or properties in these files.
- When syncing from another plugin or the canonical `Settings_API` repo, **do not overwrite the class-level `@since`** — it's plugin-specific; re-apply the values below after syncing.

| File | `@since` |
|---|---|
| `includes/admin/settings/class-settings-api.php` | 3.0.0 |
| `includes/admin/settings/class-settings-form.php` | 3.0.0 |
| `includes/admin/settings/class-settings-sanitize.php` | 3.0.0 |
| `includes/admin/settings/class-settings-wizard-api.php` | 3.1.0 |
| `includes/admin/settings/class-metabox-api.php` | 3.0.0 |
| `includes/admin/class-admin-banner.php` | 3.1.2 |
