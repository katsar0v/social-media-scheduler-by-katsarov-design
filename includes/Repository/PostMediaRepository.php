<?php
/**
 * Post media repository backed by WP attachments.
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

final class PostMediaRepository {
	/**
	 * @return list<array<string,mixed>>
	 */
	public function find_by_post_id( int $post_id ): array {
		global $wpdb;

		$table = Installer::table_name( 'sms_post_media' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d ORDER BY position ASC, created_at ASC, id ASC",
				$post_id
			),
			ARRAY_A
		);

		return array_map( array( $this, 'map_row' ), $rows ?: array() );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function attach( int $post_id, int $attachment_id, ?int $position = null ): array {
		global $wpdb;

		$table    = Installer::table_name( 'sms_post_media' );
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d AND attachment_id = %d",
				$post_id,
				$attachment_id
			),
			ARRAY_A
		);

		if ( null === $position ) {
			$position = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE post_id = %d",
					$post_id
				)
			);
		}

		if ( $existing ) {
			$this->update_position( (int) $existing['id'], $post_id, $position );
			$existing['position'] = (string) $position;

			return $this->map_row( $existing );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
				'position'      => $position,
				'created_at'    => self::now_mysql(),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Unable to attach media to post.' );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $wpdb->insert_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			throw new RuntimeException( 'Attached media row could not be loaded.' );
		}

		return $this->map_row( $row );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function detach( int $post_id, int $attachment_id ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_post_media' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d AND attachment_id = %d",
				$post_id,
				$attachment_id
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
	 * @return array<string,mixed>|null
	 */
	public function detach_by_id( int $post_id, int $id ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_post_media' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d AND id = %d",
				$post_id,
				$id
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
	 * @param list<int> $attachment_ids Attachment IDs in desired order.
	 * @return list<array<string,mixed>>
	 */
	public function reorder( int $post_id, array $attachment_ids ): array {
		global $wpdb;

		if ( count( $attachment_ids ) !== count( array_unique( $attachment_ids ) ) ) {
			throw new RuntimeException( 'Media order contains duplicate attachment IDs.' );
		}

		$table       = Installer::table_name( 'sms_post_media' );
		$existing_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT attachment_id FROM {$table} WHERE post_id = %d",
				$post_id
			)
		);
		$existing_ids = array_map( 'intval', $existing_ids ?: array() );

		sort( $existing_ids );
		$requested_ids = array_map( 'intval', $attachment_ids );
		sort( $requested_ids );

		if ( $existing_ids !== $requested_ids ) {
			throw new RuntimeException( 'Media order must include every attachment for the post.' );
		}

		foreach ( $attachment_ids as $position => $attachment_id ) {
			$wpdb->update(
				$table,
				array( 'position' => $position ),
				array(
					'post_id'       => $post_id,
					'attachment_id' => (int) $attachment_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}

		return $this->find_by_post_id( $post_id );
	}

	public function delete_by_post_id( int $post_id ): int {
		global $wpdb;

		$table = Installer::table_name( 'sms_post_media' );

		return (int) $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
	}

	private function update_position( int $id, int $post_id, int $position ): void {
		global $wpdb;

		$table = Installer::table_name( 'sms_post_media' );
		$wpdb->update(
			$table,
			array( 'position' => $position ),
			array(
				'id'      => $id,
				'post_id' => $post_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private function map_row( array $row ): array {
		$attachment_id = (int) $row['attachment_id'];
		$mime_type     = (string) get_post_mime_type( $attachment_id );
		$file          = get_attached_file( $attachment_id ) ?: '';
		$url           = wp_get_attachment_url( $attachment_id ) ?: '';
		$title         = (string) get_the_title( $attachment_id );

		return array(
			'id'           => (int) $row['id'],
			'postId'       => (int) $row['post_id'],
			'attachmentId' => $attachment_id,
			'type'         => str_starts_with( $mime_type, 'video/' ) ? 'video' : 'image',
			'filename'     => '' !== $file ? basename( $file ) : '',
			'originalName' => '' !== $title ? $title : ( '' !== $file ? basename( $file ) : '' ),
			'mimeType'     => $mime_type,
			'size'         => '' !== $file && is_readable( $file ) ? (int) filesize( $file ) : 0,
			'url'          => $url,
			'position'     => (int) $row['position'],
			'createdAt'    => self::mysql_to_iso( (string) $row['created_at'] ),
		);
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
