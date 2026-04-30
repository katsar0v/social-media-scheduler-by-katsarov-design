<?php
/**
 * Shared PHPUnit helpers.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Installer;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;

abstract class SmsTestCase extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		Installer::install();
		Installer::grant_capabilities();
		$this->clear_sms_data();
	}

	public function tear_down(): void {
		$this->clear_sms_data();
		delete_transient( 'sms_publish_lock' );
		parent::tear_down();
	}

	protected function clear_sms_data(): void {
		global $wpdb;

		foreach ( array( 'sms_post_media', 'sms_publish_result', 'sms_external_post', 'sms_post', 'sms_social_account' ) as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . Installer::table_name( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function create_meta_account(): array {
		return ( new SocialAccountRepository() )->upsert(
			array(
				'platform'       => 'meta',
				'providerUserId' => 'phpunit-meta-' . wp_generate_uuid4(),
				'accountName'    => 'PHPUnit Meta Account',
				'accessToken'    => 'meta-access-' . wp_generate_uuid4(),
				'tokenExpiresAt' => null,
				'scopes'         => 'pages_manage_posts,instagram_basic,instagram_content_publish',
				'metadata'       => wp_json_encode(
					array(
						'fbPageId'            => 'phpunit-page',
						'igBusinessAccountId' => 'phpunit-ig',
					)
				),
			)
		);
	}
}
