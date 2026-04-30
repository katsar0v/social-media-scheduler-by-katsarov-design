<?php
/**
 * WP-Cron registration helpers.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and schedules recurring plugin events.
 */
final class CronRegistrar {
	public const PUBLISH_TICK           = 'sms_publish_tick';
	public const TOKEN_REFRESH          = 'sms_token_refresh';
	public const EXTERNAL_POSTS_REFRESH = 'sms_external_posts_refresh';
	public const INTERVAL_MINUTE        = 'sms_minute';
	public const INTERVAL_SIX_HOURS     = 'sms_six_hours';

	/**
	 * @param array<string,array{interval:int,display:string}> $schedules Existing cron schedules.
	 * @return array<string,array{interval:int,display:string}>
	 */
	public static function add_schedules( array $schedules ): array {
		$schedules[ self::INTERVAL_MINUTE ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'social-media-scheduler' ),
		);

		$schedules[ self::INTERVAL_SIX_HOURS ] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every six hours', 'social-media-scheduler' ),
		);

		return $schedules;
	}

	public static function schedule_events(): void {
		if ( ! wp_next_scheduled( self::PUBLISH_TICK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::INTERVAL_MINUTE, self::PUBLISH_TICK );
		}

		if ( ! wp_next_scheduled( self::TOKEN_REFRESH ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::TOKEN_REFRESH );
		}

		if ( ! wp_next_scheduled( self::EXTERNAL_POSTS_REFRESH ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, self::INTERVAL_SIX_HOURS, self::EXTERNAL_POSTS_REFRESH );
		}
	}

	public static function unschedule_events(): void {
		foreach ( self::event_hooks() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * @return list<string>
	 */
	public static function event_hooks(): array {
		return array(
			self::PUBLISH_TICK,
			self::TOKEN_REFRESH,
			self::EXTERNAL_POSTS_REFRESH,
		);
	}
}
