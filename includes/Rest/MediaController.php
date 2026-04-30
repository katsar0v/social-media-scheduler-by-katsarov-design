<?php
/**
 * Media REST controller.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Service\MediaService;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaController extends Controller {
	private MediaService $service;

	public function __construct( ?MediaService $service = null ) {
		$this->service = $service ?? new MediaService();
	}

	public function delete( WP_REST_Request $request ): mixed {
		try {
			$this->service->delete_attachment( (int) $request['id'] );

			return $this->empty_response();
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}
}
