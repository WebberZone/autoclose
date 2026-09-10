---
slug: scheduling-autoclose-cron
title: "Scheduling the AutoClose Cron"
products: [autoclose]
sections: ["02-acc-advanced"]
tags: [autoclose, cron, scheduling]
status: publish
order: 0
---

[AutoClose](https://webberzone.com/plugins/autoclose/) uses a WordPress cron event named `acc_cron_hook` to run its maintenance tasks on a schedule. Understanding how the schedule is built helps when you want to debug missed runs, run at a different time, or migrate to a real server cron.

## How the schedule is built

The schedule is created from the **General** tab of the settings page:

- **Activate scheduled closing** — the master switch. When disabled, no cron event is registered.
- **Hour** and **Minute** — the time of day the event fires, in `0`–`23` and `0`–`59` respectively.
- **Run maintenance** — the recurrence: `daily`, `weekly`, `fortnightly`, or `monthly`.

> **Fixed in v3.2.0.** The `fortnightly` and `monthly` recurrences were selectable in earlier versions but were never registered as WordPress cron schedules, so choosing either one scheduled no maintenance event at all. If your site was set to `fortnightly` or `monthly`, confirm a next run is now showing on the **Tools → AutoClose Tools** status panel after upgrading.

When you save the settings with **Activate scheduled closing** enabled, the plugin clears any existing `acc_cron_hook` event and reschedules a new one for the next occurrence of the configured hour:min. When you disable the master switch, the event is removed.

The schedule uses UTC, so the configured hour:min is evaluated against UTC time and is unaffected by your site's timezone setting. The AutoClose status command converts the next run to the site's timezone for readability.

## Time of first run

The first run of a newly scheduled event is the next occurrence of the configured hour:min in UTC. If that time has already passed today, the event runs at the same hour:min the next day (or the next week, fortnight, or month, depending on the recurrence).

## Verifying the cron is registered

You can confirm the event is scheduled with WP-CLI:

```bash
wp cron event list | grep acc_cron_hook
```

You should see the event name and its next run time. If the event does not appear, save the settings again with **Activate scheduled closing** enabled.

## Re-activation after plugin deactivation

If you deactivate the plugin, the deactivator clears `acc_cron_hook` and the `autoclose_close_dates_event` sweep. Re-activating the plugin calls `Cron::enable_run()` from the activator if **Activate scheduled closing** was enabled at deactivation time, so the schedule resumes automatically with the saved hour, minute, and recurrence. The same applies on multisite network activation.

### How per-post close dates are applied

Per-post close dates set through the [AutoClose metabox](autoclose-metabox-and-close-dates.md) are stored as post meta, not as one cron event per post. A single recurring event, `autoclose_close_dates_event`, runs hourly and closes every date that has fallen due, then deletes the meta it applied. One event serves the whole site, so a site with 100,000 close dates schedules exactly as much cron work as a site with one.

This means a close happens at the first sweep after the configured time rather than to the exact minute. Change the frequency with the `acc_close_dates_recurrence` filter, naming any registered schedule — WordPress provides `hourly`, `twicedaily`, `daily` and `weekly`, and AutoClose adds `fortnightly` and `monthly`:

```php
add_filter( 'acc_close_dates_recurrence', fn() => 'twicedaily' );
```

An unregistered name falls back to `hourly`, so register your own schedule first if you want a shorter interval:

```php
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['acc_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Every five minutes',
		);

		return $schedules;
	}
);
add_filter( 'acc_close_dates_recurrence', fn() => 'acc_five_minutes' );
```

Each sweep is bounded so a large backlog cannot exhaust one request; if more dates are due than one pass applies, it queues a single continuation a minute later. `acc_close_dates_batch_size` and `acc_close_dates_sweep_limit` adjust those bounds.

Because the dates live in post meta, they survive a deactivate/reactivate cycle and a plugin update without any reconciliation step. If the sweep is ever missing, **Repair schedule** on the [Tools page](../01-acc-getting-started/autoclose-tools-page.md) or `wp autoclose cron repair` re-registers it.

Sites upgrading from an earlier version have their old per-post events removed once, automatically, on the first request after the update.

## Running on a real server cron

The plugin relies on WordPress's built-in pseudo-cron, which only fires when the site receives traffic. On low-traffic sites the schedule can drift or skip. If you run a real server cron that hits `wp-cron.php`, disable the default behavior by adding this to `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

The plugin's own `acc_cron_hook` event is registered with the standard WordPress cron system. It will still fire when your real cron hits `wp-cron.php`, but only if `DISABLE_WP_CRON` does not block the registration. With `DISABLE_WP_CRON` defined, register a server cron to hit `https://example.com/wp-cron.php` on a 5-minute interval, and `acc_cron_hook` will fire on its own schedule alongside all other WordPress cron events.

## What the cron actually does

When `acc_cron_hook` fires, the plugin calls into the registered feature classes:

- `WebberZoneAutoCloseFeaturesComments::process_comments()` — closes comments and pingbacks/trackbacks on posts older than the configured ages, then re-opens comments on the post IDs in the keep-open list.
- `WebberZoneAutoCloseFeaturesRevisions::process_revisions()` — deletes revisions beyond the per-post-type limit.

If **Send summary email after cron run** is enabled, an HTML email is sent to the configured address (or the site admin email) summarizing how many comments, pingbacks/trackbacks, and revisions were processed.
