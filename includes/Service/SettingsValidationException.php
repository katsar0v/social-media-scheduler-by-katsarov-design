<?php
/**
 * Settings validation exception.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Domain\ValidationError;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsValidationException extends ValidationError {}
