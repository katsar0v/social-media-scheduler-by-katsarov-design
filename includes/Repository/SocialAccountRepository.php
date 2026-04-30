<?php
/**
 * Social account repository with encrypted token storage.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Repository;

use KatsarovDesign\SocialMediaScheduler\Crypto;
use KatsarovDesign\SocialMediaScheduler\Installer;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SocialAccountRepository {
	/**
	 * @return list<array<string,mixed>>
	 */
	public function list(): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_social_account' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY connected_at DESC, id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( array( $this, 'map_row' ), $rows ?: array() );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function find_by_platform_and_provider_id( string $platform, string $provider_user_id ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_social_account' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE platform = %s AND provider_user_id = %s",
				$platform,
				$provider_user_id
			),
			ARRAY_A
		);

		return $row ? $this->map_row( $row ) : null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function find_by_id( int $id ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_social_account' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? $this->map_row( $row ) : null;
	}

	/**
	 * @param array<string,mixed> $input Account fields.
	 * @return array<string,mixed>
	 */
	public function upsert( array $input ): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_social_account' );
		$now   = self::now_mysql();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE platform = %s AND provider_user_id = %s",
				(string) $input['platform'],
				(string) $input['providerUserId']
			),
			ARRAY_A
		);

		if ( $row ) {
			$data = array(
				'account_name' => (string) $input['accountName'],
				'access_token' => Crypto::encrypt( (string) $input['accessToken'] ),
				'connected_at' => $now,
				'updated_at'   => $now,
			);
			$formats = array( '%s', '%s', '%s', '%s' );

			if ( array_key_exists( 'refreshToken', $input ) ) {
				$data['refresh_token'] = Crypto::encrypt( (string) $input['refreshToken'] );
				$formats[]             = '%s';
			}
			if ( array_key_exists( 'tokenExpiresAt', $input ) ) {
				$data['token_expires_at'] = null === $input['tokenExpiresAt'] ? null : self::input_to_mysql( (string) $input['tokenExpiresAt'] );
				$formats[]                = '%s';
			}
			if ( array_key_exists( 'scopes', $input ) ) {
				$data['scopes'] = (string) $input['scopes'];
				$formats[]      = '%s';
			}
			if ( array_key_exists( 'metadata', $input ) ) {
				$data['metadata'] = (string) $input['metadata'];
				$formats[]        = '%s';
			}

			$updated = $wpdb->update( $table, $data, array( 'id' => (int) $row['id'] ), $formats, array( '%d' ) );
			if ( false === $updated ) {
				throw new RuntimeException( 'Unable to update social account.' );
			}

			$account = $this->find_by_id( (int) $row['id'] );
			if ( null === $account ) {
				throw new RuntimeException( 'Updated social account could not be loaded.' );
			}

			return $account;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'platform'         => (string) $input['platform'],
				'provider_user_id' => (string) $input['providerUserId'],
				'account_name'     => (string) $input['accountName'],
				'access_token'     => Crypto::encrypt( (string) $input['accessToken'] ),
				'refresh_token'    => Crypto::encrypt( (string) ( $input['refreshToken'] ?? '' ) ),
				'token_expires_at' => empty( $input['tokenExpiresAt'] ) ? null : self::input_to_mysql( (string) $input['tokenExpiresAt'] ),
				'scopes'           => (string) ( $input['scopes'] ?? '' ),
				'connected_at'     => $now,
				'metadata'         => (string) ( $input['metadata'] ?? '{}' ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Unable to create social account.' );
		}

		$account = $this->find_by_id( (int) $wpdb->insert_id );
		if ( null === $account ) {
			throw new RuntimeException( 'Created social account could not be loaded.' );
		}

		return $account;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function delete( int $id ): ?array {
		global $wpdb;

		$account = $this->find_by_id( $id );
		if ( null === $account ) {
			return null;
		}

		$table = Installer::table_name( 'sms_social_account' );
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		return $account;
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private function map_row( array $row ): array {
		return array(
			'id'             => (int) $row['id'],
			'platform'       => (string) $row['platform'],
			'providerUserId' => (string) $row['provider_user_id'],
			'accountName'    => (string) $row['account_name'],
			'accessToken'    => Crypto::decrypt( (string) $row['access_token'] ),
			'refreshToken'   => Crypto::decrypt( (string) $row['refresh_token'] ),
			'tokenExpiresAt' => null === $row['token_expires_at'] ? null : self::mysql_to_iso( (string) $row['token_expires_at'] ),
			'scopes'         => (string) $row['scopes'],
			'connectedAt'    => self::mysql_to_iso( (string) $row['connected_at'] ),
			'metadata'       => (string) $row['metadata'],
			'createdAt'      => self::mysql_to_iso( (string) $row['created_at'] ),
			'updatedAt'      => self::mysql_to_iso( (string) $row['updated_at'] ),
		);
	}

	private static function input_to_mysql( string $value ): string {
		$date = new \DateTimeImmutable( $value );

		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	private static function now_mysql(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	private static function mysql_to_iso( string $value ): string {
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) );
		if ( false === $date ) {
			$date = new \DateTimeImmutable( $value );
		}

		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( DATE_ATOM );
	}
}
