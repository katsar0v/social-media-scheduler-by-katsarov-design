<?php
/**
 * REST route registration.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Plugin;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestRouter {
	public const NAMESPACE = 'sms/v1';

	public static function register_routes(): void {
		$posts          = new PostsController();
		$settings       = new SettingsController();
		$media          = new MediaController();
		$publish        = new PublishController();
		$external_posts = new ExternalPostsController();
		$auth           = new AuthController();

		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $posts, 'list' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $posts, 'create' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $posts, 'get' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $posts, 'update' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $posts, 'delete' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/media',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $posts, 'attach_media' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/media/reorder',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $posts, 'reorder_media' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<postId>\d+)/media/(?P<mediaId>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $posts, 'remove_media' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $settings, 'get' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $settings, 'update' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $media, 'delete' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/publish/meta',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $publish, 'publish_meta' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/publish/tiktok',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $publish, 'publish_tiktok' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/publish/(?P<postId>\d+)/results',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $publish, 'results' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/external-posts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $external_posts, 'list' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/external-posts/refresh',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $external_posts, 'refresh' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/accounts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $auth, 'accounts' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/accounts/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $auth, 'delete_account' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/meta/callback',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $auth, 'meta_callback' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/tiktok/callback',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $auth, 'tiktok_callback' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public static function register_admin_post_actions(): void {
		$auth = new AuthController();
		add_action( 'admin_post_sms_oauth_meta_init', array( $auth, 'meta_init' ) );
		add_action( 'admin_post_sms_oauth_tiktok_init', array( $auth, 'tiktok_init' ) );
	}

	public static function permission_callback( WP_REST_Request $request ): true|WP_Error {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'sms_rest_invalid_nonce',
				__( 'A valid REST nonce is required.', 'social-media-scheduler' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			return new WP_Error(
				'sms_rest_forbidden',
				__( 'You are not allowed to manage the social scheduler.', 'social-media-scheduler' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
