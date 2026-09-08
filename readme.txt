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
Adds WP-CLI commands for status, maintenance, discussions, revisions, pingbacks, close dates, and cron.

== Changelog ==

= 3.2.0 =

Release date: 8 September 2026

**Added**

* Added WP-CLI commands for status, maintenance, discussions, revisions, pingbacks, close dates, and cron.
* Added a revision age cutoff. Scheduled cleanup now deletes a revision only when it is beyond the number of revisions its post keeps and is older than the configured age. Defaults to 90 days.
* Added `wp autoclose revisions prune` for age and retention aware cleanup, and `--all` on `wp autoclose revisions preview`.
* Added the `acc_revisions_prune_limit` and `acc_revisions_prune_cutoff` filters to override how many revisions a cleanup run may delete and the instant a revision must predate to be eligible.

**Changed**

* Scheduled revision cleanup no longer deletes every revision on each run. It follows the retention and age policy above, and never deletes autosaves. Existing settings are preserved; the one-time Delete all revisions button on the Tools page still deletes everything. Sites that already had Delete post revisions enabled see a one-time dismissible notice explaining the change.
* Revisions are now deleted through the WordPress deletion API, so metadata, caches, and the `wp_delete_post_revision` action are handled by core. Term relationships are cleaned up separately, since WordPress only clears those for taxonomies registered against the post type and none is registered for revisions.
* Revision cleanup runs are bounded per run and report how many revisions were scanned and whether more remain, so a large site is cleaned up over several runs instead of one long request.
* Revision cleanup reports partial progress and the reason for a failure instead of a bare count, on the Tools page, in WP-CLI, and in the cron summary.
* Improved bulk operations with batched updates and cache invalidation.
* Limited default CLI scopes to supported public post types, excluding attachments and revisions.

**Fixed**

* Fixed close dates and revision restores reopening comments.
* Fixed incorrect close-date results and missing date controls.
* Removed orphaned revision metadata and term relationships.
* Fixed stale caches and restored `clean_post_cache` notifications.
* Fixed the cutoff date shown after running the closing algorithm being an hour out for revisions when the cutoff fell the other side of a daylight-saving change.
* Improved daylight-saving date validation.

= Earlier versions =

For the changelog of earlier versions, please refer to the [releases page on GitHub](https://github.com/WebberZone/autoclose/releases).
