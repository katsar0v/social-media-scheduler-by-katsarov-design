# Agent Guide: Social Media Scheduler

## Project Overview
A WordPress plugin for scheduling and publishing posts to social media platforms (Meta, TikTok). 
Uses a modern PHP architecture with PSR-4 autoloading, strict types, and repository/service patterns.

## Developer Commands
- **Environment**: `npm run wp-env:start` (Starts a local WP instance at http://localhost:8889).
- **Testing**: `npm run test:phpunit` (Runs PHPUnit tests within the `wp-env` container).
- **Linting**: `composer lint:phpcs` (Checks WordPress coding standards).
- **Syntax**: `composer lint:syntax` (Quick PHP syntax check).
- **Translations**: `npm run i18n:pot` followed by `npm run i18n:json` to generate translation files.

## Architecture & Conventions
- **Autoloading**: PSR-4 `KatsarovDesign\SocialMediaScheduler\` maps to `./includes/`.
- **Database**: Custom tables (`sms_post`, `sms_social_account`, etc.) are managed by `./includes/Installer.php`.
- **Logic Layers**:
  - `./includes/Repository/`: Custom SQL queries and data persistence.
  - `./includes/Service/`: Business logic, platform integrations (OAuth, Media, Publishing).
  - `./includes/Rest/`: WP REST API controllers.
  - `./includes/Cron/`: WP Cron handlers.
  - `./includes/Domain/`: Enums, value objects, and error definitions.
- **Views**: PHP templates in `./views/` are loaded by admin pages.
- **Assets**: JavaScript logic in `./assets/js/` is split by functional area (accounts, calendar, composer, settings).
- **Strict Types**: Always use `declare(strict_types=1);` in new PHP files.

## Testing Quirks
- **Framework**: Uses `WP_UnitTestCase` via `wp-env`.
- **Base Case**: Extend `SmsTestCase` for tests requiring database interaction; it handles table truncation between tests.
- **Mocking**: Platform APIs (Meta/TikTok) should be mocked in services or use repository-level tests.

## Environment Gotchas
- The plugin uses `./.wp-env.json` to configure the development environment.
- **Autoloader**: Run `composer dump-autoload` if classes are not found.
- **Cron**: Recommends `DISABLE_WP_CRON` in production with a system cron job to ensure timely publishing. An admin notice appears if WP-Cron is disabled but the notice isn't suppressed.
- **Database**: Custom tables are prefixed with `$wpdb->prefix`. Use `Installer::table_name('table_name')` to get the full name.
- **Uninstall**: Tables are only dropped if `sms_remove_on_uninstall` is enabled.
- **Translations**: Handled via `wp-i18n` and JSON files for JS assets.
