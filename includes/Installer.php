<?php
/**
 * Database and capability installer.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and upgrades plugin storage.
 */
final class Installer {
	public const DB_VERSION                 = '1.0.0';
	public const OPTION_DB_VERSION          = 'sms_db_version';
	public const OPTION_SETTINGS            = 'sms_settings';
	public const OPTION_REMOVE_ON_UNINSTALL = 'sms_remove_on_uninstall';

	private const TABLES = array(
		'sms_post',
		'sms_post_media',
		'sms_social_account',
		'sms_publish_result',
		'sms_external_post',
	);

	public static function install(): void {
		self::create_tables();
		self::ensure_settings_option();
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade(): void {
		$current_version = (string) get_option( self::OPTION_DB_VERSION, '0' );

		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$post_table      = self::table_name( 'sms_post' );
		$media_table     = self::table_name( 'sms_post_media' );
		$account_table   = self::table_name( 'sms_social_account' );
		$result_table    = self::table_name( 'sms_publish_result' );
		$external_table  = self::table_name( 'sms_external_post' );

		dbDelta(
			"CREATE TABLE {$post_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) DEFAULT NULL,
				caption longtext NOT NULL,
				platform varchar(32) NOT NULL,
				social_account_id bigint(20) unsigned DEFAULT NULL,
				scheduled_at datetime NOT NULL,
				status varchar(32) NOT NULL DEFAULT 'DRAFT',
				is_story tinyint(1) NOT NULL DEFAULT 0,
				notes longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY platform (platform),
				KEY status (status),
				KEY scheduled_at (scheduled_at),
				KEY social_account_id (social_account_id)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$media_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				attachment_id bigint(20) unsigned NOT NULL,
				position int(11) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY post_attachment (post_id, attachment_id),
				KEY post_position (post_id, position),
				KEY attachment_id (attachment_id)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$account_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				platform varchar(32) NOT NULL,
				provider_user_id varchar(191) NOT NULL,
				account_name varchar(255) NOT NULL,
				access_token longtext NOT NULL,
				refresh_token longtext NOT NULL,
				token_expires_at datetime DEFAULT NULL,
				scopes text NOT NULL,
				connected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				metadata longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY platform_provider_user (platform, provider_user_id),
				KEY platform (platform),
				KEY token_expires_at (token_expires_at)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$result_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				platform varchar(32) NOT NULL,
				status varchar(32) NOT NULL DEFAULT 'pending',
				platform_post_id varchar(255) NOT NULL DEFAULT '',
				permalink text,
				published_at datetime DEFAULT NULL,
				error longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY post_id (post_id),
				KEY platform (platform),
				KEY status (status),
				KEY published_at (published_at)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$external_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				platform varchar(32) NOT NULL,
				account_id bigint(20) unsigned NOT NULL,
				platform_post_id varchar(255) NOT NULL,
				content longtext NOT NULL,
				media_url text NOT NULL,
				permalink text NOT NULL,
				published_at datetime NOT NULL,
				metadata longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY platform_post (platform, platform_post_id),
				KEY account_id (account_id),
				KEY platform (platform),
				KEY published_at (published_at)
			) {$charset_collate};"
		);
	}

	public static function ensure_settings_option(): void {
		if ( false === get_option( self::OPTION_SETTINGS, false ) ) {
			add_option( self::OPTION_SETTINGS, self::default_settings(), '', false );
		}

		if ( false === get_option( self::OPTION_REMOVE_ON_UNINSTALL, false ) ) {
			add_option( self::OPTION_REMOVE_ON_UNINSTALL, false, '', false );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function default_settings(): array {
		return array(
			'timezone'            => 'Europe/Sofia',
			'defaultPlatform'     => 'instagram',
			'defaultPostStatus'   => 'DRAFT',
			'brandHashtags'       => '',
			'calendarWeekStart'   => 1,
			'metaAppId'           => '',
			'metaAppSecret'       => '',
			'metaRedirectUri'     => '',
			'tiktokClientKey'     => '',
			'tiktokClientSecret'  => '',
			'tiktokRedirectUri'   => '',
			'baseUrl'             => home_url(),
			'removeOnUninstall'   => false,
		);
	}

	public static function grant_capabilities(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( null !== $role ) {
				$role->add_cap( Plugin::CAPABILITY );
			}
		}
	}

	public static function remove_capabilities(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( null !== $role ) {
				$role->remove_cap( Plugin::CAPABILITY );
			}
		}
	}

	public static function uninstall(): void {
		self::remove_capabilities();

		if ( ! self::should_remove_on_uninstall() ) {
			return;
		}

		self::drop_tables();
		self::delete_options();
	}

	public static function should_remove_on_uninstall(): bool {
		$settings = get_option( self::OPTION_SETTINGS, array() );
		$setting  = is_array( $settings ) ? (bool) ( $settings['removeOnUninstall'] ?? false ) : false;

		return (bool) get_option( self::OPTION_REMOVE_ON_UNINSTALL, false ) || $setting;
	}

	public static function drop_tables(): void {
		global $wpdb;

		foreach ( self::TABLES as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_name( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	public static function delete_options(): void {
		delete_option( self::OPTION_SETTINGS );
		delete_option( self::OPTION_DB_VERSION );
		delete_option( self::OPTION_REMOVE_ON_UNINSTALL );
		delete_transient( 'sms_publish_lock' );
		delete_transient( 'sms_external_posts_cache' );
	}

	public static function table_name( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}
}
