<?php
/**
 * Not-found exception.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotFoundError extends DomainError {
	protected int $status_code = 404;
}
