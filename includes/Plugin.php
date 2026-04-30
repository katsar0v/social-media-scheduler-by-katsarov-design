<?php
/**
 * Plugin bootstrap.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler;

use KatsarovDesign\SocialMediaScheduler\Admin\AdminMenu;
use KatsarovDesign\SocialMediaScheduler\Admin\AssetEnqueuer;
use KatsarovDesign\SocialMediaScheduler\Admin\CronNoticeRenderer;
use KatsarovDesign\SocialMediaScheduler\Cron\CronHandlers;
use KatsarovDesign\SocialMediaScheduler\Cron\CronRegistrar;
use KatsarovDesign\SocialMediaScheduler\Rest\RestRouter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates core WordPress hooks for the plugin.
 */
final class Plugin {
	public const TEXT_DOMAIN = 'social-media-scheduler';
	public const CAPABILITY  = 'manage_social_scheduler';

	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( AdminMenu::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( AssetEnqueuer::class, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'set_script_translations' ), 20 );
		add_action( 'rest_api_init', array( RestRouter::class, 'register_routes' ) );
		add_filter( 'cron_schedules', array( CronRegistrar::class, 'add_schedules' ) );

		RestRouter::register_admin_post_actions();
		CronHandlers::register();
		CronRegistrar::schedule_events();
		CronNoticeRenderer::register();
		Installer::maybe_upgrade();
	}

	public function activate(): void {
		add_filter( 'cron_schedules', array( CronRegistrar::class, 'add_schedules' ) );

		Installer::install();
		Installer::grant_capabilities();
		CronRegistrar::schedule_events();
	}

	public function deactivate(): void {
		CronRegistrar::unschedule_events();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( SMS_PLUGIN_FILE ) ) . '/languages'
		);
	}

	public function set_script_translations(): void {
		foreach ( array( 'sms-calendar', 'sms-composer', 'sms-accounts', 'sms-settings' ) as $handle ) {
			wp_set_script_translations( $handle, self::TEXT_DOMAIN, SMS_PLUGIN_DIR . 'languages' );
		}
	}
}
