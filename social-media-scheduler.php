<?php
/**
 * Plugin Name: Social Media Scheduler
 * Description: Editorial social media scheduling for Sebeotkrivatel.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Katsarov Design
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-media-scheduler
 * Domain Path: /languages
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMS_PLUGIN_VERSION', '0.1.0' );
define( 'SMS_DB_VERSION', '1.0.0' );
define( 'SMS_PLUGIN_FILE', __FILE__ );
define( 'SMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$sms_autoload = SMS_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $sms_autoload ) ) {
	require_once $sms_autoload;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'KatsarovDesign\\SocialMediaScheduler\\';
			if ( 0 !== strncmp( $prefix, $class_name, strlen( $prefix ) ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $prefix ) );
			$file           = SMS_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook(
	SMS_PLUGIN_FILE,
	static function (): void {
		Plugin::instance()->activate();
	}
);

register_deactivation_hook(
	SMS_PLUGIN_FILE,
	static function (): void {
		Plugin::instance()->deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->init();
	}
);
