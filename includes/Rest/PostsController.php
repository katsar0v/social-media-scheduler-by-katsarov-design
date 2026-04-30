<?php
/**
 * Posts REST controller.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Service\PostService;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostsController extends Controller {
	private PostService $service;

	public function __construct( ?PostService $service = null ) {
		$this->service = $service ?? new PostService();
	}

	public function list( WP_REST_Request $request ): mixed {
		try {
			return $this->response(
				$this->service->list(
					array(
						'status'                   => $request->get_param( 'status' ),
						'platform'                 => $request->get_param( 'platform' ),
						'from'                     => $request->get_param( 'from' ),
						'to'                       => $request->get_param( 'to' ),
						'excludeWithPublishResult' => filter_var( $request->get_param( 'excludeWithPublishResult' ), FILTER_VALIDATE_BOOLEAN ),
					)
				)
			);
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function create( WP_REST_Request $request ): mixed {
		try {
			return $this->response( $this->service->create( $this->request_data( $request ) ), 201 );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function get( WP_REST_Request $request ): mixed {
		try {
			return $this->response( $this->service->find_by_id( (int) $request['id'] ) );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function update( WP_REST_Request $request ): mixed {
		try {
			return $this->response( $this->service->update( (int) $request['id'], $this->request_data( $request ) ) );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function delete( WP_REST_Request $request ): mixed {
		try {
			$this->service->delete( (int) $request['id'] );

			return $this->empty_response();
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function attach_media( WP_REST_Request $request ): mixed {
		try {
			$data = $this->request_data( $request );

			return $this->response(
				$this->service->add_media(
					(int) $request['id'],
					(int) ( $data['attachmentId'] ?? $data['attachment_id'] ?? 0 )
				),
				201
			);
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function reorder_media( WP_REST_Request $request ): mixed {
		try {
			$data = $this->request_data( $request );
			$ids  = $data['attachmentIds'] ?? $data['attachment_ids'] ?? array();
			$ids  = is_array( $ids ) ? array_map( 'intval', $ids ) : array();

			return $this->response( $this->service->reorder_media( (int) $request['id'], $ids ) );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function remove_media( WP_REST_Request $request ): mixed {
		try {
			$removed = $this->service->remove_media( (int) $request['postId'], (int) $request['mediaId'] );

			return null === $removed ? $this->empty_response() : $this->response( $removed );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}
}
