---
slug: installing-autoclose
title: "Installing AutoClose"
products: [autoclose]
sections: ["01-acc-getting-started"]
tags: [autoclose, installation]
status: publish
order: 0
featured_image: "https://webberzone.com/wp-content/uploads/2020/05/Install-AutoClose-1.png"
---

[AutoClose](https://webberzone.com/plugins/autoclose/) is hosted on WordPress.org. Installing it follows the standard WordPress plugin flow.

## WordPress install (The easy way)

1. Navigate to **Plugins** within your WordPress admin area.
2. Click **Add new** and in the search box enter `AutoClose`.
3. Find the plugin in the list (usually the first result) and click **Install Now**.
4. Activate or Network activate the plugin under the **Plugins** screen.

![Install AutoClose](https://webberzone.com/wp-content/uploads/2020/05/Install-AutoClose-1.png)

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
