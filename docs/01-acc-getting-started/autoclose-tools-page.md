---
slug: autoclose-tools-page
title: "AutoClose Tools Page"
products: [autoclose]
sections: ["01-acc-getting-started"]
tags: [autoclose, tools]
status: publish
order: 2
---

The [AutoClose](https://webberzone.com/plugins/autoclose/) Tools page provides one-click buttons to run the closing and opening actions immediately, without waiting for the scheduled cron. Access it via **Tools → AutoClose Tools** in your WordPress admin, or via the **Tools** link in the admin banner shown on both the Tools and [Settings](autoclose-settings.md) pages.

## AutoClose Status

A panel at the top of the page reports scheduling health: whether scheduled maintenance is enabled, whether its cron event is registered, the next run time (shown in your site's timezone), the recurrence, whether `DISABLE_WP_CRON` is set, and the last run's attempt time, completion time, outcome, and counts.

A warning banner appears when the last run failed or was only partially successful, or when a run started but never reported completion within an hour (a stuck run). Otherwise a success notice confirms scheduling looks healthy.

Use **Repair schedule** to reschedule the `acc_cron_hook` event from the current time and your saved recurrence settings. It only takes effect when **Activate scheduled closing** is enabled — it does not turn scheduling on. This is the same action as `wp autoclose cron repair`, documented in [AutoClose WP-CLI](../02-acc-advanced/autoclose-wp-cli.md).

## Configuration Report

Click **Generate configuration report** for an on-demand, read-only summary of the site's effective AutoClose configuration: the schedule, comment and pingback/trackback settings (including per-post-type age overrides and the approved-comment threshold), exception counts (keep-open post IDs and excluded terms), revision retention per post type, the number of posts with an explicit close date or an active reopen window, and whether the summary email is enabled.

The report contains no post content, comment text, credentials, or individual post IDs — only counts and effective settings. It is shown on the page only and is not saved to a file.

## Available actions

### Run all scheduled actions now

Executes the close-comments, close-pingbacks/trackbacks, and delete-revisions logic that the scheduled cron would run. Each task runs only if its corresponding feature is enabled in **Settings → AutoClose**. A confirmation message at the top of the page shows which tasks fired and the date/time boundary each one used.

Click **Preview changes** first to see, read-only, what the algorithm would do: the number of matching posts per operation, the scope (post types and age cutoffs, including any per-post-type overrides and the approved-comment threshold), and a sample of up to 10 affected posts. Nothing is changed by a preview, and matching content can change before you click **Run closing algorithm** — the eligibility check runs again at execution time.

### Open comments on all post types

Sets `comment_status` to `open` on every post in the configured post types.

### Open pingbacks/trackbacks on all post types

Sets `ping_status` to `open` on every post in the configured post types.

### Close comments on all post types

Sets `comment_status` to `closed` on every post in the configured post types, regardless of age.

### Close pingbacks/trackbacks on all post types

Sets `ping_status` to `closed` on every post in the configured post types, regardless of age.

### Delete pingbacks/trackbacks on all post types

Permanently removes all pingback and trackback comments on every post in the configured post types. Click **Preview changes** first to see the matching count and a sample of affected posts without deleting anything.

### Delete all revisions

Permanently removes **every** post revision, including autosaves, ignoring the retention limits and the age cutoff configured on the settings page. The confirmation prompt spells this out, and the result message reports how many revisions were deleted.

For ordinary cleanup that respects retention and age, use **Run closing algorithm** above, or `wp autoclose revisions prune`.

Click **Preview changes** first to see the matching count and a sample of affected revisions without deleting anything.

## Notes

- Each button submits a form protected by WordPress nonces; only users with the `manage_options` capability can use the page.
- The "Run all" button honors your current settings. If you have not enabled a feature on the settings page, the corresponding action is skipped and the result message reports that nothing was processed.
- These actions run synchronously. On large sites the revisions and pingback-deletion actions may take a while to complete.
