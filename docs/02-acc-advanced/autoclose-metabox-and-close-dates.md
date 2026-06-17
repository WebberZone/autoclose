---
slug: autoclose-metabox-and-close-dates
title: "AutoClose Metabox and Close Dates"
products: [autoclose]
sections: [02-acc-advanced]
tags: [autoclose,metabox,scheduling]
status: publish
order: 2
---

The [AutoClose](https://webberzone.com/plugins/autoclose/) metabox on the post edit screen lets you schedule a specific date and time to close comments, pingbacks, or trackbacks on a single post. This is useful for evergreen posts you want to keep open for a known window, or for announcements whose comment period ends on a fixed date.

## Where the metabox appears

The **AutoClose Settings** metabox appears on the post edit screen for any public post type that supports comments — posts, pages, attachments, and any custom post type that has `supports => array( 'comments' )` in its registration. It does not appear on post types that do not support comments.

## Fields

### Close comments on

Date and time at which comments should close on this post. Leave blank to leave comments open indefinitely (subject to the global close age).

### Close pingbacks/trackbacks on

Date and time at which pingbacks and trackbacks should close on this post. Leave blank to leave pings open indefinitely (subject to the global close age).

The two fields are independent: you can close comments on a specific date and let pingbacks run, or vice versa. Both fields are also independent of the post's existing `comment_status` and `ping_status` — they schedule a future action, they do not toggle the post's status immediately.

## How the scheduled close works

When you save the post, the plugin reads both date fields and schedules two separate single events on the WordPress cron:

- A `autoclose_close_comments_pings_event` event with argument `comments` at the configured close-comments timestamp.
- A `autoclose_close_comments_pings_event` event with argument `pings` at the configured close-pings timestamp.

When the event fires, the plugin checks the post's current state and closes comments or pingbacks if they are not already `closed`. If the configured date is already in the past at the moment you save the post, the close runs immediately on the next cron pass.

If you change either date and re-save the post, any previously scheduled event for that field is cleared and a new event is registered. This prevents stale events from firing after you have moved the date.

## Interaction with the global scheduled close

The metabox schedules a one-shot cron event for a specific post. It runs alongside, not in place of, the global `acc_cron_hook` that closes comments by age. If the global cron closes comments on a post before the metabox-scheduled date arrives, the metabox event will not re-open them. The metabox schedules the close; it does not protect against an earlier bulk close.

If you want a post to keep its comments open until a specific date, the cleanest approach is to:

1. Add the post ID to **Comments → Keep comments on these posts/pages open** in the settings, so the global cron leaves it alone.
2. Use the metabox to set the close date.

## Email and Tools page behavior

The metabox does not send any notification when the scheduled close fires. The summary email from **Settings → General → Send summary email after cron run** only reports the bulk cron activity, not metabox-scheduled closes. The **Tools → AutoClose Tools** page does not read the metabox date fields — its buttons run the bulk actions and respect only the global settings and the keep-open post ID lists.
