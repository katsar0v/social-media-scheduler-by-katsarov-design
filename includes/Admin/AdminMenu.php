<?php
/**
 * Admin menu and page rendering.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Admin;

use KatsarovDesign\SocialMediaScheduler\Plugin;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use KatsarovDesign\SocialMediaScheduler\Service\MetaOAuthService;
use KatsarovDesign\SocialMediaScheduler\Service\SettingsService;
use KatsarovDesign\SocialMediaScheduler\Service\TikTokOAuthService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminMenu {
	public const PAGE_CALENDAR = 'sms-calendar';
	public const PAGE_COMPOSER = 'sms-new-post';
	public const PAGE_ACCOUNTS = 'sms-accounts';
	public const PAGE_SETTINGS = 'sms-settings';

	/**
	 * @return list<string>
	 */
	public static function page_slugs(): array {
		return array( self::PAGE_CALENDAR, self::PAGE_COMPOSER, self::PAGE_ACCOUNTS, self::PAGE_SETTINGS );
	}

	public static function register(): void {
		add_menu_page(
			__( 'Social Scheduler', 'social-media-scheduler' ),
			__( 'Social Scheduler', 'social-media-scheduler' ),
			Plugin::CAPABILITY,
			self::PAGE_CALENDAR,
			array( self::class, 'render_calendar' ),
			'dashicons-calendar-alt',
			58
		);

		add_submenu_page( self::PAGE_CALENDAR, __( 'Calendar', 'social-media-scheduler' ), __( 'Calendar', 'social-media-scheduler' ), Plugin::CAPABILITY, self::PAGE_CALENDAR, array( self::class, 'render_calendar' ) );
		add_submenu_page( self::PAGE_CALENDAR, __( 'New Post', 'social-media-scheduler' ), __( 'New Post', 'social-media-scheduler' ), Plugin::CAPABILITY, self::PAGE_COMPOSER, array( self::class, 'render_composer' ) );
		add_submenu_page( self::PAGE_CALENDAR, __( 'Accounts', 'social-media-scheduler' ), __( 'Accounts', 'social-media-scheduler' ), Plugin::CAPABILITY, self::PAGE_ACCOUNTS, array( self::class, 'render_accounts' ) );
		add_submenu_page( self::PAGE_CALENDAR, __( 'Settings', 'social-media-scheduler' ), __( 'Settings', 'social-media-scheduler' ), Plugin::CAPABILITY, self::PAGE_SETTINGS, array( self::class, 'render_settings' ) );
	}

	public static function is_plugin_page(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $page, self::page_slugs(), true );
	}

	public static function render_calendar(): void {
		self::render( 'calendar.php', array() );
	}

	public static function render_composer(): void {
		self::render(
			'composer.php',
			array(
				'accounts' => ( new SocialAccountRepository() )->list(),
				'settings' => ( new SettingsService() )->get(),
			)
		);
	}

	public static function render_accounts(): void {
		$error_key   = 'sms_oauth_error_' . get_current_user_id();
		$oauth_error = get_transient( $error_key );
		delete_transient( $error_key );
		$oauth_notice = self::oauth_notice( is_string( $oauth_error ) ? $oauth_error : '' );

		self::render(
			'accounts.php',
			array(
				'accounts'         => ( new SocialAccountRepository() )->list(),
				'metaConfigured'   => ( new MetaOAuthService() )->is_configured(),
				'oauthNotice'      => $oauth_notice,
				'settings'         => ( new SettingsService() )->get(),
				'tiktokConfigured' => ( new TikTokOAuthService() )->is_configured(),
			)
		);
	}

	public static function render_settings(): void {
		self::render(
			'settings.php',
			array(
				'settings' => ( new SettingsService() )->get(),
			)
		);
	}

	/**
	 * @param array<string,mixed> $vars Variables for the view.
	 */
	private static function render( string $view, array $vars ): void {
		$path = SMS_PLUGIN_DIR . 'views/' . $view;
		if ( ! is_readable( $path ) ) {
			wp_die( esc_html__( 'View not found.', 'social-media-scheduler' ) );
		}

		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require $path;
	}

	/**
	 * @return array{type:string,message:string}|null
	 */
	private static function oauth_notice( string $transient_error ): ?array {
		if ( '' !== $transient_error ) {
			return array(
				'type'    => 'error',
				'message' => sanitize_text_field( $transient_error ),
			);
		}

		$status = isset( $_GET['oauthStatus'] ) ? sanitize_key( wp_unslash( $_GET['oauthStatus'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $status, array( 'success', 'error' ), true ) ) {
			return null;
		}

		$provider = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $provider, array( 'meta', 'tiktok' ), true ) ) {
			$provider = 'meta';
		}

		if ( 'success' === $status ) {
			$count = isset( $_GET['count'] ) ? absint( sanitize_text_field( wp_unslash( $_GET['count'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return array(
				'type'    => 'success',
				'message' => self::oauth_success_message( $provider, $count ),
			);
		}

		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array(
			'type'    => 'error',
			'message' => '' !== $message ? $message : self::oauth_error_message( $provider ),
		);
	}

	private static function oauth_success_message( string $provider, int $count ): string {
		if ( 'tiktok' === $provider ) {
			return __( 'TikTok account connected successfully.', 'social-media-scheduler' );
		}

		return $count > 1
			? sprintf(
				/* translators: %d: number of connected Meta accounts. */
				__( '%d Meta accounts connected successfully.', 'social-media-scheduler' ),
				$count
			)
			: __( 'Meta account connected successfully.', 'social-media-scheduler' );
	}

	private static function oauth_error_message( string $provider ): string {
		return 'tiktok' === $provider
			? __( 'Could not connect the TikTok account. Please try again.', 'social-media-scheduler' )
			: __( 'Could not connect the Meta account. Please try again.', 'social-media-scheduler' );
	}
}
