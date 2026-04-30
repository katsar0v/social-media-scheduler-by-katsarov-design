<?php
/**
 * Settings repository backed by wp_options.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Repository;

use KatsarovDesign\SocialMediaScheduler\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsRepository {
	/**
	 * @return array<string,mixed>
	 */
	public function get(): array {
		Installer::ensure_settings_option();

		$settings = get_option( Installer::OPTION_SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return $this->normalize( $settings );
	}

	/**
	 * @param array<string,mixed> $input Settings fields.
	 * @return array<string,mixed>
	 */
	public function update( array $input ): array {
		$current = $this->get();
		$allowed = array(
			'timezone',
			'defaultPlatform',
			'defaultPostStatus',
			'brandHashtags',
			'calendarWeekStart',
			'metaAppId',
			'metaAppSecret',
			'tiktokClientKey',
			'tiktokClientSecret',
			'tiktokRedirectUri',
			'baseUrl',
			'removeOnUninstall',
		);

		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$current[ $key ] = $input[ $key ];
			}
		}

		$current['updatedAt'] = gmdate( DATE_ATOM );

		update_option( Installer::OPTION_SETTINGS, $current, false );
		if ( array_key_exists( 'removeOnUninstall', $current ) ) {
			update_option( Installer::OPTION_REMOVE_ON_UNINSTALL, (bool) $current['removeOnUninstall'], false );
		}

		return $this->normalize( $current );
	}

	/**
	 * @param array<string,mixed> $settings Stored settings.
	 * @return array<string,mixed>
	 */
	private function normalize( array $settings ): array {
		$normalized = array_merge( Installer::default_settings(), $settings );

		if ( 'SCHEDULED' === $normalized['defaultPostStatus'] ) {
			$normalized['defaultPostStatus'] = 'PUBLISHED';
		}

		$normalized['calendarWeekStart'] = (int) $normalized['calendarWeekStart'];
		$normalized['metaRedirectUri']   = rest_url( 'sms/v1/auth/meta/callback' );
		$normalized['removeOnUninstall'] = (bool) $normalized['removeOnUninstall'];
		$normalized['updatedAt']         = (string) ( $normalized['updatedAt'] ?? gmdate( DATE_ATOM ) );

		return $normalized;
	}
}
