<?php
/**
 * Settings validation service.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Domain\Platform;
use KatsarovDesign\SocialMediaScheduler\Domain\PostStatus;
use KatsarovDesign\SocialMediaScheduler\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsService {
	private const TIMEZONES = array(
		'Europe/Sofia',
		'Europe/London',
		'Europe/Paris',
		'Europe/Berlin',
		'Europe/Madrid',
		'Europe/Rome',
		'Europe/Amsterdam',
		'Europe/Athens',
		'America/New_York',
		'America/Chicago',
		'America/Denver',
		'America/Los_Angeles',
		'Asia/Tokyo',
		'Asia/Shanghai',
		'Australia/Sydney',
		'UTC',
	);

	private SettingsRepository $repository;

	public function __construct( ?SettingsRepository $repository = null ) {
		$this->repository = $repository ?? new SettingsRepository();
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get(): array {
		return $this->repository->get();
	}

	/**
	 * @param array<string,mixed> $input Settings input.
	 * @return array<string,mixed>
	 */
	public function update( array $input ): array {
		return $this->repository->update( $this->normalize_update_input( $input ) );
	}

	/**
	 * @param array<string,mixed> $input Settings input.
	 * @return array<string,mixed>
	 */
	private function normalize_update_input( array $input ): array {
		$normalized = array();

		if ( array_key_exists( 'timezone', $input ) ) {
			$timezone = trim( (string) $input['timezone'] );
			if ( ! in_array( $timezone, self::TIMEZONES, true ) ) {
				throw new SettingsValidationException(
					sprintf(
						/* translators: %s: comma-separated list of allowed time zones. */
						__( 'Timezone must be one of: %s', 'social-media-scheduler' ),
						implode( ', ', self::TIMEZONES )
					)
				);
			}
			$normalized['timezone'] = $timezone;
		}

		if ( array_key_exists( 'defaultPlatform', $input ) ) {
			$platform = trim( (string) $input['defaultPlatform'] );
			if ( ! Platform::is_valid( $platform ) ) {
				throw new SettingsValidationException(
					sprintf(
						/* translators: %s: comma-separated list of allowed social platforms. */
						__( 'Default platform must be one of: %s', 'social-media-scheduler' ),
						implode( ', ', Platform::values() )
					)
				);
			}
			$normalized['defaultPlatform'] = $platform;
		}

		if ( array_key_exists( 'defaultPostStatus', $input ) ) {
			$status = trim( (string) $input['defaultPostStatus'] );
			if ( ! in_array( $status, PostStatus::creatable_values(), true ) ) {
				throw new SettingsValidationException(
					sprintf(
						/* translators: %s: comma-separated list of allowed post statuses. */
						__( 'Default post status must be one of: %s', 'social-media-scheduler' ),
						implode( ', ', PostStatus::creatable_values() )
					)
				);
			}
			$normalized['defaultPostStatus'] = $status;
		}

		foreach ( array( 'brandHashtags', 'metaAppId', 'metaAppSecret', 'tiktokClientKey', 'tiktokClientSecret', 'tiktokRedirectUri', 'baseUrl' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$normalized[ $key ] = trim( (string) $input[ $key ] );
			}
		}

		foreach ( array( 'tiktokRedirectUri', 'baseUrl' ) as $url_key ) {
			if ( ! empty( $normalized[ $url_key ] ) && ! wp_http_validate_url( (string) $normalized[ $url_key ] ) ) {
				throw new SettingsValidationException(
					sprintf(
						/* translators: %s: settings field key. */
						__( '%s must be a valid HTTP URL.', 'social-media-scheduler' ),
						$url_key
					)
				);
			}
		}

		if ( array_key_exists( 'calendarWeekStart', $input ) ) {
			$week_start = (int) $input['calendarWeekStart'];
			if ( 0 !== $week_start && 1 !== $week_start ) {
				throw new SettingsValidationException( __( 'Calendar week start must be 0 (Sunday) or 1 (Monday).', 'social-media-scheduler' ) );
			}
			$normalized['calendarWeekStart'] = $week_start;
		}

		if ( array_key_exists( 'removeOnUninstall', $input ) ) {
			$normalized['removeOnUninstall'] = (bool) $input['removeOnUninstall'];
		}

		return $normalized;
	}
}
