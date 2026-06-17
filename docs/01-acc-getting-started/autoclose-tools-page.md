---
slug: autoclose-tools-page
title: "AutoClose Tools Page"
products: [autoclose]
sections: [01-acc-getting-started]
tags: [autoclose,tools]
status: publish
order: 2
---

The [AutoClose](https://webberzone.com/plugins/autoclose/) Tools page provides one-click buttons to run the closing and opening actions immediately, without waiting for the scheduled cron. Access it via **Tools → AutoClose Tools** in your WordPress admin.

## Available actions

### Run all scheduled actions now

Executes the close-comments, close-pingbacks/trackbacks, and delete-revisions logic that the scheduled cron would run. Each task runs only if its corresponding feature is enabled in **Settings → AutoClose**. A confirmation message at the top of the page shows which tasks fired and the date/time boundary each one used.

### Open comments on all post types

Sets `comment_status` to `open` on every post in the configured post types.

### Open pingbacks/trackbacks on all post types

Sets `ping_status` to `open` on every post in the configured post types.

### Close comments on all post types

Sets `comment_status` to `closed` on every post in the configured post types, regardless of age.

### Close pingbacks/trackbacks on all post types

Sets `ping_status` to `closed` on every post in the configured post types, regardless of age.

### Delete pingbacks/trackbacks on all post types

Permanently removes all pingback and trackback comments on every post in the configured post types.

### Delete revisions on all post types

Permanently removes post revisions on every post in the configured post types.

## Notes

- Each button submits a form protected by WordPress nonces; only users with the `manage_options` capability can use the page.
- The "Run all" button honors your current settings. If you have not enabled a feature on the settings page, the corresponding action is skipped and the result message reports that nothing was processed.
- These actions run synchronously. On large sites the revisions and pingback-deletion actions may take a while to complete.
