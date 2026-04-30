<?php
/**
 * REST API smoke test for wp eval-file.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

use KatsarovDesign\SocialMediaScheduler\Plugin;
use KatsarovDesign\SocialMediaScheduler\Repository\ExternalPostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This test must be run through WP-CLI: wp eval-file tests/RestSmokeTest.php\n" );
	exit( 1 );
}

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$admin_users = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
$assert( ! empty( $admin_users ), 'No administrator user exists for REST smoke test.' );
$admin_id = (int) $admin_users[0]->ID;

do_action( 'rest_api_init' );
$assert( false !== has_action( 'admin_post_sms_oauth_meta_init' ), 'Meta OAuth admin-post action is not registered.' );
$assert( false !== has_action( 'admin_post_sms_oauth_tiktok_init' ), 'TikTok OAuth admin-post action is not registered.' );

$request = static function ( string $method, string $route, ?string $nonce = null, array $params = array() ): WP_REST_Response|WP_Error {
	$request = new WP_REST_Request( $method, $route );
	if ( null !== $nonce ) {
		$request->set_header( 'X-WP-Nonce', $nonce );
	}
	if ( ! empty( $params ) ) {
		if ( 'GET' === strtoupper( $method ) || 'DELETE' === strtoupper( $method ) ) {
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
		} else {
			$request->set_body_params( $params );
		}
	}

	return rest_do_request( $request );
};

$status = static function ( WP_REST_Response|WP_Error $response ): int {
	return $response instanceof WP_Error ? (int) ( $response->get_error_data()['status'] ?? 500 ) : $response->get_status();
};

$data = static function ( WP_REST_Response|WP_Error $response ): mixed {
	return $response instanceof WP_Error ? null : $response->get_data();
};

$account_repository  = new SocialAccountRepository();
$post_repository     = new PostRepository();
$external_repository = new ExternalPostRepository();
$account_id          = null;
$post_id             = null;

try {
	wp_set_current_user( $admin_id );
	$nonce = wp_create_nonce( 'wp_rest' );

	$without_nonce = $request( 'GET', '/sms/v1/settings' );
	$assert( 403 === $status( $without_nonce ), 'REST request without nonce should return 403.' );

	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'sms-rest-smoke-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20, true ),
			'user_email' => 'sms-rest-smoke@example.com',
			'role'       => 'subscriber',
		)
	);
	$assert( ! is_wp_error( $subscriber_id ), 'Could not create subscriber for capability check.' );
	wp_set_current_user( (int) $subscriber_id );
	$subscriber_nonce = wp_create_nonce( 'wp_rest' );
	$forbidden        = $request( 'GET', '/sms/v1/settings', $subscriber_nonce );
	$assert( 403 === $status( $forbidden ), 'Subscriber REST request should return 403.' );
	wp_delete_user( (int) $subscriber_id );

	wp_set_current_user( $admin_id );
	$settings_response = $request( 'GET', '/sms/v1/settings', $nonce );
	$assert( 200 === $status( $settings_response ), 'Settings GET did not return 200.' );

	$updated_settings = $request(
		'PUT',
		'/sms/v1/settings',
		$nonce,
		array(
			'brandHashtags'     => '#rest-smoke',
			'calendarWeekStart' => 1,
		)
	);
	$assert( 200 === $status( $updated_settings ), 'Settings PUT did not return 200.' );
	$assert( '#rest-smoke' === $data( $updated_settings )['brandHashtags'], 'Settings PUT did not persist brand hashtags.' );

	$account = $account_repository->upsert(
		array(
			'platform'       => 'meta',
			'providerUserId' => 'rest-meta-' . wp_generate_uuid4(),
			'accountName'    => 'REST Meta Account',
			'accessToken'    => 'rest-meta-access-' . wp_generate_uuid4(),
			'tokenExpiresAt' => null,
			'scopes'         => 'pages_manage_posts,instagram_basic,instagram_content_publish',
			'metadata'       => wp_json_encode(
				array(
					'fbPageId'            => 'rest-page',
					'igBusinessAccountId' => 'rest-ig',
				)
			),
		)
	);
	$account_id = (int) $account['id'];

	$create_post = $request(
		'POST',
		'/sms/v1/posts',
		$nonce,
		array(
			'title'           => 'REST Smoke Post',
			'caption'         => 'REST smoke caption',
			'platform'        => 'instagram',
			'socialAccountId' => $account_id,
			'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			'status'          => 'PUBLISHED',
		)
	);
	$assert( 201 === $status( $create_post ), 'Post create did not return 201.' );
	$post_data = $data( $create_post );
	$post_id   = (int) $post_data['id'];
	$assert( 'SCHEDULED' === $post_data['status'], 'REST post create did not resolve future PUBLISHED to SCHEDULED.' );

	$get_post = $request( 'GET', '/sms/v1/posts/' . $post_id, $nonce );
	$assert( 200 === $status( $get_post ), 'Post GET did not return 200.' );

	$update_post = $request(
		'PUT',
		'/sms/v1/posts/' . $post_id,
		$nonce,
		array( 'notes' => ' Updated through REST. ' )
	);
	$assert( 200 === $status( $update_post ), 'Post PUT did not return 200.' );
	$assert( 'Updated through REST.' === $data( $update_post )['notes'], 'Post PUT did not trim notes.' );

	$publish_meta = $request(
		'POST',
		'/sms/v1/publish/meta',
		$nonce,
		array(
			'postId'          => $post_id,
			'targetPlatforms' => array( 'instagram' ),
		)
	);
	$assert( 200 === $status( $publish_meta ), 'Meta publish route did not return 200 for deferred publish.' );
	$assert( true === $data( $publish_meta )[0]['isScheduled'], 'Meta publish route did not defer future Instagram publish.' );

	$results = $request( 'GET', '/sms/v1/publish/' . $post_id . '/results', $nonce );
	$assert( 200 === $status( $results ), 'Publish results route did not return 200.' );
	$assert( count( $data( $results ) ) >= 1, 'Publish results route returned no results after deferred publish.' );

	$accounts = $request( 'GET', '/sms/v1/auth/accounts', $nonce );
	$assert( 200 === $status( $accounts ), 'Auth accounts route did not return 200.' );

	$external_posts = $request( 'GET', '/sms/v1/external-posts', $nonce );
	$assert( 200 === $status( $external_posts ), 'External posts route did not return 200.' );

	$delete_post = $request( 'DELETE', '/sms/v1/posts/' . $post_id, $nonce );
	$assert( 204 === $status( $delete_post ), 'Post DELETE did not return 204.' );
	$post_id = null;
} finally {
	if ( null !== $post_id ) {
		$post_repository->delete( $post_id );
	}
	if ( null !== $account_id ) {
		$external_repository->delete_by_account_id( $account_id );
		$account_repository->delete( $account_id );
	}
}

echo "REST smoke test passed.\n";
