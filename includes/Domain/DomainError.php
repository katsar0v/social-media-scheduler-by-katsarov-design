<?php
/**
 * Base domain exception.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Domain;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class DomainError extends RuntimeException {
	protected int $status_code = 500;

	public function status_code(): int {
		return $this->status_code;
	}
}
