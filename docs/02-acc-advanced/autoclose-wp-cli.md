---
slug: autoclose-wp-cli
title: "AutoClose WP-CLI"
products: [autoclose]
sections: ["02-acc-advanced"]
tags: [autoclose, wp-cli, cron]
status: publish
order: 4
toc: true
---

[toc]

AutoClose registers the following WP-CLI command tree. Use WP-CLI's global `--url` option to select a site in a multisite installation.

```text
wp autoclose status
wp autoclose settings
wp autoclose run
wp autoclose comments close|open
wp autoclose pings close|open
wp autoclose revisions preview|prune|delete
wp autoclose pingbacks preview|delete
wp autoclose close-date list|set|clear
wp autoclose cron repair
```

## `wp autoclose status`

Shows the current site's AutoClose version, feature settings, cron health, next scheduled run, timezone, and the latest maintenance outcome.

```bash
wp autoclose status
wp autoclose status --format=json
```

Use `--format=json` or `--format=csv` for scripts and monitoring. On multisite, select the site with WP-CLI's global `--url` option; status and maintenance data are stored per site.

Health is `warning` when any of these hold, and `healthy` otherwise (or `unknown` before any run has completed):

- The last maintenance run failed or completed only partially.
- A run started but never reported completion, and more than an hour has passed since it started (a stuck run).
- Scheduled maintenance is enabled but its cron event is not registered, or the event is overdue.

The specific reasons appear in the `Warnings` row (or the `warnings` array with `--format=json`). Use `wp autoclose cron repair` to resolve a missing or overdue schedule.

## `wp autoclose settings`

Displays the effective site-local configuration, including the schedule (configured in UTC), comment and ping filters, reopen settings, self-ping blocking, revision retention, and notification settings.

```bash
wp autoclose settings
wp autoclose settings --format=json
```

## `wp autoclose run`

Runs the configured comments, pingbacks/trackbacks, and revision maintenance immediately.

```bash
wp autoclose run
wp autoclose run --yes
```

If **Delete revisions** is enabled, the command asks for confirmation because revisions are permanently deleted. Pass `--yes` only for an explicitly unattended run.

The command does not send the scheduled summary email. Its output reports posts whose comments or pings were closed or reopened, revisions deleted, processor outcomes, and any errors.

## `wp autoclose comments` and `wp autoclose pings`

Open or close discussions with explicit, one-off filters. Positional IDs and `--post-ids` select posts; omitting them operates on every matching post.

```bash
wp autoclose comments close --age=90 --post-types=post,page
wp autoclose comments open 1234 5678
wp autoclose pings close --post-ids=1234,5678
wp autoclose pings open --dry-run --format=json
```

Both command groups accept `--age`, `--post-types`, `--exclude-terms`, `--dry-run`, `--sample`, and `--format`. `--exclude-terms` uses term-taxonomy IDs. These operations do not send email or alter AutoClose settings.

When `--post-types` is omitted, the commands target public non-attachment post types that support comments. Pass `--post-types=attachment` or another explicit value to override that scope.

## `wp autoclose revisions`

Preview, prune, or permanently delete revisions. Positional IDs identify parent posts; without IDs, the operation applies to every post.

`prune` follows the same policy as scheduled cleanup: it deletes only revisions beyond the post's retention limit and older than the age cutoff, and never deletes autosaves. `delete` is the explicit delete-all action and ignores both limits.

```bash
wp autoclose revisions preview --format=json
wp autoclose revisions preview --all
wp autoclose revisions prune --dry-run
wp autoclose revisions prune --age=30 --limit=500 --yes
wp autoclose revisions delete 1234 --yes
wp autoclose revisions delete --dry-run --sample=25
```

`--age` overrides the saved age cutoff for that run; `0` ignores age entirely. `--limit` overrides the per-run bound. Output reports the revisions scanned and whether more remain beyond the bound.

Both `prune` and `delete` ask for confirmation unless `--yes` is supplied. The commands are explicit and are not limited by the scheduled **Delete revisions** setting.

## `wp autoclose pingbacks`

Preview or permanently delete pingbacks and trackbacks, optionally limited to parent post IDs.

```bash
wp autoclose pingbacks preview
wp autoclose pingbacks delete 1234 5678 --yes
```

Deletion always asks for confirmation unless `--yes` is supplied.

## `wp autoclose close-date`

Manage the per-post close dates used by the AutoClose metabox. Dates are entered in the site's timezone using `Y-m-dTH:i`.

```bash
wp autoclose close-date list
wp autoclose close-date set 1234 --comments=2026-10-01T09:00
wp autoclose close-date set 1234 --comments=2026-10-01T09:00 --pings=2026-10-01T09:00
wp autoclose close-date clear 1234 --comments
wp autoclose close-date clear 1234
```

`set` schedules future dates and closes immediately when a supplied date is already due. `clear` removes the selected dates and reschedules any remaining date. Both commands support `--dry-run`.

## `wp autoclose cron`

Repair the current site's scheduled maintenance event when scheduled closing is enabled but the event is missing or overdue. This command never changes the enabled/disabled setting.

```bash
wp autoclose cron repair
wp autoclose cron repair --force --format=json
```

Use `--force` to reschedule an existing event as well.

## `wp autoclose run --dry-run`

Previews the configured maintenance without changing content, settings, cron events, run history, or sending email.

```bash
wp autoclose run --dry-run
wp autoclose run --dry-run --sample=25 --format=json
```

The preview reports the effective age cutoff, post-type and exclusion filters, counts, and a bounded sample of matching post IDs. A preview is a snapshot; the content can change before a later real run.

`--sample` accepts 1–100 rows per operation and defaults to 10.

## Output formats and exit codes

All commands accept `--format=table`, `--format=json`, or `--format=csv` where documented. A successful operation includes a successful zero-change operation and exits with code `0`.

| Exit code | Meaning |
| --- | --- |
| `0` | Completed successfully, including no eligible changes. |
| `1` | The command or preview failed and no processor completed successfully. WP-CLI also uses `1` for synopsis errors such as extra positional arguments. |
| `2` | The command completed partially; at least one processor failed. |
| `3` | Invalid command arguments or output format. |

Declining a destructive confirmation follows WP-CLI's convention and exits successfully without making changes. Destructive commands use the same exit codes and require `--yes` for unattended execution. The root `run` command rejects `--sample` unless `--dry-run` is also supplied.
