<?php
/**
 * Post validation and lifecycle service.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Domain\Platform;
use KatsarovDesign\SocialMediaScheduler\Domain\PostStatus;
use KatsarovDesign\SocialMediaScheduler\Repository\PostMediaRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostService {
	private const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'video/mp4',
		'video/quicktime',
		'video/webm',
	);

	private const INSTAGRAM_IMAGE_MIME_TYPES = array( 'image/jpeg' );

	private PostRepository $repository;
	private PostMediaRepository $media_repository;
	private ?SocialAccountRepository $social_account_repository;

	public function __construct(
		?PostRepository $repository = null,
		?SocialAccountRepository $social_account_repository = null,
		?PostMediaRepository $media_repository = null
	) {
		$this->repository                = $repository ?? new PostRepository();
		$this->social_account_repository = $social_account_repository;
		$this->media_repository          = $media_repository ?? new PostMediaRepository();
	}

	/**
	 * @param array<string,mixed> $input Post input.
	 * @return array<string,mixed>
	 */
	public function create( array $input ): array {
		return $this->repository->create( $this->normalize_create_input( $input ) );
	}

	/**
	 * @param array<string,mixed> $filter Post filter.
	 * @return list<array<string,mixed>>
	 */
	public function list( array $filter = array() ): array {
		return $this->repository->list( $this->normalize_filter( $filter ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function find_by_id( int $id ): array {
		$post = $this->repository->find_by_id( $id );
		if ( null === $post ) {
			throw new PostNotFoundException(
				sprintf(
					/* translators: %d: scheduled post ID. */
					__( 'Post with ID %d was not found.', 'social-media-scheduler' ),
					$id
				)
			);
		}

		return $post;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function findById( int $id ): array {
		return $this->find_by_id( $id );
	}

	/**
	 * @param array<string,mixed> $input Post input.
	 * @return array<string,mixed>
	 */
	public function update( int $id, array $input ): array {
		$existing = $this->find_by_id( $id );
		$normalized = $this->normalize_update_input( $input, $existing );

		return $this->repository->update( $id, $normalized );
	}

	public function delete( int $id ): void {
		$this->find_by_id( $id );
		$this->repository->delete( $id );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function add_media( int $post_id, int $attachment_id ): array {
		$post = $this->find_by_id( $post_id );
		$media = $this->media_input_from_attachment( $attachment_id );

		if ( ! in_array( $media['mimeType'], self::ALLOWED_MIME_TYPES, true ) ) {
			throw new PostValidationException(
				sprintf(
					/* translators: 1: uploaded file MIME type, 2: comma-separated list of allowed MIME types. */
					__( 'Unsupported file type: %1$s. Allowed types: %2$s', 'social-media-scheduler' ),
					$media['mimeType'],
					implode( ', ', self::ALLOWED_MIME_TYPES )
				)
			);
		}

		if ( Platform::is_video_only( (string) $post['platform'] ) && 'video' !== $media['type'] ) {
			throw new PostValidationException( __( 'TikTok posts only support video media.', 'social-media-scheduler' ) );
		}

		if ( ! empty( $post['isStory'] ) && 'video' !== $media['type'] ) {
			throw new PostValidationException( __( 'Story posts only support video media.', 'social-media-scheduler' ) );
		}

		$this->assert_media_compatible_with_post( $post, $media );

		return $this->media_repository->attach( $post_id, $attachment_id );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function addMedia( int $post_id, int $attachment_id ): array {
		return $this->add_media( $post_id, $attachment_id );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function remove_media( int $post_id, int $media_id ): ?array {
		$this->find_by_id( $post_id );

		return $this->media_repository->detach_by_id( $post_id, $media_id );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function removeMedia( int $post_id, int $media_id ): ?array {
		return $this->remove_media( $post_id, $media_id );
	}

	/**
	 * @param list<int> $attachment_ids Attachment IDs.
	 * @return list<array<string,mixed>>
	 */
	public function reorder_media( int $post_id, array $attachment_ids ): array {
		$this->find_by_id( $post_id );

		foreach ( $attachment_ids as $attachment_id ) {
			if ( ! is_int( $attachment_id ) || $attachment_id < 0 ) {
				throw new PostValidationException( __( 'Attachment IDs must be an array of non-negative integers.', 'social-media-scheduler' ) );
			}
		}

		return $this->media_repository->reorder( $post_id, $attachment_ids );
	}

	/**
	 * @param list<int> $attachment_ids Attachment IDs.
	 * @return list<array<string,mixed>>
	 */
	public function reorderMedia( int $post_id, array $attachment_ids ): array {
		return $this->reorder_media( $post_id, $attachment_ids );
	}

	/**
	 * @param array<string,mixed> $input Post input.
	 * @return array<string,mixed>
	 */
	private function normalize_create_input( array $input ): array {
		$is_story = true === ( $input['isStory'] ?? false );
		$caption  = trim( (string) ( $input['caption'] ?? '' ) );
		$platform = trim( (string) ( $input['platform'] ?? '' ) );
		$notes    = trim( (string) ( $input['notes'] ?? '' ) );
		$title    = trim( (string) ( $input['title'] ?? '' ) );

		if ( ! $is_story && '' === $caption ) {
			throw new PostValidationException( __( 'Caption is required.', 'social-media-scheduler' ) );
		}

		$this->assert_platform( $platform, __( 'Platform is required.', 'social-media-scheduler' ) );
		$this->assert_story_supported( $platform, $is_story );

		$social_account_id = (int) ( $input['socialAccountId'] ?? 0 );
		$this->assert_social_account( $social_account_id );

		$scheduled_at = $this->normalize_date( $input['scheduledAt'] ?? null, __( 'Scheduled date must be a valid ISO 8601 date string.', 'social-media-scheduler' ) );
		$status       = $this->normalize_creatable_status( $input['status'] ?? null ) ?? PostStatus::DRAFT->value;

		return array(
			'title'           => '' === $title ? null : $title,
			'caption'         => $is_story ? '' : $caption,
			'platform'        => $platform,
			'socialAccountId' => $social_account_id,
			'scheduledAt'     => $scheduled_at,
			'status'          => $this->resolve_status( $scheduled_at, $status ),
			'isStory'         => $is_story,
			'notes'           => $notes,
		);
	}

	/**
	 * @param array<string,mixed> $input Post input.
	 * @param array<string,mixed> $existing Existing post.
	 * @return array<string,mixed>
	 */
	private function normalize_update_input( array $input, array $existing ): array {
		$normalized = array();

		if ( array_key_exists( 'title', $input ) ) {
			$title = trim( (string) $input['title'] );
			$normalized['title'] = '' === $title ? null : $title;
		}

		if ( array_key_exists( 'caption', $input ) ) {
			$effective_is_story = array_key_exists( 'isStory', $input ) ? true === $input['isStory'] : (bool) $existing['isStory'];
			$caption            = trim( (string) $input['caption'] );
			if ( '' === $caption && ! $effective_is_story ) {
				throw new PostValidationException( __( 'Caption cannot be empty.', 'social-media-scheduler' ) );
			}
			$normalized['caption'] = $effective_is_story ? '' : $caption;
		}

		if ( array_key_exists( 'isStory', $input ) ) {
			$normalized['isStory'] = true === $input['isStory'];
			$effective_platform    = array_key_exists( 'platform', $input ) ? trim( (string) $input['platform'] ) : (string) $existing['platform'];
			$this->assert_story_supported( $effective_platform, $normalized['isStory'] );
			if ( $normalized['isStory'] && ! array_key_exists( 'caption', $normalized ) ) {
				$normalized['caption'] = '';
			}
		}

		if ( array_key_exists( 'platform', $input ) ) {
			$platform = trim( (string) $input['platform'] );
			$this->assert_platform( $platform, __( 'Platform cannot be empty.', 'social-media-scheduler' ) );
			$effective_is_story = array_key_exists( 'isStory', $input ) ? true === $input['isStory'] : (bool) $existing['isStory'];
			$this->assert_story_supported( $platform, $effective_is_story );
			$normalized['platform'] = $platform;
		}

		if ( array_key_exists( 'socialAccountId', $input ) ) {
			$social_account_id = (int) $input['socialAccountId'];
			$this->assert_social_account( $social_account_id );
			$normalized['socialAccountId'] = $social_account_id;
		}

		if ( array_key_exists( 'scheduledAt', $input ) ) {
			$normalized['scheduledAt'] = $this->normalize_date( $input['scheduledAt'], __( 'Scheduled date must be a valid ISO 8601 date string.', 'social-media-scheduler' ) );
		}

		$status_changed   = array_key_exists( 'status', $input );
		$schedule_changed = array_key_exists( 'scheduledAt', $normalized );
		if ( $status_changed || $schedule_changed ) {
			$requested_status = $status_changed
				? ( $this->normalize_creatable_status( $input['status'] ?? null ) ?? PostStatus::DRAFT->value )
				: $this->requested_status_from_existing_post( $existing );
			$target_status = $this->resolve_status( $normalized['scheduledAt'] ?? (string) $existing['scheduledAt'], $requested_status );
			if ( ! PostStatus::can_transition( (string) $existing['status'], $target_status ) ) {
				throw new PostValidationException(
					sprintf(
						/* translators: 1: current post status, 2: requested post status. */
						__( 'Status transition %1$s -> %2$s is not allowed.', 'social-media-scheduler' ),
						$existing['status'],
						$target_status
					)
				);
			}
			$normalized['status'] = $target_status;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$normalized['notes'] = trim( (string) $input['notes'] );
		}

		$effective_post = array_merge(
			$existing,
			array(
				'platform' => $normalized['platform'] ?? $existing['platform'],
				'isStory' => $normalized['isStory'] ?? $existing['isStory'],
			)
		);

		foreach ( $existing['media'] ?? array() as $media ) {
			$this->assert_media_compatible_with_post( $effective_post, $media );
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $filter Raw filter.
	 * @return array<string,mixed>
	 */
	private function normalize_filter( array $filter ): array {
		$normalized = array();

		if ( array_key_exists( 'status', $filter ) && null !== $filter['status'] && '' !== $filter['status'] ) {
			$status = (string) $filter['status'];
			if ( ! PostStatus::is_valid( $status ) ) {
				throw new PostValidationException(
					sprintf(
						/* translators: %s: comma-separated list of allowed post statuses. */
						__( 'Status must be one of: %s', 'social-media-scheduler' ),
						implode( ', ', PostStatus::values() )
					)
				);
			}
			$normalized['status'] = $status;
		}

		if ( array_key_exists( 'platform', $filter ) && null !== $filter['platform'] && '' !== $filter['platform'] ) {
			$platform = trim( (string) $filter['platform'] );
			$this->assert_platform( $platform, __( 'Platform cannot be empty.', 'social-media-scheduler' ) );
			$normalized['platform'] = $platform;
		}

		$has_from = ! empty( $filter['from'] );
		$has_to   = ! empty( $filter['to'] );
		if ( $has_from xor $has_to ) {
			throw new PostValidationException( __( 'Start and end dates must be provided together.', 'social-media-scheduler' ) );
		}
		if ( $has_from && $has_to ) {
			$from = $this->normalize_date( $filter['from'], __( 'Start and end dates must be valid ISO 8601 date strings.', 'social-media-scheduler' ) );
			$to   = $this->normalize_date( $filter['to'], __( 'Start and end dates must be valid ISO 8601 date strings.', 'social-media-scheduler' ) );
			if ( strtotime( $from ) >= strtotime( $to ) ) {
				throw new PostValidationException( __( 'Start date must be earlier than end date.', 'social-media-scheduler' ) );
			}
			$normalized['from'] = $from;
			$normalized['to']   = $to;
		}

		if ( ! empty( $filter['excludeWithPublishResult'] ) ) {
			$normalized['excludeWithPublishResult'] = true;
		}

		return $normalized;
	}

	private function assert_platform( string $platform, string $empty_message ): void {
		if ( '' === $platform ) {
			throw new PostValidationException( $empty_message );
		}

		if ( ! Platform::is_valid( $platform ) ) {
			throw new PostValidationException(
				sprintf(
					/* translators: %s: comma-separated list of allowed social platforms. */
					__( 'Platform must be one of: %s', 'social-media-scheduler' ),
					implode( ', ', Platform::values() )
				)
			);
		}
	}

	private function assert_story_supported( string $platform, bool $is_story ): void {
		if ( $is_story && ! Platform::supports_stories( $platform ) ) {
			throw new PostValidationException(
				sprintf(
					/* translators: %s: comma-separated list of platforms that support story posts. */
					__( 'Stories are only supported on: %s', 'social-media-scheduler' ),
					implode( ', ', Platform::story_values() )
				)
			);
		}
	}

	private function assert_social_account( int $social_account_id ): void {
		if ( $social_account_id <= 0 ) {
			throw new PostValidationException( __( 'Social account ID must be a positive integer.', 'social-media-scheduler' ) );
		}

		if ( null !== $this->social_account_repository && null === $this->social_account_repository->find_by_id( $social_account_id ) ) {
			throw new PostValidationException( __( 'Social account ID must reference a connected account.', 'social-media-scheduler' ) );
		}
	}

	private function normalize_date( mixed $value, string $message ): string {
		if ( empty( $value ) || ! is_scalar( $value ) ) {
			throw new PostValidationException( $message );
		}

		try {
			$date = new \DateTimeImmutable( (string) $value );
		} catch ( \Exception ) {
			throw new PostValidationException( $message );
		}

		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( DATE_ATOM );
	}

	private function normalize_creatable_status( mixed $status ): ?string {
		if ( null === $status || '' === $status ) {
			return null;
		}

		$status = (string) $status;
		if ( ! in_array( $status, PostStatus::creatable_values(), true ) ) {
			throw new PostValidationException(
				sprintf(
					/* translators: %s: comma-separated list of allowed post statuses. */
					__( 'Status must be one of: %s', 'social-media-scheduler' ),
					implode( ', ', PostStatus::creatable_values() )
				)
			);
		}

		return $status;
	}

	/**
	 * @param array<string,mixed> $post Existing post.
	 */
	private function requested_status_from_existing_post( array $post ): string {
		return match ( (string) $post['status'] ) {
			PostStatus::SCHEDULED->value, PostStatus::PUBLISHED->value => PostStatus::PUBLISHED->value,
			PostStatus::FAILED->value => PostStatus::APPROVED->value,
			default => (string) $post['status'],
		};
	}

	private function resolve_status( string $scheduled_at, string $requested_status ): string {
		return PostStatus::PUBLISHED->value === $requested_status && strtotime( $scheduled_at ) > time()
			? PostStatus::SCHEDULED->value
			: $requested_status;
	}

	/**
	 * @param array<string,mixed> $post Post data.
	 * @param array<string,mixed> $media Media data.
	 */
	private function assert_media_compatible_with_post( array $post, array $media ): void {
		if (
			'instagram' === (string) $post['platform']
			&& empty( $post['isStory'] )
			&& 'image' === (string) $media['type']
			&& ! in_array( (string) $media['mimeType'], self::INSTAGRAM_IMAGE_MIME_TYPES, true )
		) {
			throw new PostValidationException( __( 'Instagram image posts require JPEG media. Convert unsupported image formats to JPG before uploading.', 'social-media-scheduler' ) );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function media_input_from_attachment( int $attachment_id ): array {
		$mime_type = (string) get_post_mime_type( $attachment_id );

		return array(
			'type'     => str_starts_with( $mime_type, 'video/' ) ? 'video' : 'image',
			'mimeType' => $mime_type,
		);
	}
}
