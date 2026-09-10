=== Auto-Close Comments, Pingbacks and Trackbacks ===
Tags: comments, pingback, revisions, spam, anti-spam
Contributors: webberzone, Ajay
Donate link: https://wzn.io/donate-wz
Stable tag: 3.2.0
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
License: GPL v2 or later

Auto-Close keeps your site clean by automatically closing comments, pingbacks, and trackbacks—so you can focus on content, not cleanup.

== Description ==

Spammers target old posts in a hope that you won't notice the comments on them. Why not stop them in their tracks by just shutting off comments and pingbacks? [Auto-Close Comments, Pingbacks and Trackbacks](https://webberzone.com/plugins/autoclose/) lets you automatically close comments, pingbacks and trackbacks on your posts, pages and custom post types.

You can also choose to keep comments, pingbacks, or trackbacks open on certain posts, pages or custom post types. Just enter a comma-separated list of post IDs in the Settings page.

An extra feature is the ability to delete post revisions or limit their number.

Found a bug or want to contribute? PRs and issues welcome on [GitHub](https://github.com/WebberZone/autoclose). For help, use the [support forum](https://wordpress.org/support/plugin/autoclose) or [premium support](https://webberzone.com/support/).

## Key Features

* Close (or open) comments on posts, pages, attachments and even Custom Post Types
* Close (or open) pingbacks and trackbacks as well across all post types. You can also choose to delete them
* Schedule a cron job to automatically close comments, pingbacks and trackbacks daily
* Delete all post revisions or limit the number of revisions by post type
* Exclude specific post IDs from auto-close
* Exclude posts in specific categories, tags, or any taxonomy term from auto-close
* Reopen comments automatically when a post is updated, with a configurable open window
* Receive an email summary after each scheduled cron run showing what was closed or deleted
* Block self-pings and custom ping URLs
* Schedule the closing of comments, pingbacks, and trackbacks for the current post

== Screenshots ==

1. Autoclose Settings - General
2. Autoclose Settings - Comments
3. Autoclose Settings - Pingbacks/Trackbacks
4. Autoclose Settings - Revisions
5. Autoclose Tools

== Installation ==

= WordPress install =
1. Navigate to Plugins within your WordPress Admin Area
2. Click "Add new" and in the search box enter "autoclose"
3. Find the plugin in the list (usually the first result) and click "Install Now"

= Manual install =
1. Download the plugin
2. Extract the contents of autoclose.zip to wp-content/plugins/ folder. You should get a folder called autoclose.
3. Activate the Plugin in WP-Admin.
4. Go to Settings » AutoClose to configure

== Frequently Asked Questions ==

= How do I exclude a post from auto-closing? =
Enter the post ID in the settings page under "Exclude posts".

= Will this plugin work with custom post types? =
Yes! Auto-Close works with posts, pages, attachments, and any registered custom post types.

= Can I delete or limit revisions? =
Yes, you can delete all revisions or set a limit per post type from the settings page.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/wordpress/plugin/autoclose/vdp)

== Upgrade Notice ==

= 3.2.0 =
Adds per-post-type closing ages, an approved-comment threshold, Tools page previews, a cron status panel, and a configuration report, plus fixes for close dates, revision restores, and caching. Review revision retention settings before enabling cleanup.

== Changelog ==

= 3.2.0 =

Release date: 10 September 2026

**Added**

* Added WP-CLI commands for status, maintenance, discussions, revisions, pingbacks, close dates, and cron.
* Added age- and retention-aware revision pruning with a 90-day default cutoff and the `acc_revisions_prune_limit` and `acc_revisions_prune_cutoff` filters.
* Added `fortnightly` and `monthly` cron recurrences.
* Independent per-post-type age overrides for closing comments and pingbacks/trackbacks, alongside the existing global age.
* An optional approved-comment count threshold: comments close once a post reaches the configured count, in addition to the age rule.
* A read-only Preview changes option on the Tools page for the closing algorithm, pingback/trackback deletion, and revision deletion, showing matching counts, scope, and a sample of affected posts before you run them.
* An AutoClose Status panel on the Tools page showing scheduling health, the next run, and the latest run's outcome, with a Repair schedule action.
* An on-demand Configuration Report on the Tools page covering effective settings, exception counts, close-date and reopen-window counts, and revision policies, without post content, comment text, or credentials.
* Guidance on the Settings General tab explaining what stays and what stops if the plugin is deactivated.

**Changed**

* Scheduled revision cleanup now respects each post's retention limit and age cutoff; autosaves remain protected and the Tools delete-all action remains explicit.
* Added lifecycle reconciliation for scheduled close dates and labeled cron schedule times as UTC.
* Improved bulk maintenance with supported public post-type scopes and truthful no-op and partial results.
* Migrated legacy `close` discussion statuses to WordPress's canonical `closed` value once per site.
* Preserved existing revision settings and added an admin notice explaining the new cleanup policy.

**Fixed**

* Close-date and revision-restore updates no longer reopen closed comments.
* Fixed close-date results, date controls, DST validation, and discussion age cutoff calculations.
* Revision deletion now removes orphaned metadata and term relationships.
* Fixed stale caches, cache invalidation notifications, and per-site cron notification counts.

= Earlier versions =

For the changelog of earlier versions, please refer to the [releases page on GitHub](https://github.com/WebberZone/autoclose/releases).
