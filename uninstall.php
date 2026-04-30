<?php
/**
 * Plugin uninstall handler.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Installer;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'SMS_PLUGIN_DIR' ) ) {
	define( 'SMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

$sms_autoload = SMS_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $sms_autoload ) ) {
	require_once $sms_autoload;
} else {
	require_once SMS_PLUGIN_DIR . 'includes/Plugin.php';
	require_once SMS_PLUGIN_DIR . 'includes/Installer.php';
}

Installer::uninstall();
