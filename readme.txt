=== Auto-Close Comments, Pingbacks and Trackbacks ===
Tags: comments, pingback, revisions, spam, anti-spam
Contributors: webberzone, Ajay
Donate link: https://wzn.io/donate-wz
Stable tag: 3.1.0
Requires at least: 6.6
Tested up to: 7.0
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

= 3.1.0 =
Major plugin changes. Check the Changelog and release post for complete information.

== Changelog ==

= 3.2.0 =

* Bug fixes:
    * Plugin re-activation now correctly re-schedules the cron job if the scheduler was previously enabled. Previously, the cron remained unscheduled until settings were manually re-saved.
    * Fixed per-post close date scheduling: when both a comments date and a pings date were set on the same post, only the first event was registered. Comments and pings events are now scheduled as distinct cron entries and both fire correctly.
    * Fixed date comparison in the comment/ping closing query to use UTC time against `post_date_gmt`, preventing posts from being closed at the wrong time on servers where the PHP timezone differs from UTC.

= 3.1.0 =
Release post: [https://webberzone.com/announcements/auto-close-v3-1-0/](https://webberzone.com/announcements/auto-close-v3-1-0/)

* New features:
    * Exclude posts in specific categories, tags, or any taxonomy term from having comments or pingbacks/trackbacks closed.
    * Reopen comments automatically when a published post is saved or updated, with a configurable number of days before they are closed again by the cron.
    * Email summary notification after each scheduled cron run, showing the number of comments closed, pings closed, and revisions deleted.

* Bug fixes:
    * Fixed undefined variable warnings when processing taxonomy term exclusions.
    * Fixed Settings API repeater fields: form submission, hidden input names, and sanitization with row ID lookup.

* Modifications:
    * Update Settings API and other reusable classes in line with the latest WebberZone plugins.
    * Email summary now uses an HTML template for improved readability.
    * Term exclusion fields now use an autocomplete search (Tom Select) instead of manual ID entry.

= 3.0.0 =
Release post: [https://webberzone.com/announcements/auto-close-v3-0-0/](https://webberzone.com/announcements/auto-close-v3-0-0/)

Completely rewritten the plugin to use autoloading, namespaces and classes.

* Features:
    * Added block ping URLs feature and self-pings feature.
    * Introduced a new meta box allowing users to schedule the closure of comments, pingbacks, and trackbacks for the current post.

* Bug fixes:
    * Fixed PHP error/warnings about loading translations too early.

For older changes, refer to changelog.txt
