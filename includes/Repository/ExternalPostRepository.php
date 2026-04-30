<?php
/**
 * External post repository.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Repository;

use KatsarovDesign\SocialMediaScheduler\Installer;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExternalPostRepository {
	/**
	 * @return list<array<string,mixed>>
	 */
	public function list(): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_external_post' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY published_at DESC, id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( array( $this, 'map_row' ), $rows ?: array() );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function list_by_month( int $year, int $month ): array {
		global $wpdb;

		$start = new \DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $month ), new \DateTimeZone( 'UTC' ) );
		$end   = $start->modify( '+1 month' );
		$table = Installer::table_name( 'sms_external_post' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE published_at >= %s AND published_at < %s ORDER BY published_at DESC, id DESC",
				$start->format( 'Y-m-d H:i:s' ),
				$end->format( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		return array_map( array( $this, 'map_row' ), $rows ?: array() );
	}

	/**
	 * @param array<string,mixed> $input External post fields.
	 * @return array<string,mixed>
	 */
	public function upsert( array $input ): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_external_post' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE platform = %s AND platform_post_id = %s",
				(string) $input['platform'],
				(string) $input['platformPostId']
			),
			ARRAY_A
		);

		$now  = self::now_mysql();
		$data = array(
			'platform'         => (string) $input['platform'],
			'account_id'       => (int) $input['accountId'],
			'platform_post_id' => (string) $input['platformPostId'],
			'content'          => (string) ( $input['content'] ?? '' ),
			'media_url'        => (string) ( $input['mediaUrl'] ?? '' ),
			'permalink'        => (string) ( $input['permalink'] ?? '' ),
			'published_at'     => self::input_to_mysql( (string) $input['publishedAt'] ),
			'metadata'         => (string) ( $input['metadata'] ?? '{}' ),
			'updated_at'       => $now,
		);

		if ( $row ) {
			$updated = $wpdb->update(
				$table,
				$data,
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				throw new RuntimeException( 'Unable to update external post.' );
			}

			$id = (int) $row['id'];
		} else {
			$data['created_at'] = $now;
			$inserted           = $wpdb->insert(
				$table,
				$data,
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				throw new RuntimeException( 'Unable to create external post.' );
			}

			$id = (int) $wpdb->insert_id;
		}

		$post = $this->find_by_id( $id );
		if ( null === $post ) {
			throw new RuntimeException( 'External post could not be loaded.' );
		}

		return $post;
	}

	public function delete_by_account_id( int $account_id ): int {
		global $wpdb;

		$table = Installer::table_name( 'sms_external_post' );

		return (int) $wpdb->delete( $table, array( 'account_id' => $account_id ), array( '%d' ) );
	}

	/**
	 * @param list<string> $present_ids Platform post IDs that should remain.
	 */
	public function delete_missing_for_account( int $account_id, array $present_ids ): int {
		global $wpdb;

		$table = Installer::table_name( 'sms_external_post' );

		if ( empty( $present_ids ) ) {
			return $this->delete_by_account_id( $account_id );
		}

		$placeholders = implode( ', ', array_fill( 0, count( $present_ids ), '%s' ) );
		$sql          = "DELETE FROM {$table} WHERE account_id = %d AND platform_post_id NOT IN ({$placeholders})";
		$params       = array_merge( array( $account_id ), array_values( $present_ids ) );

		return (int) $wpdb->query( $wpdb->prepare( $sql, ...$params ) );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function find_by_id( int $id ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_external_post' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? $this->map_row( $row ) : null;
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private function map_row( array $row ): array {
		return array(
			'id'             => (int) $row['id'],
			'platform'       => (string) $row['platform'],
			'accountId'      => (int) $row['account_id'],
			'platformPostId' => (string) $row['platform_post_id'],
			'content'        => (string) $row['content'],
			'mediaUrl'       => (string) $row['media_url'],
			'permalink'      => (string) $row['permalink'],
			'publishedAt'    => self::mysql_to_iso( (string) $row['published_at'] ),
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
