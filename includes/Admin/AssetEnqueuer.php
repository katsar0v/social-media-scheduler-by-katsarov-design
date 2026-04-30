<?php
/**
 * Admin asset registration.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Admin;

use KatsarovDesign\SocialMediaScheduler\Plugin;
use KatsarovDesign\SocialMediaScheduler\Service\SettingsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetEnqueuer {
	public static function enqueue( string $hook_suffix ): void {
		if ( ! AdminMenu::is_plugin_page() ) {
			return;
		}

		wp_enqueue_style( 'sms-admin', SMS_PLUGIN_URL . 'assets/css/admin.css', array(), SMS_PLUGIN_VERSION );

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$settings = ( new SettingsService() )->get();
		$config   = array(
			'root'          => esc_url_raw( rest_url( 'sms/v1/' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'currentUserId' => get_current_user_id(),
			'adminUrl'      => admin_url( 'admin.php' ),
			'settings'      => array(
				'timezone'          => (string) ( $settings['timezone'] ?? '' ),
				'defaultPlatform'   => (string) ( $settings['defaultPlatform'] ?? '' ),
				'defaultPostStatus' => (string) ( $settings['defaultPostStatus'] ?? '' ),
				'calendarWeekStart' => (int) ( $settings['calendarWeekStart'] ?? 1 ),
			),
		);

		if ( AdminMenu::PAGE_CALENDAR === $page ) {
			self::enqueue_script( 'sms-calendar', 'assets/js/calendar.js', array( 'wp-i18n' ), 'smsCalendar', $config );
		}

		if ( AdminMenu::PAGE_COMPOSER === $page ) {
			wp_enqueue_media();
			self::enqueue_script( 'sms-composer', 'assets/js/composer.js', array( 'wp-i18n', 'media-views' ), 'smsComposer', $config );
		}

		if ( AdminMenu::PAGE_ACCOUNTS === $page ) {
			self::enqueue_script( 'sms-accounts', 'assets/js/accounts.js', array( 'wp-i18n' ), 'smsAccounts', $config );
		}

		if ( AdminMenu::PAGE_SETTINGS === $page ) {
			self::enqueue_script( 'sms-settings', 'assets/js/settings.js', array( 'wp-i18n' ), 'smsSettings', $config );
		}
	}

	/**
	 * @param list<string>         $deps Script dependencies.
	 * @param array<string,mixed>  $config Localized config.
	 */
	private static function enqueue_script( string $handle, string $path, array $deps, string $object_name, array $config ): void {
		wp_enqueue_script( $handle, SMS_PLUGIN_URL . $path, $deps, SMS_PLUGIN_VERSION, true );
		wp_localize_script( $handle, $object_name, $config );
		wp_set_script_translations( $handle, Plugin::TEXT_DOMAIN, SMS_PLUGIN_DIR . 'languages' );
	}
}
