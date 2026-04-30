<?php
/**
 * WordPress Media Library service.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Repository\PostMediaRepository;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaService {
	private PostMediaRepository $repository;

	public function __construct( ?PostMediaRepository $repository = null ) {
		$this->repository = $repository ?? new PostMediaRepository();
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function list_for_post( int $post_id ): array {
		return $this->repository->find_by_post_id( $post_id );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function attach( int $post_id, int $attachment_id ): array {
		$this->assert_attachment_exists( $attachment_id );

		return $this->repository->attach( $post_id, $attachment_id );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function detach( int $post_id, int $attachment_id ): ?array {
		return $this->repository->detach( $post_id, $attachment_id );
	}

	/**
	 * @param list<int> $attachment_ids Attachment IDs.
	 * @return list<array<string,mixed>>
	 */
	public function reorder( int $post_id, array $attachment_ids ): array {
		return $this->repository->reorder( $post_id, $attachment_ids );
	}

	public function public_url( int $attachment_id ): string {
		$this->assert_attachment_exists( $attachment_id );
		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			throw new RuntimeException( __( 'Attachment does not have a public URL.', 'social-media-scheduler' ) );
		}

		return $url;
	}

	public function publicUrl( int $attachment_id ): string {
		return $this->public_url( $attachment_id );
	}

	public function delete_attachment( int $attachment_id ): bool {
		$this->assert_attachment_exists( $attachment_id );

		return false !== wp_delete_attachment( $attachment_id, true );
	}

	public function prepare_for_instagram( int $attachment_id ): int {
		$this->assert_attachment_exists( $attachment_id );

		if ( 'image/png' !== get_post_mime_type( $attachment_id ) ) {
			return $attachment_id;
		}

		$source_path = get_attached_file( $attachment_id );
		if ( ! $source_path || ! is_readable( $source_path ) ) {
			throw new RuntimeException( __( 'Attachment source file is not readable.', 'social-media-scheduler' ) );
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			throw new RuntimeException( (string) $upload_dir['error'] );
		}

		$path_info = pathinfo( $source_path );
		$filename  = wp_unique_filename( (string) $path_info['dirname'], (string) $path_info['filename'] . '.jpg' );
		$target    = trailingslashit( (string) $path_info['dirname'] ) . $filename;

		$editor = wp_get_image_editor( $source_path );
		if ( is_wp_error( $editor ) ) {
			throw new RuntimeException( $editor->get_error_message() );
		}

		$saved = $editor->save( $target, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			throw new RuntimeException( $saved->get_error_message() );
		}

		$attachment = array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$new_id     = wp_insert_attachment( $attachment, $target );

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			throw new RuntimeException( __( 'Unable to create Instagram JPEG attachment.', 'social-media-scheduler' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( (int) $new_id, wp_generate_attachment_metadata( (int) $new_id, $target ) );

		return (int) $new_id;
	}

	public function prepareForInstagram( int $attachment_id ): int {
		return $this->prepare_for_instagram( $attachment_id );
	}

	private function assert_attachment_exists( int $attachment_id ): void {
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			throw new MediaNotFoundException(
				sprintf(
					/* translators: %d: WordPress media attachment ID. */
					__( 'Media item with ID %d was not found.', 'social-media-scheduler' ),
					$attachment_id
				)
			);
		}
	}
}
