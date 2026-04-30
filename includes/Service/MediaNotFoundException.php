<?php
/**
 * Media not-found exception.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Domain\NotFoundError;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaNotFoundException extends NotFoundError {}
