---
slug: autoclose-settings
title: "AutoClose Settings"
products: [autoclose]
sections: ["01-acc-getting-started"]
tags: [autoclose, settings]
status: publish
order: 1
toc: true
---

[toc]

This document describes all available settings for the [AutoClose](https://webberzone.com/plugins/autoclose/) plugin. Access settings via **Settings → AutoClose** in your WordPress admin.

Since v3.1.2, a search box on the settings screen filters options across all tabs as you type — start typing to jump straight to the setting you are looking for.

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

### Cleanup policy

Scheduled cleanup deletes a revision only when both conditions hold:

1. It is beyond the number of revisions its post keeps (**Number of revisions** below).
2. It is older than **Delete revisions older than**.

Autosaves are never deleted by scheduled cleanup, so autosave recovery data stays intact.

Before v3.2.0, enabling **Delete post revisions** deleted every revision on each scheduled run. Existing settings are preserved and nothing is enabled on upgrade, but scheduled runs now follow the policy above. Sites that already had the option enabled see a one-time dismissible admin notice explaining the change. To delete every revision regardless of retention and age, use the **Delete all revisions** button on the [Tools page](autoclose-tools-page.md).

Each run is bounded so a large site is cleaned up over several runs rather than in one long request. The bound is filterable:

```php
add_filter( 'acc_revisions_prune_limit', fn() => 5000 );
```

### Delete post revisions

WordPress stores a record of each saved draft or published update. This can build up over time. Enable to delete old post revisions when the cron runs.

**Default:** Disabled

### Delete revisions older than

Age cutoff in days. Only revisions older than this — and beyond the retention limit — are deleted. Set to `0` to delete every revision beyond the retention limit regardless of age.

**Default:** `90`

### Number of revisions

Limit the number of revisions that WordPress stores in the database for each post type. This is both the limit WordPress applies when saving and the retention floor that scheduled cleanup will not delete below.

The setting is per post type. Value semantics:

- `-2` — ignore this plugin's setting (use the WordPress default, `WP_POST_REVISIONS`, or another plugin's filter).
- `-1` — store every revision. Scheduled cleanup never prunes this post type.
- `0` — do not store any revisions. Every revision older than the age cutoff is eligible.
- `>0` — store that many revisions per post. The newest that many are kept.

**Default:** `-2` for every supported post type.
