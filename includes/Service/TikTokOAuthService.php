<?php
/**
 * TikTok OAuth orchestration.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Repository\SettingsRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TikTokOAuthService {
	private const TRANSIENT_PREFIX = 'sms_oauth_tiktok_state_';

	private SocialAccountRepository $repository;
	private SettingsRepository $settings_repository;

	public function __construct( ?SocialAccountRepository $repository = null, ?SettingsRepository $settings_repository = null ) {
		$this->repository          = $repository ?? new SocialAccountRepository();
		$this->settings_repository = $settings_repository ?? new SettingsRepository();
	}

	public function create_authorization_url(): string {
		$config = $this->config();
		$state  = $this->create_state();

		return add_query_arg(
			array(
				'client_key'    => $config['clientKey'],
				'redirect_uri'  => $config['redirectUri'],
				'state'         => $state,
				'response_type' => 'code',
				'scope'         => 'user.info.basic,video.publish',
			),
			'https://www.tiktok.com/v2/auth/authorize/'
		);
	}

	public function createAuthorizationUrl(): string {
		return $this->create_authorization_url();
	}

	/**
	 * @return array{id:int,platform:string,accountName:string}
	 */
	public function handle_callback( string $code, ?string $state = null ): array {
		if ( null !== $state ) {
			$this->validate_state( $state );
		}

		$result  = TokenExchange::exchange_tiktok_code_for_token( $code, $this->config() );
		$account = $this->repository->upsert(
			array(
				'platform'       => 'tiktok',
				'providerUserId' => $result['openId'],
				'accountName'    => $result['username'] ?: $result['openId'],
				'accessToken'    => $result['accessToken'],
				'refreshToken'   => $result['refreshToken'] ?? '',
				'tokenExpiresAt' => $result['expiresAt'],
				'scopes'         => 'video.publish',
				'metadata'       => wp_json_encode( array( 'openId' => $result['openId'] ) ),
			)
		);

		return array(
			'id'          => (int) $account['id'],
			'platform'    => (string) $account['platform'],
			'accountName' => (string) $account['accountName'],
		);
	}

	public function is_configured(): bool {
		$settings = $this->settings_repository->get();

		return '' !== trim( (string) $settings['tiktokClientKey'] ) && '' !== trim( (string) $settings['tiktokClientSecret'] );
	}

	private function create_state(): string {
		$state = wp_generate_password( 32, false, false );
		set_transient( self::TRANSIENT_PREFIX . $state, 1, 10 * MINUTE_IN_SECONDS );

		return $state;
	}

	private function validate_state( string $state ): void {
		$key = self::TRANSIENT_PREFIX . $state;
		if ( ! get_transient( $key ) ) {
			throw new RuntimeException( __( 'OAuth state is invalid or expired.', 'social-media-scheduler' ) );
		}

		delete_transient( $key );
	}

	/**
	 * @return array{clientKey:string,clientSecret:string,redirectUri:string}
	 */
	private function config(): array {
		$settings = $this->settings_repository->get();
		$client_key = trim( (string) $settings['tiktokClientKey'] );
		$secret     = trim( (string) $settings['tiktokClientSecret'] );

		if ( '' === $client_key || '' === $secret ) {
			throw new RuntimeException( __( 'TikTok Client Key and Client Secret must be configured before connecting an account.', 'social-media-scheduler' ) );
		}

		return array(
			'clientKey'    => $client_key,
			'clientSecret' => $secret,
			'redirectUri'  => (string) ( $settings['tiktokRedirectUri'] ?: rest_url( 'sms/v1/auth/tiktok/callback' ) ),
		);
	}
}
