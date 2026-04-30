<?php
/**
 * Validation exception.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ValidationError extends DomainError {
	protected int $status_code = 400;
}
