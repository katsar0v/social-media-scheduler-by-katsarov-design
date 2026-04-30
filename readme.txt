=== Social Media Scheduler ===
Contributors: katsarovdesign
Tags: social media, scheduler, calendar
Requires at least: 6.4
Requires PHP: 8.1
Tested up to: 6.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial social media scheduling for Sebeotkrivatel.

== Description ==

Social Media Scheduler adds a WordPress admin calendar for planning and publishing Sebeotkrivatel social posts across Instagram, Facebook, and TikTok.

This initial migration scaffold creates the plugin bootstrap, custom database tables, settings option, cron registration, capability registration, and token encryption foundation.

== Installation ==

1. Upload the plugin directory to `wp-content/plugins/`.
2. Run `composer dump-autoload` from the plugin directory.
3. Activate Social Media Scheduler in WordPress.

== Scheduled Publishing ==

The plugin registers WP-Cron events for deferred publishing, token refresh, and external post sync. For production publishing, use a real system cron job so scheduled posts are not delayed by low site traffic.

Recommended setup:

1. Add `define( 'DISABLE_WP_CRON', true );` to `wp-config.php`.
2. Add a server cron entry that calls WordPress every minute:

`* * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1`

Replace `https://example.com` with the site URL. When WP-Cron is disabled, Social Scheduler shows an admin notice on plugin pages reminding editors that the system cron must be active.

== Internationalization ==

All user-facing PHP strings must use WordPress translation helpers such as `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()`, `_x()`, or translated `printf()` calls. JavaScript strings must use `@wordpress/i18n`.

To update translation templates, run:

`composer i18n:pot`

To generate JavaScript translation JSON files after updating `.po` files, run:

`composer i18n:json`

Translation contributors should submit `.po` files created from `languages/social-media-scheduler.pot`. Do not edit generated `.json` files by hand.

== Uninstall ==

The plugin removes custom tables and stored options only when remove-on-uninstall is enabled in plugin settings or the `sms_remove_on_uninstall` option is truthy. Role capabilities are removed during uninstall.

== Changelog ==

= 0.1.0 =
* Initial WordPress plugin scaffold, installer, settings storage, cron wiring, and token encryption.
