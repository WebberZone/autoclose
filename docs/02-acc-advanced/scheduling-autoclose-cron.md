---
slug: scheduling-autoclose-cron
title: "Scheduling the AutoClose Cron"
products: [autoclose]
sections: [02-acc-advanced]
tags: [autoclose,cron,scheduling]
status: publish
order: 0
---

[AutoClose](https://webberzone.com/plugins/autoclose/) uses a WordPress cron event named `acc_cron_hook` to run its maintenance tasks on a schedule. Understanding how the schedule is built helps when you want to debug missed runs, run at a different time, or migrate to a real server cron.

## How the schedule is built

The schedule is created from the **General** tab of the settings page:

- **Activate scheduled closing** — the master switch. When disabled, no cron event is registered.
- **Hour** and **Minute** — the time of day the event fires, in `0`–`23` and `0`–`59` respectively.
- **Run maintenance** — the recurrence: `daily`, `weekly`, `fortnightly`, or `monthly`.

When you save the settings with **Activate scheduled closing** enabled, the plugin clears any existing `acc_cron_hook` event and reschedules a new one for the next occurrence of the configured hour:min. When you disable the master switch, the event is removed.

The schedule uses `gmmktime()` (UTC), so the configured hour:min is evaluated against UTC time and is unaffected by your site's timezone setting.

## Time of first run

The first run of a newly scheduled event is the next occurrence of the configured hour:min in UTC. If that time has already passed today, the event runs at the same hour:min the next day (or the next week, fortnight, or month, depending on the recurrence).

## Verifying the cron is registered

You can confirm the event is scheduled with WP-CLI:

```bash
wp cron event list | grep acc_cron_hook
```

You should see the event name and its next run time. If the event does not appear, save the settings again with **Activate scheduled closing** enabled.

## Re-activation after plugin deactivation

If you deactivate the plugin, the deactivator clears `acc_cron_hook` and any `autoclose_close_comments_pings_event` events for scheduled close dates. Re-activating the plugin calls `Cron::enable_run()` from the activator if **Activate scheduled closing** was enabled at deactivation time, so the schedule resumes automatically with the saved hour, minute, and recurrence. The same applies on multisite network activation.

## Running on a real server cron

The plugin relies on WordPress's built-in pseudo-cron, which only fires when the site receives traffic. On low-traffic sites the schedule can drift or skip. If you run a real server cron that hits `wp-cron.php`, disable the default behavior by adding this to `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

The plugin's own `acc_cron_hook` event is registered with the standard WordPress cron system. It will still fire when your real cron hits `wp-cron.php`, but only if `DISABLE_WP_CRON` does not block the registration. With `DISABLE_WP_CRON` defined, register a server cron to hit `https://example.com/wp-cron.php` on a 5-minute interval, and `acc_cron_hook` will fire on its own schedule alongside all other WordPress cron events.

## What the cron actually does

When `acc_cron_hook` fires, the plugin calls into the registered feature classes:

- `WebberZone\AutoClose\Features\Comments::process_comments()` — closes comments and pingbacks/trackbacks on posts older than the configured ages, then re-opens comments on the post IDs in the keep-open list.
- `WebberZone\AutoClose\Features\Revisions::process_revisions()` — deletes revisions beyond the per-post-type limit.

If **Send summary email after cron run** is enabled, an HTML email is sent to the configured address (or the site admin email) summarizing how many comments, pingbacks/trackbacks, and revisions were processed.
