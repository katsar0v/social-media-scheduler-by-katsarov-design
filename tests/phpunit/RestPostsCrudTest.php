<?php
/**
 * REST posts CRUD contract tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Plugin;

final class RestPostsCrudTest extends SmsTestCase {
	private int $admin_id;
	private int $account_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_id = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		get_user_by( 'id', $this->admin_id )->add_cap( Plugin::CAPABILITY );
		wp_set_current_user( $this->admin_id );
		$this->account_id = (int) $this->create_meta_account()['id'];

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	public function test_posts_crud_requires_nonce_and_persists_contract(): void {
		$missing_nonce = $this->rest_request( 'GET', '/sms/v1/posts' );
		$this->assertSame( 403, $this->response_status( $missing_nonce ) );

		$nonce = wp_create_nonce( 'wp_rest' );
		$create = $this->rest_request(
			'POST',
			'/sms/v1/posts',
			array(
				'title'           => 'REST PHPUnit Post',
				'caption'         => 'REST PHPUnit caption',
				'platform'        => 'instagram',
				'socialAccountId' => $this->account_id,
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'PUBLISHED',
			),
			$nonce
		);

		$this->assertSame( 201, $this->response_status( $create ) );
		$created = $create->get_data();
		$this->assertSame( 'SCHEDULED', $created['status'] );

		$get = $this->rest_request( 'GET', '/sms/v1/posts/' . (int) $created['id'], array(), $nonce );
		$this->assertSame( 200, $this->response_status( $get ) );
		$this->assertSame( 'REST PHPUnit caption', $get->get_data()['caption'] );

		$update = $this->rest_request( 'PUT', '/sms/v1/posts/' . (int) $created['id'], array( 'notes' => ' Updated. ' ), $nonce );
		$this->assertSame( 200, $this->response_status( $update ) );
		$this->assertSame( 'Updated.', $update->get_data()['notes'] );

		$delete = $this->rest_request( 'DELETE', '/sms/v1/posts/' . (int) $created['id'], array(), $nonce );
		$this->assertSame( 204, $this->response_status( $delete ) );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 */
	private function rest_request( string $method, string $route, array $params = array(), ?string $nonce = null ): WP_REST_Response|WP_Error {
		$request = new WP_REST_Request( $method, $route );
		if ( null !== $nonce ) {
			$request->set_header( 'X-WP-Nonce', $nonce );
		}
		if ( in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$request->set_body_params( $params );
		} else {
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
		}

		return rest_do_request( $request );
	}

	private function response_status( WP_REST_Response|WP_Error $response ): int {
		if ( $response instanceof WP_Error ) {
			return (int) ( $response->get_error_data()['status'] ?? 500 );
		}

		return $response->get_status();
	}
}
