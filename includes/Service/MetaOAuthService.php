<?php
/**
 * Meta OAuth orchestration.
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

final class MetaOAuthService {
	private const TRANSIENT_PREFIX = 'sms_oauth_meta_state_';
	private const SCOPES = 'pages_manage_posts,instagram_basic,instagram_content_publish,pages_read_engagement,pages_read_user_content';

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
				'client_id'     => $config['appId'],
				'redirect_uri'  => $config['redirectUri'],
				'state'         => $state,
				'response_type' => 'code',
				'scope'         => self::SCOPES,
			),
			'https://www.facebook.com/v25.0/dialog/oauth'
		);
	}

	public function createAuthorizationUrl(): string {
		return $this->create_authorization_url();
	}

	/**
	 * @return list<array{id:int,platform:string,accountName:string}>
	 */
	public function handle_callback( string $code, ?string $state = null ): array {
		if ( null !== $state ) {
			$this->validate_state( $state );
		}

		$result   = TokenExchange::exchange_meta_code_for_page_token( $code, $this->config() );
		$accounts = array();

		foreach ( $result['pages'] as $page ) {
			$account = $this->repository->upsert(
				array(
					'platform'       => 'meta',
					'providerUserId' => $page['pageId'],
					'accountName'    => $page['pageName'],
					'accessToken'    => $page['pageAccessToken'],
					'tokenExpiresAt' => $result['expiresAt'],
					'scopes'         => self::SCOPES,
					'metadata'       => wp_json_encode(
						array(
							'fbPageId'            => $page['pageId'],
							'igBusinessAccountId' => $page['igBusinessAccountId'],
							'igUsername'          => $page['igUsername'],
						)
					),
				)
			);

			$accounts[] = array(
				'id'          => (int) $account['id'],
				'platform'    => (string) $account['platform'],
				'accountName' => (string) $account['accountName'],
			);
		}

		return $accounts;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function list_accounts(): array {
		return array_map(
			static fn ( array $account ): array => array(
				'id'             => $account['id'],
				'platform'       => $account['platform'],
				'accountName'    => $account['accountName'],
				'connectedAt'    => $account['connectedAt'],
				'tokenExpiresAt' => $account['tokenExpiresAt'],
				'metadata'       => $account['metadata'],
				'providerUserId' => $account['providerUserId'],
			),
			$this->repository->list()
		);
	}

	public function delete_account( int $id ): bool {
		return null !== $this->repository->delete( $id );
	}

	public function is_configured(): bool {
		$settings = $this->settings_repository->get();

		return '' !== trim( (string) $settings['metaAppId'] ) && '' !== trim( (string) $settings['metaAppSecret'] );
	}

	public static function redirect_uri(): string {
		return rest_url( 'sms/v1/auth/meta/callback' );
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
	 * @return array{appId:string,appSecret:string,redirectUri:string}
	 */
	private function config(): array {
		$settings = $this->settings_repository->get();
		$app_id   = trim( (string) $settings['metaAppId'] );
		$secret   = trim( (string) $settings['metaAppSecret'] );

		if ( '' === $app_id || '' === $secret ) {
			throw new RuntimeException( __( 'Meta App ID and App Secret must be configured before connecting an account.', 'social-media-scheduler' ) );
		}

		return array(
			'appId'       => $app_id,
			'appSecret'   => $secret,
			'redirectUri' => self::redirect_uri(),
		);
	}
}
