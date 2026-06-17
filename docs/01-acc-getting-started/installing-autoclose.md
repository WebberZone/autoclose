---
slug: installing-autoclose
title: "Installing AutoClose"
products: [autoclose]
sections: [01-acc-getting-started]
tags: [autoclose,installation]
status: publish
order: 0
---

[AutoClose](https://webberzone.com/plugins/autoclose/) is hosted on WordPress.org. Installing it follows the standard WordPress plugin flow.

## WordPress install (The easy way)

1. Navigate to **Plugins** within your WordPress admin area.
2. Click **Add new** and in the search box enter `AutoClose`.
3. Find the plugin in the list (usually the first result) and click **Install Now**.
4. Activate or Network activate the plugin under the **Plugins** screen.

<figure class="wp-block-image size-large">
<img src="https://webberzone.com/wp-content/uploads/2020/05/Install-AutoClose-1.png" class="wp-image-215" loading="lazy" decoding="async" srcset="https://webberzone.com/wp-content/uploads/2020/05/Install-AutoClose-1.png 624w, https://webberzone.com/wp-content/uploads/2020/05/Install-AutoClose-1-300x89.png 300w" sizes="auto, (max-width: 624px) 100vw, 624px" width="624" height="185" alt="Install AutoClose" />
<figcaption>Install AutoClose</figcaption>
</figure>

## Manual install

1. Download the plugin from [webberzone.com](https://webberzone.com/plugins/autoclose/).
2. Extract the contents of `autoclose.zip` to the `wp-content/plugins/` folder. You should get a folder named `autoclose`.
3. Activate or Network activate the plugin under the **Plugins** screen.

## Installing via WP CLI

If you use [WP-CLI](http://wp-cli.org/), install and activate the plugin with:

```bash
wp plugin install autoclose --activate
```

To network activate on a multisite install:

```bash
wp plugin install autoclose --activate-network
```

## Next steps

After activation, open **Settings → AutoClose** to configure the cron schedule, the comment and pingback close ages, and the revision limits. See [AutoClose Settings](autoclose-settings.md) for a full reference.
