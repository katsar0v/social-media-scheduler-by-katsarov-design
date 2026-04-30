<?php
/**
 * Scheduled post repository.
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

final class PostRepository {
	private PostMediaRepository $media_repository;
	private PublishResultRepository $publish_result_repository;

	public function __construct(
		?PostMediaRepository $media_repository = null,
		?PublishResultRepository $publish_result_repository = null
	) {
		$this->media_repository          = $media_repository ?? new PostMediaRepository();
		$this->publish_result_repository = $publish_result_repository ?? new PublishResultRepository();
	}

	/**
	 * @param array<string,mixed> $input Post fields.
	 * @return array<string,mixed>
	 */
	public function create( array $input ): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_post' );
		$now   = self::now_mysql();

		$inserted = $wpdb->insert(
			$table,
			array(
				'title'             => $this->nullable_trimmed_string( $input['title'] ?? null ),
				'caption'           => (string) ( $input['caption'] ?? '' ),
				'platform'          => (string) ( $input['platform'] ?? '' ),
				'social_account_id' => isset( $input['socialAccountId'] ) ? (int) $input['socialAccountId'] : null,
				'scheduled_at'      => self::input_to_mysql( (string) ( $input['scheduledAt'] ?? $now ) ),
				'status'            => (string) ( $input['status'] ?? 'DRAFT' ),
				'is_story'          => ! empty( $input['isStory'] ) ? 1 : 0,
				'notes'             => (string) ( $input['notes'] ?? '' ),
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Unable to create scheduled post.' );
		}

		$post = $this->find_by_id( (int) $wpdb->insert_id );
		if ( null === $post ) {
			throw new RuntimeException( 'Created post could not be loaded.' );
		}

		return $post;
	}

	/**
	 * @param array<string,mixed> $filter List filters.
	 * @return list<array<string,mixed>>
	 */
	public function list( array $filter = array() ): array {
		global $wpdb;

		$table        = Installer::table_name( 'sms_post' );
		$result_table = Installer::table_name( 'sms_publish_result' );
		$where        = array();
		$params       = array();

		if ( ! empty( $filter['status'] ) ) {
			$where[]  = 'p.status = %s';
			$params[] = (string) $filter['status'];
		}

		if ( ! empty( $filter['platform'] ) ) {
			$where[]  = 'p.platform = %s';
			$params[] = (string) $filter['platform'];
		}

		if ( ! empty( $filter['from'] ) ) {
			$where[]  = 'p.scheduled_at >= %s';
			$params[] = self::input_to_mysql( (string) $filter['from'] );
		}

		if ( ! empty( $filter['to'] ) ) {
			$where[]  = 'p.scheduled_at < %s';
			$params[] = self::input_to_mysql( (string) $filter['to'] );
		}

		if ( ! empty( $filter['excludeWithPublishResult'] ) ) {
			$where[] = "NOT EXISTS (
				SELECT 1 FROM {$result_table} pr
				WHERE pr.post_id = p.id
				AND pr.platform_post_id <> ''
				AND pr.status IN ('success', 'scheduled')
			)";
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql       = "SELECT p.* FROM {$table} p {$where_sql} ORDER BY p.scheduled_at ASC, p.id ASC";
		$rows      = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( array( $this, 'map_row' ), $rows ?: array() );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function find_by_id( int $id ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_post' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? $this->map_row( $row ) : null;
	}

	/**
	 * @param array<string,mixed> $input Fields to update.
	 * @return array<string,mixed>
	 */
	public function update( int $id, array $input ): array {
		global $wpdb;

		$table   = Installer::table_name( 'sms_post' );
		$data    = array( 'updated_at' => self::now_mysql() );
		$formats = array( '%s' );

		if ( array_key_exists( 'title', $input ) ) {
			$data['title'] = $this->nullable_trimmed_string( $input['title'] );
			$formats[]     = '%s';
		}
		if ( array_key_exists( 'caption', $input ) ) {
			$data['caption'] = (string) $input['caption'];
			$formats[]       = '%s';
		}
		if ( array_key_exists( 'platform', $input ) ) {
			$data['platform'] = (string) $input['platform'];
			$formats[]        = '%s';
		}
		if ( array_key_exists( 'socialAccountId', $input ) ) {
			$data['social_account_id'] = null === $input['socialAccountId'] ? null : (int) $input['socialAccountId'];
			$formats[]                 = '%d';
		}
		if ( array_key_exists( 'scheduledAt', $input ) ) {
			$data['scheduled_at'] = self::input_to_mysql( (string) $input['scheduledAt'] );
			$formats[]            = '%s';
		}
		if ( array_key_exists( 'status', $input ) ) {
			$data['status'] = (string) $input['status'];
			$formats[]      = '%s';
		}
		if ( array_key_exists( 'isStory', $input ) ) {
			$data['is_story'] = ! empty( $input['isStory'] ) ? 1 : 0;
			$formats[]        = '%d';
		}
		if ( array_key_exists( 'notes', $input ) ) {
			$data['notes'] = (string) $input['notes'];
			$formats[]     = '%s';
		}

		$updated = $wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		if ( false === $updated ) {
			throw new RuntimeException( 'Unable to update scheduled post.' );
		}

		$post = $this->find_by_id( $id );
		if ( null === $post ) {
			throw new RuntimeException( 'Scheduled post was not found.' );
		}

		return $post;
	}

	public function delete( int $id ): void {
		global $wpdb;

		$this->media_repository->delete_by_post_id( $id );
		$this->publish_result_repository->delete_by_post_id( $id );

		$table   = Installer::table_name( 'sms_post' );
		$deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		if ( false === $deleted ) {
			throw new RuntimeException( 'Unable to delete scheduled post.' );
		}
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private function map_row( array $row ): array {
		$id = (int) $row['id'];

		return array(
			'id'              => $id,
			'title'           => null === $row['title'] ? null : (string) $row['title'],
			'caption'         => (string) $row['caption'],
			'platform'        => (string) $row['platform'],
			'socialAccountId' => null === $row['social_account_id'] ? null : (int) $row['social_account_id'],
			'scheduledAt'     => self::mysql_to_iso( (string) $row['scheduled_at'] ),
			'status'          => (string) $row['status'],
			'isStory'         => (bool) $row['is_story'],
			'notes'           => (string) $row['notes'],
			'createdAt'       => self::mysql_to_iso( (string) $row['created_at'] ),
			'updatedAt'       => self::mysql_to_iso( (string) $row['updated_at'] ),
			'media'           => $this->media_repository->find_by_post_id( $id ),
			'publishResults'  => $this->publish_result_repository->find_by_post_id( $id ),
		);
	}

	private function nullable_trimmed_string( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$trimmed = trim( (string) $value );

		return '' === $trimmed ? null : $trimmed;
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
