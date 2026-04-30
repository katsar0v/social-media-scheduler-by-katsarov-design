<?php
/**
 * Publishing exception.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service\Publish;

use KatsarovDesign\SocialMediaScheduler\Domain\DomainError;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PublishError extends DomainError {
	private mixed $platform_error;

	public function __construct( string $message, int $status_code = 500, mixed $platform_error = null ) {
		parent::__construct( $message );
		$this->status_code    = $status_code;
		$this->platform_error = $platform_error;
	}

	public function platform_error(): mixed {
		return $this->platform_error;
	}
}
