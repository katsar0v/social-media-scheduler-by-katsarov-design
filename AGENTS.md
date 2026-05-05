# Agent Guide: Social Media Scheduler

## Project Overview
A WordPress plugin for scheduling and publishing posts to social media platforms (Meta, TikTok). 
Uses a modern PHP architecture with PSR-4 autoloading, strict types, and repository/service patterns.

## Developer Commands
- **Container status**: `docker ps -a` (Use this first to confirm local containers are up).
- **Testing**: `docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php vendor/bin/phpunit -c phpunit.xml.dist` (Runs PHPUnit in the `php` container).
- **Linting**: `docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php composer lint:phpcs` (Checks WordPress coding standards in the `php` container).
- **Syntax**: `docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php composer lint:syntax` (Runs quick PHP syntax checks in the `php` container).
- **Translations**: `docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php wp i18n make-pot . languages/social-media-scheduler.pot --exclude=vendor,node_modules --allow-root` followed by `docker exec -w /var/www/html/wp-content/plugins/social-media-scheduler php wp i18n make-json languages --no-purge --allow-root`.

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
- **Framework**: Uses `WP_UnitTestCase` in the local Docker-based WordPress test setup.
- **Base Case**: Extend `SmsTestCase` for tests requiring database interaction; it handles table truncation between tests.
- **Mocking**: Platform APIs (Meta/TikTok) should be mocked in services or use repository-level tests.

## Environment Gotchas
- The plugin runs in a local Docker Compose setup.
- Use `docker ps -a` to verify container names before running Docker exec commands.
- **Autoloader**: Run `composer dump-autoload` if classes are not found.
- **Cron**: Recommends `DISABLE_WP_CRON` in production with a system cron job to ensure timely publishing. An admin notice appears if WP-Cron is disabled but the notice isn't suppressed.
- **Database**: Custom tables are prefixed with `$wpdb->prefix`. Use `Installer::table_name('table_name')` to get the full name.
- **Uninstall**: Tables are only dropped if `sms_remove_on_uninstall` is enabled.
- **Translations**: Handled via `wp-i18n` and JSON files for JS assets.
