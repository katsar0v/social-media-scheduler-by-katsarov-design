<?php
/**
 * OAuth token refresh service.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Repository\SettingsRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TokenRefreshService {
	private SocialAccountRepository $repository;
	private SettingsRepository $settings_repository;

	public function __construct( ?SocialAccountRepository $repository = null, ?SettingsRepository $settings_repository = null ) {
		$this->repository          = $repository ?? new SocialAccountRepository();
		$this->settings_repository = $settings_repository ?? new SettingsRepository();
	}

	/**
	 * @return array{checked:int,refreshed:int,failed:int}
	 */
	public function refresh_due_accounts(): array {
		$accounts  = $this->repository->list();
		$refreshed = 0;
		$failed    = 0;

		foreach ( $accounts as $account ) {
			if ( ! $this->needs_refresh( $account['tokenExpiresAt'] ?? null ) ) {
				continue;
			}

			try {
				if ( 'meta' === $account['platform'] ) {
					$this->refresh_meta( $account );
					++$refreshed;
				} elseif ( 'tiktok' === $account['platform'] ) {
					$this->refresh_tiktok( $account );
					++$refreshed;
				}
			} catch ( \Throwable $error ) {
				++$failed;
				error_log(
					sprintf(
						'[token-refresh] Failed to refresh %s account "%s" (id=%d): %s',
						(string) $account['platform'],
						(string) $account['accountName'],
						(int) $account['id'],
						$error->getMessage()
					)
				);
			}
		}

		return array(
			'checked'   => count( $accounts ),
			'refreshed' => $refreshed,
			'failed'    => $failed,
		);
	}

	/**
	 * @return array{checked:int,refreshed:int,failed:int}
	 */
	public function refresh_all(): array {
		return $this->refresh_due_accounts();
	}

	private function needs_refresh( mixed $expires_at ): bool {
		if ( empty( $expires_at ) ) {
			return false;
		}

		$expiry = strtotime( (string) $expires_at );
		if ( false === $expiry ) {
			return false;
		}

		return $expiry <= time() + HOUR_IN_SECONDS;
	}

	/**
	 * @param array<string,mixed> $account Social account.
	 */
	private function refresh_meta( array $account ): void {
		$settings = $this->settings_repository->get();
		if ( empty( $settings['metaAppId'] ) || empty( $settings['metaAppSecret'] ) ) {
			return;
		}

		$data = TokenExchange::refresh_meta_token(
			(string) $account['accessToken'],
			(string) $settings['metaAppId'],
			(string) $settings['metaAppSecret']
		);

		$this->repository->upsert(
			array(
				'platform'       => 'meta',
				'providerUserId' => (string) $account['providerUserId'],
				'accountName'    => (string) $account['accountName'],
				'accessToken'    => (string) $data['access_token'],
				'tokenExpiresAt' => ! empty( $data['expires_in'] ) ? gmdate( DATE_ATOM, time() + (int) $data['expires_in'] ) : null,
			)
		);
	}

	/**
	 * @param array<string,mixed> $account Social account.
	 */
	private function refresh_tiktok( array $account ): void {
		$settings = $this->settings_repository->get();
		if ( empty( $settings['tiktokClientKey'] ) || empty( $settings['tiktokClientSecret'] ) ) {
			return;
		}

		if ( empty( $account['refreshToken'] ) ) {
			throw new \RuntimeException( 'No refresh token available' );
		}

		$data = TokenExchange::refresh_tiktok_token(
			(string) $account['refreshToken'],
			(string) $settings['tiktokClientKey'],
			(string) $settings['tiktokClientSecret']
		);

		$this->repository->upsert(
			array(
				'platform'       => 'tiktok',
				'providerUserId' => (string) $account['providerUserId'],
				'accountName'    => (string) $account['accountName'],
				'accessToken'    => (string) $data['access_token'],
				'refreshToken'   => (string) ( $data['refresh_token'] ?? $account['refreshToken'] ),
				'tokenExpiresAt' => ! empty( $data['expires_in'] ) ? gmdate( DATE_ATOM, time() + (int) $data['expires_in'] ) : null,
			)
		);
	}
}
