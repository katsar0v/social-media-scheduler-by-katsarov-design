<h1 align="center">Social Media Scheduler</h1>

<p align="center">
  Editorial social media scheduling for WordPress, built for teams that plan content in advance and publish across Meta and TikTok from one clean admin workspace.
</p>

<p align="center">
  <img alt="Version 0.1.0" src="https://img.shields.io/badge/version-0.1.0-1f6feb?style=for-the-badge">
  <img alt="WordPress 6.4+" src="https://img.shields.io/badge/WordPress-6.4%2B-21759b?style=for-the-badge&logo=wordpress&logoColor=white">
  <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="License GPL-2.0-or-later" src="https://img.shields.io/badge/license-GPL--2.0--or--later-0f766e?style=for-the-badge">
</p>

<p align="center">
  <strong>Instagram</strong> | <strong>Facebook</strong> | <strong>TikTok</strong>
</p>

## Overview

Social Media Scheduler adds a dedicated WordPress admin area for planning, composing, scheduling, and publishing social content. It includes an editorial calendar, connected social accounts, OAuth configuration, REST APIs, API keys, cron-based publishing, token refresh, external post sync, and translation support.

The plugin is built as a modern WordPress plugin with strict PHP types, PSR-4-style class organization, custom database tables, repository and service layers, and PHPUnit coverage for core behavior.

## Highlights

| Area | What it gives you |
| --- | --- |
| Editorial calendar | Month view with status filters for drafts, scheduled posts, published posts, and failures. |
| Post composer | Caption, destination account, platform, story mode, schedule time, status, notes, and media selection. |
| Social accounts | Meta and TikTok OAuth flows with account connection and disconnect controls. |
| Scheduled publishing | WP-Cron events publish due posts, refresh tokens, and synchronize external posts. |
| Platform support | Instagram, Facebook, and TikTok with platform-aware validation rules. |
| REST API | `sms/v1` endpoints for posts, media, settings, publishing, accounts, external posts, and API keys. |
| API keys | Hashed API keys with status, permissions, last-used tracking, and one-time plaintext display. |
| Security | Custom capability, nonce-protected admin REST calls, API key authentication, and encrypted OAuth tokens. |
| Internationalization | PHP translation helpers, JavaScript translation setup, POT generation, and Bulgarian translation files. |

## Requirements

| Requirement | Version |
| --- | --- |
| WordPress | 6.4 or newer |
| PHP | 8.1 or newer |
| PHP extensions | OpenSSL for OAuth token encryption |
| Permissions | Administrator or Editor role after activation |
| Social apps | Meta and TikTok developer apps for real publishing |

## Supported Platforms

| Platform | Supported content | Notes |
| --- | --- | --- |
| Instagram | Feed posts and stories | Instagram image posts require JPEG or PNG media. Stories require video media. |
| Facebook | Page posts and stories | Future Facebook feed posts can be scheduled through Meta where supported. Stories publish when due. |
| TikTok | Video posts | TikTok is video-only. Future posts are queued and published when due. |

## Admin Screens

| Screen | Slug | Purpose |
| --- | --- | --- |
| Calendar | `sms-calendar` | Review the month, filter by status, and jump to new post creation. |
| New Post | `sms-new-post` | Compose posts, select connected accounts, attach media, and publish now. |
| Accounts | `sms-accounts` | Connect or disconnect Meta and TikTok accounts. |
| Settings | `sms-settings` | Configure editorial defaults, OAuth credentials, uninstall behavior, and API keys. |

## Installation

Place the plugin directory inside WordPress:

```bash
wp-content/plugins/social-media-scheduler
```

Install the PHP autoloader and dependencies when installing from source:

```bash
composer install --no-dev
```

Activate the plugin in WordPress:

```bash
wp plugin activate social-media-scheduler
```

You can also activate it from `Plugins` in the WordPress admin.

## First-Time Setup

1. Open `Calendar` in the WordPress admin sidebar.
2. Go to `Settings` and choose the timezone, default platform, and calendar week start.
3. Add the Meta App ID and Meta App Secret if you publish to Facebook or Instagram.
4. Add the TikTok Client Key, TikTok Client Secret, and TikTok Redirect URI if you publish to TikTok.
5. Go to `Accounts` and connect the required Meta and TikTok accounts.
6. Go to `New Post`, choose a connected account, attach media, and save or publish.

## OAuth Redirect URLs

Use these URLs in the matching developer app settings. Replace `https://example.com` with the production site URL.

| Provider | Redirect URL |
| --- | --- |
| Meta | `https://example.com/wp-json/sms/v1/auth/meta/callback` |
| TikTok | `https://example.com/wp-json/sms/v1/auth/tiktok/callback` |

The Meta redirect URL is generated from the REST callback. The TikTok redirect URL can also be stored in the plugin settings.

## Production Cron

WordPress traffic-based cron can delay scheduled social publishing on quiet sites. For production, use a real system cron job and disable the traffic trigger.

Add this to `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

Run WordPress cron every minute from the server:

```cron
* * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

The plugin registers these events:

| Event | Interval | Purpose |
| --- | --- | --- |
| `sms_publish_tick` | Every minute | Publish due Instagram, Facebook story, and TikTok posts. |
| `sms_token_refresh` | Hourly | Refresh connected account tokens when needed. |
| `sms_external_posts_refresh` | Every six hours | Sync external posts from connected platforms. |

## REST API

All endpoints live under:

```text
/wp-json/sms/v1
```

Admin requests require an `X-WP-Nonce` header and the `manage_social_scheduler` capability. External integrations can use an API key with the `X-API-KEY` header.

