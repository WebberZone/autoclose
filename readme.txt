=== Auto-Close Comments, Pingbacks and Trackbacks ===
Tags: autoclose, comments, pingback, trackback, spam, anti-spam
Contributors: Ajay
Donate link: http://ajaydsouza.com/donate/
Stable tag: trunk
Requires at least: 4.5
Tested up to: 5.1
License: GPL v2 or later

Close comments, pingbacks and trackbacks on your posts automatically at intervals set by you

== Description ==

Spammers target old posts in a hope that you won't notice the comments on them. Why not stop them in their tracks by just shutting off comments and pingbacks? [Auto-Close Comments, Pingbacks and Trackbacks](http://ajaydsouza.com/wordpress/plugins/autoclose/) let's you automatically close comments, pingbacks and trackbacks on your posts, pages and custom post types.

You can also choose to keep comments / pingbacks / trackbacks open on certain posts, page or custom post types. Just enter a comma-separated list of post IDs in the Settings page.

An extra feature is the ability to delete post revisions that were introduced in WordPress v2.6 onwards.


= Key features =

* **Close comments**: Automatically close comments on posts, pages, attachments and even Custom Post Types!
* **Close pingbacks and trackbacks**: Automatically close pingbacks and trackbacks as well
* **Choose how old**: Choose a custom time period as to when the comments, pingbacks and trackbacks need to be closed
* **Scheduling**: You can also schedule a cron job to automatically close comments, pingbacks and trackbacks daily
* **Bonus**: Delete post revisions, pingbacks and trackbacks


== Installation ==

= WordPress install =
1. Navigate to Plugins within your WordPress Admin Area

2. Click "Add new" and in the search box enter "autoclose"

3. Find the plugin in the list (usually the first result) and click "Install Now"

= Manual install =
1. Download the plugin

2. Extract the contents of autoclose.zip to wp-content/plugins/ folder. You should get a folder called autoclose.

3. Activate the Plugin in WP-Admin.

4. Goto **Settings &raquo; Auto-Close** to configure


== Upgrade Notice ==

= 2.0.0 =
Major upgrade. Complete rewrite. You will need to reactivate the plugin.
Check settings on upgrade. Check the ChangeLog for details


== Changelog ==

= 2.0.0 =

Release post: https://wzn.io/2EOQ0Ec

* Features:
	* New Tools page with several buttons to open, close and delete comments, pingbacks and trackbacks. You can find the link in the Settings page under the main header
	* New button to delete all pingbacks and trackbacks in the Tools page
	* Activating the plugin on Multisite should upgrade settings from v1.x
	* Uninstalling the plugin on Multisite will delete the settings from all sites

* Enhancements:
	* Migrated options to the Settings API

* Modifications:
	* Main plugin file has been renamed to autoclose.php
	* Cron hook renamed from `ald_acc_hook` to `acc_cron_hook`

For older changes, refer to changelog.txt

== Screenshots ==

1. Autoclose Settings - General
2. Autoclose Settings - Comments
3. Autoclose Settings - Pingbacks/Trackbacks
4. Autoclose Tools


== Frequently Asked Questions ==

If your question isn't listed there, please create a new post at the [WordPress.org support forum](http://wordpress.org/support/plugin/autoclose). It is the fastest way to get support as I monitor the forums regularly. I also provide [premium *paid* support via email](https://ajaydsouza.com/support/).

= What does "Delete Post Revisions" do?  =

The WordPress revisions system stores a record of each saved draft or published update. This can gather up a lot of overhead in the long run. Use this option to delete old post revisions.

If you enable this option and turn on the cron job then any new revisions will be automatically deleted on a daily basis.
