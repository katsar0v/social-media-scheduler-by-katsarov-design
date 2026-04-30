<?php
/**
 * Publish result repository.
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

final class PublishResultRepository {
	/**
	 * @param array<string,mixed> $input Result fields.
	 * @return array<string,mixed>
	 */
	public function create( array $input ): array {
		global $wpdb;

		$table        = Installer::table_name( 'sms_publish_result' );
		$status       = (string) ( $input['status'] ?? 'pending' );
		$published_at = null;
		if ( ! empty( $input['publishedAt'] ) ) {
			$published_at = self::input_to_mysql( (string) $input['publishedAt'] );
		} elseif ( 'success' === $status ) {
			$published_at = self::now_mysql();
		}

		$now      = self::now_mysql();
		$inserted = $wpdb->insert(
			$table,
			array(
				'post_id'          => (int) $input['postId'],
				'platform'         => (string) $input['platform'],
				'status'           => $status,
				'platform_post_id' => (string) ( $input['platformPostId'] ?? '' ),
				'permalink'        => $input['permalink'] ?? null,
				'published_at'     => $published_at,
				'error'            => (string) ( $input['error'] ?? '' ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Unable to create publish result.' );
		}

		$result = $this->find_by_id( (int) $wpdb->insert_id );
		if ( null === $result ) {
			throw new RuntimeException( 'Created publish result could not be loaded.' );
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $update Update fields.
	 * @return array<string,mixed>
	 */
	public function resolve_pending( int $post_id, string $platform, array $update ): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_publish_result' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d AND platform = %s AND status IN ('pending', 'scheduled') ORDER BY created_at DESC, id DESC LIMIT 1",
				$post_id,
				$platform
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return $this->create(
				array_merge(
					array(
						'postId'   => $post_id,
						'platform' => $platform,
					),
					$update
				)
			);
		}

		$status       = (string) $update['status'];
		$published_at = null;
		if ( array_key_exists( 'publishedAt', $update ) && null !== $update['publishedAt'] ) {
			$published_at = self::input_to_mysql( (string) $update['publishedAt'] );
		} elseif ( 'success' === $status ) {
			$published_at = self::now_mysql();
		} else {
			$published_at = $row['published_at'];
		}

		$wpdb->update(
			$table,
			array(
				'status'           => $status,
				'platform_post_id' => (string) ( $update['platformPostId'] ?? $row['platform_post_id'] ),
				'permalink'        => array_key_exists( 'permalink', $update ) ? $update['permalink'] : $row['permalink'],
				'published_at'     => $published_at,
				'error'            => (string) ( $update['error'] ?? $row['error'] ),
				'updated_at'       => self::now_mysql(),
			),
			array( 'id' => (int) $row['id'] ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$result = $this->find_by_id( (int) $row['id'] );
		if ( null === $result ) {
			throw new RuntimeException( 'Updated publish result could not be loaded.' );
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $input Failure fields.
	 * @return array<string,mixed>
	 */
	public function create_failure( array $input ): array {
		$input['status'] = 'failed';

		return $this->create( $input );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function delete_scheduled( int $post_id, string $platform ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_publish_result' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d AND platform = %s AND status = 'scheduled' ORDER BY created_at DESC, id DESC LIMIT 1",
				$post_id,
				$platform
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$wpdb->delete( $table, array( 'id' => (int) $row['id'] ), array( '%d' ) );

		return $this->map_row( $row );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function find_by_post_id( int $post_id ): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_publish_result' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d ORDER BY created_at DESC, id DESC",
				$post_id
			),
			ARRAY_A
		);

		return array_map( array( $this, 'map_row' ), $rows ?: array() );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function find_all_successful(): array {
		return $this->find_by_status( 'success' );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function find_all_scheduled(): array {
		return $this->find_by_status( 'scheduled' );
	}

	/**
	 * @return list<string>
	 */
	public function find_tracked_platform_post_ids(): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_publish_result' );
		$ids   = $wpdb->get_col(
			"SELECT platform_post_id FROM {$table} WHERE status IN ('success', 'scheduled') AND platform_post_id <> ''"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_values( array_unique( array_map( 'strval', $ids ?: array() ) ) );
	}

	/**
	 * @return list<string>
	 */
	public function find_tracked_permalinks(): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_publish_result' );
		$urls  = $wpdb->get_col(
			"SELECT permalink FROM {$table} WHERE status IN ('success', 'scheduled') AND permalink IS NOT NULL AND permalink <> ''"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_values( array_unique( array_map( 'strval', $urls ?: array() ) ) );
	}

	public function reconcile_by_platform_post_id( string $platform_post_id, string $permalink ): void {
		global $wpdb;

		$table  = Installer::table_name( 'sms_publish_result' );
		$suffix = str_contains( $platform_post_id, '_' ) ? (string) array_slice( explode( '_', $platform_post_id ), -1 )[0] : $platform_post_id;
		$rows   = $wpdb->get_results(
			"SELECT id, platform_post_id, status FROM {$table} WHERE status IN ('scheduled', 'success') AND (permalink IS NULL OR permalink = '') AND platform_post_id <> ''",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $rows ?: array() as $row ) {
			$stored = (string) $row['platform_post_id'];
			$stored_suffix = str_contains( $stored, '_' ) ? (string) array_slice( explode( '_', $stored ), -1 )[0] : $stored;
			if ( $stored !== $platform_post_id && $stored_suffix !== $suffix ) {
				continue;
			}

			$data = array(
				'permalink'  => $permalink,
				'status'     => 'success',
				'updated_at' => self::now_mysql(),
			);
			$formats = array( '%s', '%s', '%s' );

			if ( 'success' !== $row['status'] ) {
				$data['published_at'] = self::now_mysql();
				$formats[]            = '%s';
			}

			$wpdb->update( $table, $data, array( 'id' => (int) $row['id'] ), $formats, array( '%d' ) );
			return;
		}
	}

	public function delete_by_post_id( int $post_id ): int {
		global $wpdb;

		$table = Installer::table_name( 'sms_publish_result' );

		return (int) $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function find_by_id( int $id ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_publish_result' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? $this->map_row( $row ) : null;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function find_by_status( string $status ): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_publish_result' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC, id DESC",
				$status
			),
			ARRAY_A
		);

		return array_map( array( $this, 'map_row' ), $rows ?: array() );
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private function map_row( array $row ): array {
		return array(
			'id'             => (int) $row['id'],
			'postId'         => (int) $row['post_id'],
			'platform'       => (string) $row['platform'],
			'status'         => (string) $row['status'],
			'platformPostId' => (string) $row['platform_post_id'],
			'permalink'      => null === $row['permalink'] ? null : (string) $row['permalink'],
			'publishedAt'    => null === $row['published_at'] ? null : self::mysql_to_iso( (string) $row['published_at'] ),
			'error'          => (string) $row['error'],
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
