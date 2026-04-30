<?php
/**
 * Shared REST controller helpers.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Domain\DomainError;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Controller {
	/**
	 * @return array<string,mixed>
	 */
	protected function request_data( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( is_array( $json ) && ! empty( $json ) ) {
			return $json;
		}

		$params = $request->get_body_params();
		if ( is_array( $params ) && ! empty( $params ) ) {
			return $params;
		}

		return $request->get_params();
	}

	protected function response( mixed $data = null, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	protected function empty_response(): WP_REST_Response {
		return new WP_REST_Response( null, 204 );
	}

	protected function error_response( Throwable $error ): WP_Error {
		$status = $error instanceof DomainError ? $error->status_code() : 500;

		return new WP_Error(
			'sms_rest_error',
			$error->getMessage(),
			array( 'status' => $status )
		);
	}
}
