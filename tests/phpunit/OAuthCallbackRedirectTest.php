<?php
/**
 * OAuth callback redirect tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Admin\AdminMenu;
use KatsarovDesign\SocialMediaScheduler\Installer;
use KatsarovDesign\SocialMediaScheduler\Rest\AuthController;

final class OAuthCallbackRedirectTest extends SmsTestCase {
	public function test_meta_callback_redirects_to_accounts_with_success_notice_query(): void {
		$this->configure_oauth_settings();
		$state  = $this->set_oauth_state( 'meta' );
		$filter = array( $this, 'fake_meta_http_response' );
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$response = ( new AuthController() )->meta_callback(
				$this->request_with_params(
					array(
						'code'  => 'meta-code',
						'state' => $state,
					)
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertSame( 302, $response->get_status() );
		$location = $response->get_headers()['Location'];
		$this->assertStringStartsWith( admin_url( 'admin.php?page=sms-accounts' ), $location );
		parse_str( (string) parse_url( $location, PHP_URL_QUERY ), $query );
		$this->assertSame( 'success', $query['oauthStatus'] );
		$this->assertSame( 'meta', $query['provider'] );
		$this->assertSame( '2', $query['count'] );
	}

	public function test_tiktok_callback_redirects_to_accounts_with_success_notice_query(): void {
		$this->configure_oauth_settings();
		$state  = $this->set_oauth_state( 'tiktok' );
		$filter = array( $this, 'fake_tiktok_http_response' );
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$response = ( new AuthController() )->tiktok_callback(
				$this->request_with_params(
					array(
						'code'  => 'tiktok-code',
						'state' => $state,
					),
					'/sms/v1/auth/tiktok/callback'
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertSame( 302, $response->get_status() );
		$location = $response->get_headers()['Location'];
		$this->assertStringStartsWith( admin_url( 'admin.php?page=sms-accounts' ), $location );
		parse_str( (string) parse_url( $location, PHP_URL_QUERY ), $query );
		$this->assertSame( 'success', $query['oauthStatus'] );
		$this->assertSame( 'tiktok', $query['provider'] );
		$this->assertSame( '1', $query['count'] );
	}

	public function test_meta_callback_redirects_provider_error_to_accounts(): void {
		$response = ( new AuthController() )->meta_callback(
			$this->request_with_params(
				array(
					'error'             => 'access_denied',
					'error_description' => 'The user denied access.',
				)
			)
		);

		$this->assertSame( 302, $response->get_status() );
		parse_str( (string) parse_url( $response->get_headers()['Location'], PHP_URL_QUERY ), $query );
		$this->assertSame( 'error', $query['oauthStatus'] );
		$this->assertSame( 'meta', $query['provider'] );
		$this->assertSame( 'The user denied access.', $query['message'] );
	}

	public function test_tiktok_callback_redirects_service_error_to_accounts(): void {
		$this->configure_oauth_settings();
		$state  = $this->set_oauth_state( 'tiktok' );
		$filter = array( $this, 'fake_failed_tiktok_http_response' );
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$response = ( new AuthController() )->tiktok_callback(
				$this->request_with_params(
					array(
						'code'  => 'tiktok-code',
						'state' => $state,
					),
					'/sms/v1/auth/tiktok/callback'
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertSame( 302, $response->get_status() );
		parse_str( (string) parse_url( $response->get_headers()['Location'], PHP_URL_QUERY ), $query );
		$this->assertSame( 'error', $query['oauthStatus'] );
		$this->assertSame( 'tiktok', $query['provider'] );
		$this->assertSame( 'TikTok exchange failed.', $query['message'] );
	}

	public function test_meta_callback_missing_state_redirects_to_accounts_without_http_exchange(): void {
		$this->configure_oauth_settings();
		$filter = array( $this, 'fail_http_request' );
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$response = ( new AuthController() )->meta_callback( $this->request_with_params( array( 'code' => 'meta-code' ) ) );
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertSame( 302, $response->get_status() );
		parse_str( (string) parse_url( $response->get_headers()['Location'], PHP_URL_QUERY ), $query );
		$this->assertSame( 'error', $query['oauthStatus'] );
		$this->assertSame( 'meta', $query['provider'] );
		$this->assertSame( 'OAuth state is invalid or expired.', $query['message'] );
	}

	public function test_tiktok_callback_missing_state_redirects_to_accounts_without_http_exchange(): void {
		$this->configure_oauth_settings();
		$filter = array( $this, 'fail_http_request' );
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$response = ( new AuthController() )->tiktok_callback(
				$this->request_with_params( array( 'code' => 'tiktok-code' ), '/sms/v1/auth/tiktok/callback' )
			);
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertSame( 302, $response->get_status() );
		parse_str( (string) parse_url( $response->get_headers()['Location'], PHP_URL_QUERY ), $query );
		$this->assertSame( 'error', $query['oauthStatus'] );
		$this->assertSame( 'tiktok', $query['provider'] );
		$this->assertSame( 'OAuth state is invalid or expired.', $query['message'] );
	}

	public function test_meta_callback_redirects_to_accounts_with_success_notice_query_via_rest_dispatch(): void {
		$this->configure_oauth_settings();
		$state = $this->set_oauth_state( 'meta' );

		global $wp_rest_server;
		$previous_rest_server = $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		$filter = array( $this, 'fake_meta_http_response' );
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$response = rest_do_request(
				$this->request_with_params(
					array(
						'code'  => 'meta-code',
						'state' => $state,
					)
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
			$wp_rest_server = $previous_rest_server;
		}

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 302, $response->get_status() );
		parse_str( (string) parse_url( $response->get_headers()['Location'], PHP_URL_QUERY ), $query );
		$this->assertSame( 'success', $query['oauthStatus'] );
		$this->assertSame( 'meta', $query['provider'] );
		$this->assertSame( '2', $query['count'] );
	}

	public function test_accounts_screen_renders_success_notice_from_redirect_query(): void {
		$previous_get = $_GET;
		$_GET         = array(
			'page'        => 'sms-accounts',
			'oauthStatus' => 'success',
			'provider'    => 'meta',
			'count'       => '2',
		);

		try {
			$buffer_level = ob_get_level();
			ob_start();
			AdminMenu::render_accounts();
			$output = ob_get_clean();
		} finally {
			if ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			$_GET = $previous_get;
		}

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'notice notice-success', $output );
		$this->assertStringContainsString( 'Успешно свързахме 2 Meta акаунта.', $output );
	}

	/**
	 * @param array<string,string> $params REST request parameters.
	 */
	private function request_with_params( array $params, string $route = '/sms/v1/auth/meta/callback' ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	private function configure_oauth_settings(): void {
		update_option(
			Installer::OPTION_SETTINGS,
			array_merge(
				Installer::default_settings(),
				array(
					'metaAppId'          => 'meta-app-id',
					'metaAppSecret'      => 'meta-app-secret',
					'tiktokClientKey'    => 'tiktok-client-key',
					'tiktokClientSecret' => 'tiktok-client-secret',
				)
			),
			false
		);
	}

	private function set_oauth_state( string $provider ): string {
		$state = $provider . '-state-' . wp_generate_uuid4();
		set_transient( 'sms_oauth_' . $provider . '_state_' . $state, 1, 10 * MINUTE_IN_SECONDS );

		return $state;
	}

	/**
	 * @param array<string,mixed> $args Request args.
	 */
	public function fake_meta_http_response( mixed $preempt, array $args, string $url ): array|false {
		if ( str_contains( $url, '/oauth/access_token' ) ) {
			$body = str_contains( (string) ( $args['body'] ?? '' ), 'fb_exchange_token' )
				? array(
					'access_token' => 'long-lived-user-token',
					'expires_in'   => 3600,
				)
				: array( 'access_token' => 'short-lived-user-token' );

			return $this->http_response( $body );
		}

		if ( str_contains( $url, '/me/accounts' ) ) {
			return $this->http_response(
				array(
					'data' => array(
						array(
							'id'           => 'page-1',
							'name'         => 'First Page',
							'access_token' => 'page-token-1',
						),
						array(
							'id'           => 'page-2',
							'name'         => 'Second Page',
							'access_token' => 'page-token-2',
						),
					),
				)
			);
		}

		if ( str_contains( $url, '/page-1' ) ) {
			return $this->http_response(
				array(
					'instagram_business_account' => array(
						'id'       => 'ig-1',
						'username' => 'firstpage',
					),
				)
			);
		}

		if ( str_contains( $url, '/page-2' ) ) {
			return $this->http_response(
				array(
					'instagram_business_account' => array(
						'id'       => 'ig-2',
						'username' => 'secondpage',
					),
				)
			);
		}

		return false;
	}

	/**
	 * @param array<string,mixed> $args Request args.
	 */
	public function fake_tiktok_http_response( mixed $preempt, array $args, string $url ): array|false {
		if ( str_contains( $url, 'open.tiktokapis.com/v2/oauth/token' ) ) {
			return $this->http_response(
				array(
					'access_token'  => 'tiktok-access-token',
					'refresh_token' => 'tiktok-refresh-token',
					'open_id'       => 'tiktok-open-id',
					'expires_in'    => 3600,
				)
			);
		}

		return false;
	}

	/**
	 * @param array<string,mixed> $args Request args.
	 */
	public function fake_failed_tiktok_http_response( mixed $preempt, array $args, string $url ): array|false {
		if ( str_contains( $url, 'open.tiktokapis.com/v2/oauth/token' ) ) {
			return $this->http_response( array( 'error_description' => 'TikTok exchange failed.' ) );
		}

		return false;
	}

	/**
	 * @param array<string,mixed> $args Request args.
	 */
	public function fail_http_request( mixed $preempt, array $args, string $url ): never {
		$this->fail( 'OAuth callback attempted an HTTP request before validating state.' );
	}

	/**
	 * @param array<string,mixed> $body Response body.
	 * @return array<string,mixed>
	 */
	private function http_response( array $body, int $status = 200 ): array {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status,
				'message' => 200 === $status ? 'OK' : 'Bad Request',
			),
			'cookies'  => array(),
		);
	}
}
