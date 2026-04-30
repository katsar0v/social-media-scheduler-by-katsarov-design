<?php
/**
 * External posts REST controller.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Repository\PublishResultRepository;
use KatsarovDesign\SocialMediaScheduler\Service\ExternalPostService;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExternalPostsController extends Controller {
	private ExternalPostService $service;

	public function __construct( ?ExternalPostService $service = null ) {
		$this->service = $service ?? new ExternalPostService( publish_result_repository: new PublishResultRepository() );
	}

	public function list( WP_REST_Request $request ): mixed {
		try {
			$month = $request->get_param( 'month' );
			$year  = $request->get_param( 'year' );

			return $this->response( $this->service->list( null === $month ? null : (int) $month, null === $year ? null : (int) $year ) );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function refresh( WP_REST_Request $request ): mixed {
		try {
			return $this->response( $this->service->refresh() );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}
}
