<?php
/**
 * Settings REST controller.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Service\SettingsService;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsController extends Controller {
	private SettingsService $service;

	public function __construct( ?SettingsService $service = null ) {
		$this->service = $service ?? new SettingsService();
	}

	public function get( WP_REST_Request $request ): mixed {
		try {
			return $this->response( $this->service->get() );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function update( WP_REST_Request $request ): mixed {
		try {
			return $this->response( $this->service->update( $this->request_data( $request ) ) );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}
}
