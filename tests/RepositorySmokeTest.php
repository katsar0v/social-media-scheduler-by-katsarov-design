<?php
/**
 * Repository smoke test for wp eval-file.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

use KatsarovDesign\SocialMediaScheduler\Domain\Platform;
use KatsarovDesign\SocialMediaScheduler\Domain\PostStatus;
use KatsarovDesign\SocialMediaScheduler\Installer;
use KatsarovDesign\SocialMediaScheduler\Repository\ExternalPostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PostMediaRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PublishResultRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SettingsRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This test must be run through WP-CLI: wp eval-file tests/RepositorySmokeTest.php\n" );
	exit( 1 );
}

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert( PostStatus::can_transition( 'DRAFT', 'SCHEDULED' ), 'Expected DRAFT to transition to SCHEDULED.' );
$assert( ! PostStatus::can_transition( 'PUBLISHED', 'DRAFT' ), 'Expected PUBLISHED to reject DRAFT transition.' );
$assert( Platform::is_valid( 'instagram' ), 'Expected instagram to be a valid platform.' );
$assert( Platform::is_video_only( 'tiktok' ), 'Expected TikTok to be video-only.' );

global $wpdb;

$settings_repository = new SettingsRepository();
$account_repository  = new SocialAccountRepository();
$post_repository     = new PostRepository();
$media_repository    = new PostMediaRepository();
$result_repository   = new PublishResultRepository();
$external_repository = new ExternalPostRepository();

$original_settings = get_option( Installer::OPTION_SETTINGS, array() );
$created_post_id   = null;
$created_account_id = null;

try {
	$settings = $settings_repository->update(
		array(
			'brandHashtags'     => '#phase3-smoke',
			'defaultPlatform'   => 'facebook',
			'calendarWeekStart' => 0,
		)
	);
	$assert( '#phase3-smoke' === $settings['brandHashtags'], 'Settings repository did not persist brand hashtags.' );

	$provider_user_id = 'phase3-' . wp_generate_uuid4();
	$access_token     = 'access-' . wp_generate_uuid4();
	$refresh_token    = 'refresh-' . wp_generate_uuid4();
	$account          = $account_repository->upsert(
		array(
			'platform'       => 'meta',
			'providerUserId' => $provider_user_id,
			'accountName'    => 'Phase 3 Smoke Account',
			'accessToken'    => $access_token,
			'refreshToken'   => $refresh_token,
			'tokenExpiresAt' => gmdate( DATE_ATOM, time() + HOUR_IN_SECONDS ),
			'scopes'         => 'pages_manage_posts',
			'metadata'       => '{"smoke":true}',
		)
	);
	$created_account_id = (int) $account['id'];
	$assert( $access_token === $account['accessToken'], 'Access token did not decrypt through repository.' );
	$assert( $refresh_token === $account['refreshToken'], 'Refresh token did not decrypt through repository.' );

	$raw_access_token = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT access_token FROM ' . Installer::table_name( 'sms_social_account' ) . ' WHERE id = %d',
			$created_account_id
		)
	);
	$assert( is_string( $raw_access_token ) && $raw_access_token !== $access_token, 'Access token was stored as plaintext.' );
	$assert( str_starts_with( $raw_access_token, 'sms1:' ), 'Access token ciphertext prefix was missing.' );

	$post = $post_repository->create(
		array(
			'title'           => 'Phase 3 Smoke Post',
			'caption'         => 'Smoke test caption',
			'platform'        => 'instagram',
			'socialAccountId' => $created_account_id,
			'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			'status'          => 'DRAFT',
			'isStory'         => false,
			'notes'           => 'Created by RepositorySmokeTest.',
		)
	);
	$created_post_id = (int) $post['id'];
	$assert( 'instagram' === $post['platform'], 'Post repository did not persist platform.' );

	$media = $media_repository->attach( $created_post_id, 0 );
	$assert( 0 === $media['attachmentId'], 'Media repository did not persist attachment ID.' );
	$assert( 1 === count( $media_repository->reorder( $created_post_id, array( 0 ) ) ), 'Media reorder did not return attached media.' );

	$scheduled_result = $result_repository->create(
		array(
			'postId'         => $created_post_id,
			'platform'       => 'instagram',
			'status'         => 'scheduled',
			'platformPostId' => 'phase3-platform-post',
		)
	);
	$assert( 'scheduled' === $scheduled_result['status'], 'Publish result was not created as scheduled.' );

	$resolved_result = $result_repository->resolve_pending(
		$created_post_id,
		'instagram',
		array(
			'status'         => 'success',
			'platformPostId' => 'phase3-platform-post',
			'permalink'      => 'https://example.com/phase3-platform-post',
		)
	);
	$assert( 'success' === $resolved_result['status'], 'Publish result did not resolve to success.' );
	$assert( in_array( 'phase3-platform-post', $result_repository->find_tracked_platform_post_ids(), true ), 'Tracked platform post ID was missing.' );

	$external = $external_repository->upsert(
		array(
			'platform'       => 'instagram',
			'accountId'      => $created_account_id,
			'platformPostId' => 'phase3-external-post',
			'content'        => 'External smoke content',
			'mediaUrl'       => 'https://example.com/media.jpg',
			'permalink'      => 'https://example.com/external',
			'publishedAt'    => gmdate( DATE_ATOM ),
			'metadata'       => '{"smoke":true}',
		)
	);
	$assert( 'phase3-external-post' === $external['platformPostId'], 'External post upsert failed.' );
	$assert( count( $external_repository->list_by_month( (int) gmdate( 'Y' ), (int) gmdate( 'n' ) ) ) >= 1, 'External post month filter returned no rows.' );

	$filtered_posts = $post_repository->list(
		array(
			'platform'                 => 'instagram',
			'excludeWithPublishResult' => true,
		)
	);
	$matching = array_filter(
		$filtered_posts,
		static fn ( array $candidate ): bool => (int) $candidate['id'] === $created_post_id
	);
	$assert( 0 === count( $matching ), 'Post with successful publish result was not excluded.' );
} finally {
	if ( null !== $created_post_id ) {
		$post_repository->delete( $created_post_id );
	}

	if ( null !== $created_account_id ) {
		$external_repository->delete_by_account_id( $created_account_id );
		$account_repository->delete( $created_account_id );
	}

	if ( is_array( $original_settings ) ) {
		update_option( Installer::OPTION_SETTINGS, $original_settings, false );
	}
}

echo "Repository smoke test passed.\n";
