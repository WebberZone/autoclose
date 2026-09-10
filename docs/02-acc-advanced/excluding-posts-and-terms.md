---
slug: excluding-posts-and-terms
title: "Excluding Posts and Terms from AutoClose"
products: [autoclose]
sections: ["02-acc-advanced"]
tags: [autoclose, exclude, terms]
status: publish
order: 1
---

[AutoClose](https://webberzone.com/plugins/autoclose/) provides several layered mechanisms to keep specific content open: keep-open post IDs, taxonomy term exclusions, per-post-type age overrides, the per-post close-date metabox, and the per-post override. This article covers the bulk-exclusion and per-post-type mechanisms; the metabox is covered in [AutoClose Metabox and Close Dates](autoclose-metabox-and-close-dates.md).

## Keep comments open on these posts/pages

The **Comments → Keep comments on these posts/pages open** field is a comma-separated list of post IDs whose `comment_status` is forced to `open` on every cron run. For example:

```text
188,320,500
```

When the cron fires, the plugin first closes comments on every post older than the configured age in the selected post types, then walks this list and re-opens the listed posts. The keep-open list wins over the age-based close.

## Keep pingbacks/trackbacks open on these posts/pages

The **Pingbacks/Trackbacks → Keep pingbacks/trackbacks on these posts/pages open** field works the same way for `ping_status`. Enter post IDs as a comma-separated list.

## Exclude posts in these categories/tags

The **Comments → Exclude posts in these categories/tags** and **Pingbacks/Trackbacks → Exclude posts in these categories/tags** fields exclude posts that are assigned to any of the chosen terms. The field has Tom-Select autocomplete — start typing a category, tag, or any other public taxonomy term name, and select from the list.

Posts that match any excluded term are skipped entirely; their comment and ping status is not touched. Term exclusions are checked first, before the age-based close, so a post can be old enough to qualify for closing but still be skipped if it is in an excluded term.

The plugin saves the term IDs to a separate `*_term_ids` option when you save the settings, and uses that ID list when the cron runs. If you import settings from another site or migrate, you may need to re-select the terms in the autocomplete so the IDs match the new site's term IDs.

## Age per post type

The **Comments → Age per post type** and **Pingbacks/Trackbacks → Age per post type** settings override the global age cutoff for individual post types, rather than excluding them entirely:

- `-2` — use the global age above (default).
- `-1` — never close this post type.
- `0` or higher — an explicit age in days for this post type, independent of the global setting.

Setting a post type to `-1` is effectively a blanket exclusion for that post type, without needing a term or ID list. A post type left at `-2` still respects the term exclusions and keep-open ID list described above; those checks run before the age (global or per-type) is applied.

## Per-post override via the metabox

The **AutoClose Settings** metabox on the post edit screen exposes **Close comments on** and **Close pingbacks/trackbacks on** date fields. See [AutoClose Metabox and Close Dates](autoclose-metabox-and-close-dates.md) for details. The cron also respects the comment and ping status set on the post itself, so any post whose `comment_status` or `ping_status` is `open` is left alone.

## Excluding a post from the Tools page actions

The one-click **Tools → AutoClose Tools** buttons are global. There is no per-post exclusion for them — clicking **Close comments on all post types** will close comments on every post in the configured post types, including ones in your keep-open list of post IDs only when the keep-open list also runs as part of **Run all scheduled actions now**. The other buttons do not honor the keep-open list. Use them carefully.
