<?php
/**
 * Service layer smoke test for wp eval-file.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

use KatsarovDesign\SocialMediaScheduler\Installer;
use KatsarovDesign\SocialMediaScheduler\Repository\ExternalPostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use KatsarovDesign\SocialMediaScheduler\Service\PostService;
use KatsarovDesign\SocialMediaScheduler\Service\PostValidationException;
use KatsarovDesign\SocialMediaScheduler\Service\PublishService;
use KatsarovDesign\SocialMediaScheduler\Service\SettingsService;
use KatsarovDesign\SocialMediaScheduler\Service\SettingsValidationException;
use KatsarovDesign\SocialMediaScheduler\Service\TokenRefreshService;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This test must be run through WP-CLI: wp eval-file tests/ServiceSmokeTest.php\n" );
	exit( 1 );
}

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$settings_repository_backup = get_option( Installer::OPTION_SETTINGS, array() );

$account_repository = new SocialAccountRepository();
$post_repository    = new PostRepository();
$external_repository = new ExternalPostRepository();
$post_service       = new PostService( $post_repository, $account_repository );
$settings_service   = new SettingsService();
$publish_service    = new PublishService( $post_repository, $account_repository );
$token_service      = new TokenRefreshService( $account_repository );

$meta_account_id   = null;
$tiktok_account_id = null;
$meta_post_id      = null;
$tiktok_post_id    = null;

try {
	$settings = $settings_service->update(
		array(
			'timezone'          => 'Europe/Sofia',
			'defaultPlatform'   => 'instagram',
			'defaultPostStatus' => 'DRAFT',
			'brandHashtags'     => ' #service-smoke ',
			'calendarWeekStart' => 1,
		)
	);
	$assert( '#service-smoke' === $settings['brandHashtags'], 'SettingsService did not trim brandHashtags.' );

	try {
		$settings_service->update( array( 'timezone' => 'Mars/Olympus' ) );
		throw new RuntimeException( 'Invalid timezone was accepted.' );
	} catch ( SettingsValidationException ) {
		// Expected.
	}

	$meta_account = $account_repository->upsert(
		array(
			'platform'       => 'meta',
			'providerUserId' => 'service-meta-' . wp_generate_uuid4(),
			'accountName'    => 'Service Meta Account',
			'accessToken'    => 'meta-access-' . wp_generate_uuid4(),
			'tokenExpiresAt' => null,
			'scopes'         => 'pages_manage_posts,instagram_basic,instagram_content_publish',
			'metadata'       => wp_json_encode(
				array(
					'fbPageId'            => 'page-service-smoke',
					'igBusinessAccountId' => 'ig-service-smoke',
				)
			),
		)
	);
	$meta_account_id = (int) $meta_account['id'];

	$tiktok_account = $account_repository->upsert(
		array(
			'platform'       => 'tiktok',
			'providerUserId' => 'service-tiktok-' . wp_generate_uuid4(),
			'accountName'    => 'Service TikTok Account',
			'accessToken'    => 'tiktok-access-' . wp_generate_uuid4(),
			'refreshToken'   => 'tiktok-refresh-' . wp_generate_uuid4(),
			'tokenExpiresAt' => null,
			'scopes'         => 'video.publish',
			'metadata'       => '{"smoke":true}',
		)
	);
	$tiktok_account_id = (int) $tiktok_account['id'];

	try {
		$post_service->create(
			array(
				'caption'         => '',
				'platform'        => 'instagram',
				'socialAccountId' => $meta_account_id,
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			)
		);
		throw new RuntimeException( 'Empty non-story caption was accepted.' );
	} catch ( PostValidationException ) {
		// Expected.
	}

	$meta_post = $post_service->create(
		array(
			'title'           => 'Service Meta Post',
			'caption'         => 'Service smoke caption',
			'platform'        => 'instagram',
			'socialAccountId' => $meta_account_id,
			'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			'status'          => 'PUBLISHED',
			'notes'           => 'Service smoke.',
		)
	);
	$meta_post_id = (int) $meta_post['id'];
	$assert( 'SCHEDULED' === $meta_post['status'], 'Future PUBLISHED post did not resolve to SCHEDULED.' );

	$meta_results = $publish_service->publish_to_meta(
		array(
			'postId'          => $meta_post_id,
			'targetPlatforms' => array( 'instagram' ),
		)
	);
	$assert( true === $meta_results[0]['isScheduled'], 'Future Instagram publish was not deferred.' );
	$assert( 'SCHEDULED' === $post_repository->find_by_id( $meta_post_id )['status'], 'Deferred Instagram publish did not keep post scheduled.' );

	$tiktok_post = $post_service->create(
		array(
			'title'           => 'Service TikTok Post',
			'caption'         => 'TikTok smoke caption',
			'platform'        => 'tiktok',
			'socialAccountId' => $tiktok_account_id,
			'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			'status'          => 'PUBLISHED',
		)
	);
	$tiktok_post_id = (int) $tiktok_post['id'];
	$tiktok_result  = $publish_service->publish_to_tiktok( array( 'postId' => $tiktok_post_id ) );
	$assert( '' === $tiktok_result['platformPostId'], 'Future TikTok publish should be deferred without a platform ID.' );
	$assert( 'SCHEDULED' === $post_repository->find_by_id( $tiktok_post_id )['status'], 'Deferred TikTok publish did not keep post scheduled.' );

	$refresh = $token_service->refresh_due_accounts();
	$assert( $refresh['checked'] >= 2, 'TokenRefreshService did not inspect the created accounts.' );
} finally {
	if ( null !== $meta_post_id ) {
		$post_repository->delete( $meta_post_id );
	}
	if ( null !== $tiktok_post_id ) {
		$post_repository->delete( $tiktok_post_id );
	}
	if ( null !== $meta_account_id ) {
		$external_repository->delete_by_account_id( $meta_account_id );
		$account_repository->delete( $meta_account_id );
	}
	if ( null !== $tiktok_account_id ) {
		$external_repository->delete_by_account_id( $tiktok_account_id );
		$account_repository->delete( $tiktok_account_id );
	}
	if ( is_array( $settings_repository_backup ) ) {
		update_option( Installer::OPTION_SETTINGS, $settings_repository_backup, false );
	}
}

echo "Service smoke test passed.\n";
