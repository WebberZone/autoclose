---
slug: autoclose-settings
title: "AutoClose Settings"
products: [autoclose]
sections: [01-acc-getting-started]
tags: [autoclose,settings]
status: publish
order: 1
---

[kbtoc]

This document describes all available settings for the [AutoClose](https://webberzone.com/plugins/autoclose/) plugin. Access settings via **Settings → AutoClose** in your WordPress admin.

## General

### Activate scheduled closing

Enable to create a WordPress cron job using the schedule settings below. The cron job executes the tasks to close comments, close pingbacks/trackbacks, and delete post revisions based on the settings from the other tabs.

**Default:** Disabled

### Time to run closing

The next two options set the time the cron job runs. The cron job runs immediately if the configured hour:min is before the current time (for example, 9:00 when the time is now 20:30). Otherwise it runs later today at the scheduled time.

#### Hour

Hour at which the cron job runs. `0`–`23`.

**Default:** `0`

#### Minute

Minute at which the cron job runs. `0`–`59`.

**Default:** `0`

### Run maintenance

How often the cron job runs.

**Options:**

- **Daily** (default)
- **Weekly**
- **Fortnightly**
- **Monthly**

### Send summary email after cron run

Enable to send an email summary after each scheduled run. The summary lists the number of comments closed, pingbacks/trackbacks closed, and revisions deleted.

**Default:** Disabled

### Notification email address

Address to which the summary email is sent. Leave blank to use the site admin email address.

**Default:** empty (uses `admin_email`)

## Comments

### Close comments

Enable to close comments. Used for the automatic schedule as well as one-time runs under **Tools → AutoClose Tools**.

**Default:** Disabled

### Post types to include

Select the post types on which to close comments. At least one option must be selected.

**Default:** `post`

### Close comments on posts/pages older than

Comments on posts older than this number of days are closed automatically when the schedule is enabled.

**Default:** `90`

### Keep comments on these posts/pages open

Comma-separated list of post IDs whose comments should remain open. For example, `188,320,500`.

**Default:** empty

### Exclude posts in these categories/tags

Taxonomy terms whose posts should not have comments closed. Start typing to search for categories, tags, or other public taxonomy terms. The field has autocomplete.

**Default:** empty

### Reopen comments on post update

When a post is saved or updated, its comments reopen for the number of days set in **Keep comments open for (days)**.

**Default:** Disabled

### Keep comments open for (days)

Number of days to keep comments open after a post update. Set to `0` to keep open until the next scheduled close.

**Default:** `30`

## Pingbacks/Trackbacks

### Close Pingbacks/Trackbacks

Enable to close pingbacks and trackbacks. Used for the automatic schedule as well as one-time runs under **Tools → AutoClose Tools**.

**Default:** Disabled

### Post types to include

Select the post types on which to close pingbacks/trackbacks. At least one option must be selected.

**Default:** `post`

### Close pingbacks/trackbacks on posts/pages older than

Pingbacks/trackbacks on posts older than this number of days are closed automatically when the schedule is enabled.

**Default:** `90`

### Keep pingbacks/trackbacks on these posts/pages open

Comma-separated list of post IDs whose pingbacks/trackbacks should remain open. For example, `188,320,500`.

**Default:** empty

### Exclude posts in these categories/tags

Taxonomy terms whose posts should not have pingbacks/trackbacks closed. Start typing to search for categories, tags, or other public taxonomy terms. The field has autocomplete.

**Default:** empty

### Block Self-Pings

Enable to block self-pings — pings from a post to other pages on the same site.

**Default:** Disabled

### Block Ping URLs

One URL per line. Pings to any of these URLs are blocked in addition to self-pings.

**Default:** empty

## Revisions

### Delete post revisions

WordPress stores a record of each saved draft or published update. This can build up over time. Enable to delete old post revisions when the cron runs.

**Default:** Disabled

### Number of revisions

Limit the number of revisions that WordPress stores in the database for each post type. Old revisions are deleted automatically.

The setting is per post type. Value semantics:

- `-2` — ignore this plugin's setting (use WordPress default).
- `-1` — store every revision.
- `0` — do not store any revisions.
- `>0` — store that many revisions per post.

**Default:** `-2` for every supported post type.
