<?php
/**
 * Publish REST controller.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Service\PublishService;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PublishController extends Controller {
	private PublishService $service;

	public function __construct( ?PublishService $service = null ) {
		$this->service = $service ?? new PublishService();
	}

	public function publish_meta( WP_REST_Request $request ): mixed {
		try {
			$data = $this->request_data( $request );
			$targets = $data['targetPlatforms'] ?? $data['target_platforms'] ?? array();
			$targets = is_array( $targets ) ? array_values( array_map( 'strval', $targets ) ) : array();

			return $this->response(
				$this->service->publish_to_meta(
					array(
						'postId'          => (int) ( $data['postId'] ?? $data['post_id'] ?? 0 ),
						'targetPlatforms' => $targets,
					)
				)
			);
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function publish_tiktok( WP_REST_Request $request ): mixed {
		try {
			$data = $this->request_data( $request );

			return $this->response(
				$this->service->publish_to_tiktok(
					array(
						'postId' => (int) ( $data['postId'] ?? $data['post_id'] ?? 0 ),
					)
				)
			);
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function results( WP_REST_Request $request ): mixed {
		try {
			return $this->response( $this->service->get_results_for_post( (int) $request['postId'] ) );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}
}