```bash
curl -H "X-API-KEY: your-api-key" \
  https://example.com/wp-json/sms/v1/posts
```

| Methods | Endpoint | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/posts` | List and create scheduled social posts. |
| `GET`, `PUT`, `PATCH`, `DELETE` | `/posts/{id}` | Read, update, or delete a post. |
| `POST` | `/posts/{id}/media` | Attach WordPress media to a post. |
| `POST` | `/posts/{id}/media/reorder` | Reorder attached media. |
| `DELETE` | `/posts/{postId}/media/{mediaId}` | Remove media from a post. |
| `GET`, `PUT`, `PATCH` | `/settings` | Read or update plugin settings. |
| `DELETE` | `/media/{id}` | Delete plugin-managed media records. |
| `POST` | `/publish/meta` | Publish or schedule a post through Meta. |
| `POST` | `/publish/tiktok` | Publish or schedule a TikTok post. |
| `GET` | `/publish/{postId}/results` | Read publish results for a post. |
| `GET` | `/external-posts` | List synced external posts. |
| `POST` | `/external-posts/refresh` | Refresh external posts immediately. |
| `GET` | `/auth/accounts` | List connected social accounts. |
| `DELETE` | `/auth/accounts/{id}` | Disconnect a social account. |
| `GET` | `/auth/meta/callback` | Meta OAuth callback. |
| `GET` | `/auth/tiktok/callback` | TikTok OAuth callback. |
| `GET`, `POST` | `/api-keys` | List and create API keys. |
| `GET`, `PUT`, `PATCH`, `DELETE` | `/api-keys/{id}` | Read, update, or delete an API key. |

## API Key Permissions

| Permission | Grants access to |
| --- | --- |
| `posts:read` | Read posts and publish results. |
| `posts:write` | Create and update posts and media. |
| `posts:delete` | Delete posts. |
| `publish:meta` | Publish through Meta endpoints. |
| `publish:tiktok` | Publish through TikTok endpoints. |
| `accounts:read` | Read connected accounts. |
| `accounts:write` | Disconnect accounts. |
| `api_keys:read` | Read API keys. |
| `api_keys:write` | Create, update, and delete API keys. |
| `all` | Full API access. |

## Post Lifecycle

Posts can move through these statuses:

```text
DRAFT -> IN_REVIEW -> APPROVED -> SCHEDULED -> PUBLISHED
```

Additional terminal or recovery statuses are available:

```text
FAILED, CANCELLED
```

When a post is requested as `PUBLISHED` with a future `scheduledAt` date, the plugin stores it as `SCHEDULED` and lets cron publish it when due.

## Data Storage

The plugin creates custom tables using the active WordPress table prefix.

| Table | Purpose |
| --- | --- |
| `sms_api_key` | API key records and hashed credentials. |
| `sms_post` | Scheduled post content, platform, account, status, and timing. |
| `sms_post_media` | Attached WordPress media and ordering. |
| `sms_social_account` | Connected Meta and TikTok accounts with encrypted tokens. |
| `sms_publish_result` | Platform publish status, IDs, permalinks, and errors. |
| `sms_external_post` | Synced posts that were not created by this scheduler. |

The plugin stores settings in `sms_settings`, database versioning in `sms_db_version`, and uninstall preference in `sms_remove_on_uninstall`.

## Security Model

| Layer | Implementation |
| --- | --- |
| Admin capability | `manage_social_scheduler`, granted to administrators and editors on activation. |
| Admin REST requests | WordPress REST nonce plus capability check. |
| Integration requests | `X-API-KEY` authentication with per-key permissions. |
| OAuth tokens | Recoverable AES-256-CBC encryption using a key derived from WordPress auth hashing. |
| API key storage | Plaintext keys are shown once, then stored as password hashes. |
| OAuth state | Temporary nonce-like state values stored in transients. |

## Development

Install development dependencies:

```bash
composer install
```

Check local Docker containers first:

```bash
docker ps -a
```

Run PHPUnit in the WordPress test container:

```bash
docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php vendor/bin/phpunit -c phpunit.xml.dist
```

Run PHP syntax checks:

```bash
docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php composer lint:syntax
```

Run WordPress coding standards:

```bash
docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php composer lint:phpcs
```

There is no frontend build step. Admin CSS and JavaScript live directly in `assets/css` and `assets/js`.

## Architecture

```text
social-media-scheduler.php        Plugin bootstrap and activation hooks
includes/Admin/                  Admin menu, assets, and notices
includes/Cron/                   Scheduled event registration and handlers
includes/Domain/                 Enums and domain value objects
includes/Repository/             SQL persistence layer
includes/Rest/                   REST controllers and routing
includes/Service/                Business logic, OAuth, media, publishing
views/                           WordPress admin templates
assets/css/                      Admin styles
assets/js/                       Admin screens and interactions
tests/                           Smoke tests and PHPUnit test suite
languages/                       POT, PO, MO, and JS translation files
```

## Internationalization

Generate the translation template:

```bash
docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php wp i18n make-pot . languages/social-media-scheduler.pot --exclude=vendor,node_modules --allow-root
```

Generate JavaScript translation JSON files after updating `.po` files:

```bash
docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php wp i18n make-json languages --no-purge --allow-root
```

Do not edit generated JSON translation files by hand.

## Uninstall Behavior

By default, uninstalling the plugin keeps custom tables and settings so data is not removed accidentally.

Enable `Remove plugin data during uninstall` in settings if you want uninstall to drop plugin tables, delete stored options, delete plugin transients, and remove plugin capabilities.

## License

Social Media Scheduler is licensed under `GPL-2.0-or-later`.

Copyright (C) 2026 Katsarov Design.
