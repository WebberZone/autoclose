# Auto-Close Comments, Pingbacks and Trackbacks

![Auto-Close](https://raw.githubusercontent.com/WebberZone/autoclose/master/wporg-assets/banner-1544x500.png)

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/autoclose.svg?style=flat-square)](https://wordpress.org/plugins/autoclose/) [![License](https://img.shields.io/badge/license-GPL_v2%2B-orange.svg?style=flat-square)](https://opensource.org/licenses/GPL-2.0) [![WordPress Tested](https://img.shields.io/wordpress/v/autoclose.svg?style=flat-square)](https://wordpress.org/plugins/autoclose/) [![Required PHP](https://img.shields.io/wordpress/plugin/required-php/autoclose?style=flat-square)](https://wordpress.org/plugins/autoclose/) [![Active installs](https://img.shields.io/wordpress/plugin/installs/autoclose?style=flat-square)](https://wordpress.org/plugins/autoclose/)

_Requires:_ 6.6
_Tested up to:_ 7.1
_Requires PHP:_ 7.4
_License:_ [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.html)  
_Plugin page:_ [Auto-Close Comments, Pingbacks and Trackbacks](https://webberzone.com/plugins/autoclose/) | [WordPress.org plugin page](https://wordpress.org/plugins/autoclose/)

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [WP-CLI](#wp-cli)
- [Filters](#filters)
- [Contributing](#contributing)
- [For Users](#for-users)
- [Screenshots](#screenshots)
- [Changelog](#changelog)
- [License](#license)

---

## Overview

_Auto-Close_ is a WordPress plugin that _automatically closes comments, pingbacks, and trackbacks_ on posts, pages, and custom post types after a configurable period. It helps reduce spam and lets you manage post revisions easily.

- _OOP & Namespaced:_ All code under `WebberZone\AutoClose`, following WordPress PHP coding standards.
- _Extensible:_ Modular structure for easy maintenance and extension.
- _WP Cron:_ Schedules automatic closing based on user-defined intervals.

---

## Features

- _Automatically close_ comments, pingbacks, and trackbacks on posts, pages, and custom post types
- _Per-post-type closing ages_ overriding the global age, each set to inherit, never, or an explicit number of days
- _Close by approved comment count_ as an alternative trigger to age; a post closes on whichever condition it meets first
- _Open or close_ comments/pingbacks/trackbacks selectively by post ID
- _Close on a specific date_ per post, reconciled on activation so scheduled events survive deactivation
- _Exclude posts_ in specific categories, tags, or any taxonomy term from auto-closing
- _Reopen comments_ automatically when a post is updated, with a configurable open window
- _Email summary_ after each scheduled cron run showing comments closed, pings closed, and revisions deleted
- _Schedule_ automatic closing using WordPress cron, daily through monthly
- _Block self-pings and custom ping URLs_
- _Delete or limit_ post revisions by type, with scheduled cleanup bounded by both a retention limit and an age cutoff
- _Manual tools_ for opening/closing and deleting, each destructive action with a read-only preview
- _Status panel_ reporting cron health, the next run, and the last run's outcome, with a repair action
- _Configuration report_ on demand, covering effective settings and counts without post or comment content
- _WP-CLI commands_ for every maintenance operation, with `--dry-run` support
- _Admin interface_ with settings and tools tabs
- _Extensible, OOP, and namespaced_ codebase

---

## WP-CLI

All commands live under `wp autoclose`. Everything that changes content accepts `--dry-run`; all accept `--format=table|json|csv`.

```bash
wp autoclose status
wp autoclose settings
wp autoclose run [--dry-run] [--sample=<n>] [--yes]
wp autoclose comments close|open
wp autoclose pings close|open
wp autoclose revisions preview|prune|delete
wp autoclose pingbacks preview|delete
wp autoclose close-date list|set|clear
wp autoclose cron repair [--force]
```

Full reference: [docs/02-acc-advanced/autoclose-wp-cli.md](./docs/02-acc-advanced/autoclose-wp-cli.md).

---

## Filters

Scheduled revision cleanup is bounded by two filters. Neither sets the retention count — that comes from the per-post-type revision settings via `wp_revisions_to_keep`.

| Filter | Purpose |
|---|---|
| `acc_revisions_prune_limit` | Maximum revisions a single pruning run may delete. Default `1000`; `0` removes the bound. |
| `acc_revisions_prune_cutoff` | UTC timestamp a revision must predate to be pruned. Return `null` to drop the age condition. |

---

## Contributing

We welcome contributions! Please:

- Fork the repository and create your branch from `master`.
- Follow the code style and structure above.
- Open issues for bugs, feature requests, or questions.

---

## For Users

See [readme.txt](./readme.txt) or the [WordPress.org plugin page](https://wordpress.org/plugins/autoclose/) for installation, features, screenshots, and FAQ. This `README.md` is intended for developers and contributors.

---

## Screenshots

![General Options](https://raw.github.com/ajaydsouza/autoclose/master/wporg-assets/screenshot-1.png)
> Auto-Close Comments, Pingbacks and Trackbacks - General Options

More screenshots: [WordPress plugin page](https://wordpress.org/plugins/autoclose/screenshots/)

---

## Changelog

See [releases](https://github.com/WebberZone/autoclose/releases) for the latest changes and beta versions.

---

## License

GPL v2 or later.

---
