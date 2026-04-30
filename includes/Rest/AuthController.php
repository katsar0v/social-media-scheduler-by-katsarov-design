<?php
/**
 * Auth REST controller and OAuth init actions.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Cron\CronRegistrar;
use KatsarovDesign\SocialMediaScheduler\Plugin;
use KatsarovDesign\SocialMediaScheduler\Service\MetaOAuthService;
use KatsarovDesign\SocialMediaScheduler\Service\TikTokOAuthService;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuthController extends Controller {
	private MetaOAuthService $meta_service;
	private TikTokOAuthService $tiktok_service;

	public function __construct( ?MetaOAuthService $meta_service = null, ?TikTokOAuthService $tiktok_service = null ) {
		$this->meta_service   = $meta_service ?? new MetaOAuthService();
		$this->tiktok_service = $tiktok_service ?? new TikTokOAuthService();
	}

	public function accounts( WP_REST_Request $request ): mixed {
		try {
			return $this->response( $this->meta_service->list_accounts() );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function delete_account( WP_REST_Request $request ): mixed {
		try {
			$deleted = $this->meta_service->delete_account( (int) $request['id'] );

			return $deleted ? $this->empty_response() : $this->response( array( 'deleted' => false ), 404 );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function meta_callback( WP_REST_Request $request ): mixed {
		$provider_error = $this->provider_error_message( $request );
		if ( '' !== $provider_error ) {
			return $this->oauth_error_redirect( 'meta', $provider_error );
		}

		$code = trim( (string) $request->get_param( 'code' ) );
		if ( '' === $code ) {
			return $this->oauth_error_redirect( 'meta', __( 'Missing OAuth authorization code.', 'social-media-scheduler' ) );
		}

		$state = trim( (string) $request->get_param( 'state' ) );
		if ( '' === $state ) {
			return $this->oauth_error_redirect( 'meta', __( 'OAuth state is invalid or expired.', 'social-media-scheduler' ) );
		}

		try {
			$accounts = $this->meta_service->handle_callback( $code, $state );
			$this->schedule_external_posts_refresh();

			return $this->oauth_success_redirect( 'meta', count( $accounts ) );
		} catch ( \Throwable $error ) {
			return $this->oauth_error_redirect( 'meta', $error->getMessage() );
		}
	}

	public function tiktok_callback( WP_REST_Request $request ): mixed {
		$provider_error = $this->provider_error_message( $request );
		if ( '' !== $provider_error ) {
			return $this->oauth_error_redirect( 'tiktok', $provider_error );
		}

		$code = trim( (string) $request->get_param( 'code' ) );
		if ( '' === $code ) {
			return $this->oauth_error_redirect( 'tiktok', __( 'Missing OAuth authorization code.', 'social-media-scheduler' ) );
		}

		$state = trim( (string) $request->get_param( 'state' ) );
		if ( '' === $state ) {
			return $this->oauth_error_redirect( 'tiktok', __( 'OAuth state is invalid or expired.', 'social-media-scheduler' ) );
		}

		try {
			$this->tiktok_service->handle_callback( $code, $state );

			return $this->oauth_success_redirect( 'tiktok', 1 );
		} catch ( \Throwable $error ) {
			return $this->oauth_error_redirect( 'tiktok', $error->getMessage() );
		}
	}

	public function meta_init(): void {
		$this->assert_can_manage();
		try {
			$authorization_url = $this->meta_service->create_authorization_url();
		} catch ( \Throwable $error ) {
			$this->redirect_with_error( $error->getMessage() );
		}

		wp_redirect( $authorization_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	public function tiktok_init(): void {
		$this->assert_can_manage();
		try {
			$authorization_url = $this->tiktok_service->create_authorization_url();
		} catch ( \Throwable $error ) {
			$this->redirect_with_error( $error->getMessage() );
		}

		wp_redirect( $authorization_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	private function assert_can_manage(): void {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the social scheduler.', 'social-media-scheduler' ), 403 );
		}

		check_admin_referer( 'sms_oauth_init' );
	}

	private function redirect_with_error( string $message ): void {
		set_transient( 'sms_oauth_error_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=sms-accounts' ) );
		exit;
	}

	private function oauth_success_redirect( string $provider, int $count ): WP_REST_Response {
		return $this->redirect_response(
			array(
				'oauthStatus' => 'success',
				'provider'    => $provider,
				'count'       => $count,
			)
		);
	}

	private function oauth_error_redirect( string $provider, string $message ): WP_REST_Response {
		$message = sanitize_text_field( $message );

		return $this->redirect_response(
			array(
				'oauthStatus' => 'error',
				'provider'    => $provider,
				'message'     => '' !== $message ? $message : $this->default_error_message( $provider ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $query_args Redirect query arguments.
	 */
	private function redirect_response( array $query_args ): WP_REST_Response {
		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', esc_url_raw( add_query_arg( $query_args, admin_url( 'admin.php?page=sms-accounts' ) ) ) );

		return $response;
	}

	private function provider_error_message( WP_REST_Request $request ): string {
		$error = trim( (string) $request->get_param( 'error' ) );
		if ( '' === $error ) {
			return '';
		}

		$description = trim( (string) ( $request->get_param( 'error_description' ) ?: $request->get_param( 'error_message' ) ) );

		return '' !== $description ? $description : $error;
	}

	private function default_error_message( string $provider ): string {
		return 'tiktok' === $provider
			? __( 'Could not connect the TikTok account. Please try again.', 'social-media-scheduler' )
			: __( 'Could not connect the Meta account. Please try again.', 'social-media-scheduler' );
	}

	private function schedule_external_posts_refresh(): void {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, CronRegistrar::EXTERNAL_POSTS_REFRESH );
	}
}
