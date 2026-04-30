<?php
/**
 * Publish orchestration service.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PublishResultRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use KatsarovDesign\SocialMediaScheduler\Service\Publish\MetaPublisher;
use KatsarovDesign\SocialMediaScheduler\Service\Publish\PublishError;
use KatsarovDesign\SocialMediaScheduler\Service\Publish\TikTokPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PublishService {
	private PostRepository $post_repository;
	private SocialAccountRepository $social_account_repository;
	private PublishResultRepository $publish_result_repository;
	private MetaPublisher $meta_publisher;
	private TikTokPublisher $tiktok_publisher;

	public function __construct(
		?PostRepository $post_repository = null,
		?SocialAccountRepository $social_account_repository = null,
		?PublishResultRepository $publish_result_repository = null,
		?MetaPublisher $meta_publisher = null,
		?TikTokPublisher $tiktok_publisher = null
	) {
		$this->post_repository           = $post_repository ?? new PostRepository();
		$this->social_account_repository = $social_account_repository ?? new SocialAccountRepository();
		$this->publish_result_repository = $publish_result_repository ?? new PublishResultRepository();
		$this->meta_publisher            = $meta_publisher ?? new MetaPublisher();
		$this->tiktok_publisher          = $tiktok_publisher ?? new TikTokPublisher();
	}

	/**
	 * @param array{postId:int,targetPlatforms:list<string>} $options Publish options.
	 * @return list<array{platform:string,platformPostId:string,isScheduled:bool,error?:string}>
	 */
	public function publish_to_meta( array $options ): array {
		$post = $this->post_repository->find_by_id( (int) $options['postId'] );
		if ( null === $post ) {
			throw new PublishError( __( 'Post was not found.', 'social-media-scheduler' ), 404 );
		}

		$account = $this->find_account_for_post( $post, 'meta' );
		$meta    = json_decode( (string) $account['metadata'], true );
		$meta    = is_array( $meta ) ? $meta : array();

		$page_token     = (string) $account['accessToken'];
		$page_id        = (string) ( $meta['fbPageId'] ?? '' );
		$ig_business_id = (string) ( $meta['igBusinessAccountId'] ?? '' );
		if ( '' === $page_id || '' === $page_token ) {
			throw new PublishError( __( 'Meta account is missing a page ID or access token.', 'social-media-scheduler' ), 400 );
		}

		$results = array();
		$is_future = ! empty( $post['scheduledAt'] ) && strtotime( (string) $post['scheduledAt'] ) > time();

		foreach ( $options['targetPlatforms'] as $target ) {
			$input = array( 'postId' => (int) $post['id'], 'platform' => $target );
			try {
				$platform_post_id = '';
				$is_scheduled     = false;
				$permalink        = null;

				if ( 'facebook' === $target ) {
					if ( ! empty( $post['isStory'] ) ) {
						if ( $is_future ) {
							$this->ensure_pending_result( $input );
							$results[] = array( 'platform' => $target, 'platformPostId' => '', 'isScheduled' => true );
							continue;
						}

						$fb = $this->meta_publisher->publish_facebook_story( $post, $page_id, $page_token );
						$platform_post_id = $fb['id'];
						$permalink        = $fb['permalink'];
						$this->publish_result_repository->resolve_pending(
							(int) $post['id'],
							$target,
							array(
								'status'         => 'success',
								'platformPostId' => $platform_post_id,
								'permalink'      => $permalink,
								'publishedAt'    => gmdate( DATE_ATOM ),
							)
						);
						$results[] = array( 'platform' => $target, 'platformPostId' => $platform_post_id, 'isScheduled' => false );
						continue;
					}

					$existing_scheduled = $this->find_scheduled_result( (int) $post['id'], 'facebook' );
					$fb = $this->meta_publisher->publish_to_facebook( $post, $page_id, $page_token, (string) $post['scheduledAt'] );
					$platform_post_id = $fb['id'];
					$is_scheduled     = $fb['isScheduled'];
					$permalink        = $fb['permalink'];

					if ( null !== $existing_scheduled ) {
						$this->meta_publisher->delete_post( (string) $existing_scheduled['platformPostId'], $page_token );
						$this->publish_result_repository->delete_scheduled( (int) $post['id'], 'facebook' );
					}

					$this->publish_result_repository->create(
						array_merge(
							$input,
							array(
								'status'         => $is_scheduled ? 'scheduled' : 'success',
								'platformPostId' => $platform_post_id,
								'permalink'      => $permalink,
								'publishedAt'    => $is_scheduled ? null : gmdate( DATE_ATOM ),
							)
						)
					);
					$results[] = array( 'platform' => $target, 'platformPostId' => $platform_post_id, 'isScheduled' => $is_scheduled );
					continue;
				}

				if ( 'instagram' !== $target ) {
					throw new PublishError( __( 'Unsupported Meta target platform.', 'social-media-scheduler' ), 400 );
				}

				if ( '' === $ig_business_id ) {
					throw new PublishError( __( 'Instagram Business Account ID is not configured.', 'social-media-scheduler' ), 400 );
				}

				if ( ! empty( $post['isStory'] ) ) {
					if ( $is_future ) {
						$this->ensure_pending_result( $input );
						$results[] = array( 'platform' => $target, 'platformPostId' => '', 'isScheduled' => true );
						continue;
					}

					$ig = $this->meta_publisher->publish_instagram_story( $post, $ig_business_id, $page_token );
				} elseif ( $is_future ) {
					$this->ensure_pending_result( $input );
					$results[] = array( 'platform' => $target, 'platformPostId' => '', 'isScheduled' => true );
					continue;
				} else {
					$ig = $this->meta_publisher->publish_to_instagram( $post, $ig_business_id, $page_token );
				}

				$platform_post_id = $ig['id'];
				$permalink        = $ig['permalink'];
				$this->publish_result_repository->resolve_pending(
					(int) $post['id'],
					$target,
					array(
						'status'         => 'success',
						'platformPostId' => $platform_post_id,
						'permalink'      => $permalink,
						'publishedAt'    => gmdate( DATE_ATOM ),
					)
				);
				$results[] = array( 'platform' => $target, 'platformPostId' => $platform_post_id, 'isScheduled' => false );
			} catch ( \Throwable $error ) {
				$error_message = $error->getMessage();
				$has_scheduled = $this->has_tracked_scheduled_result( (int) $post['id'], $target );
				if ( $has_scheduled ) {
					$this->publish_result_repository->create_failure( array_merge( $input, array( 'error' => $error_message ) ) );
				} else {
					$this->publish_result_repository->resolve_pending( (int) $post['id'], $target, array( 'status' => 'failed', 'error' => $error_message ) );
				}
				$results[] = array( 'platform' => $target, 'platformPostId' => '', 'isScheduled' => false, 'error' => $error_message );
			}
		}

		$this->update_post_status_after_results( (int) $post['id'], $results );

		return $results;
	}

	public function publishToMeta( array $options ): array {
		return $this->publish_to_meta( $options );
	}

	/**
	 * @param array{postId:int} $options Publish options.
	 * @return array{platform:string,platformPostId:string}
	 */
	public function publish_to_tiktok( array $options ): array {
		$post = $this->post_repository->find_by_id( (int) $options['postId'] );
		if ( null === $post ) {
			throw new PublishError( __( 'Post was not found.', 'social-media-scheduler' ), 404 );
		}

		$account = $this->find_account_for_post( $post, 'tiktok' );
		$input   = array( 'postId' => (int) $post['id'], 'platform' => 'tiktok' );

		if ( ! empty( $post['scheduledAt'] ) && strtotime( (string) $post['scheduledAt'] ) > time() ) {
			$this->publish_result_repository->create( array_merge( $input, array( 'status' => 'pending' ) ) );
			$this->post_repository->update( (int) $post['id'], array( 'status' => 'SCHEDULED' ) );

			return array( 'platform' => 'tiktok', 'platformPostId' => '' );
		}

		try {
			$result = $this->tiktok_publisher->publish( $post, (string) $account['accessToken'] );
			$this->publish_result_repository->resolve_pending(
				(int) $post['id'],
				'tiktok',
				array(
					'status'         => 'success',
					'platformPostId' => $result['platformPostId'],
					'permalink'      => $result['permalink'],
					'publishedAt'    => gmdate( DATE_ATOM ),
				)
			);
			$this->post_repository->update( (int) $post['id'], array( 'status' => 'PUBLISHED' ) );

			return array( 'platform' => 'tiktok', 'platformPostId' => $result['platformPostId'] );
		} catch ( \Throwable $error ) {
			$this->publish_result_repository->resolve_pending( (int) $post['id'], 'tiktok', array( 'status' => 'failed', 'error' => $error->getMessage() ) );
			$this->post_repository->update( (int) $post['id'], array( 'status' => 'FAILED' ) );
			throw $error;
		}
	}

	public function publishToTikTok( array $options ): array {
		return $this->publish_to_tiktok( $options );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function get_results_for_post( int $post_id ): array {
		return $this->publish_result_repository->find_by_post_id( $post_id );
	}

	public function getResultsForPost( int $post_id ): array {
		return $this->get_results_for_post( $post_id );
	}

	/**
	 * @return array{deleted:list<string>,skipped:list<string>,errors:list<string>}
	 */
	public function unpublish( int $post_id ): array {
		$results = $this->publish_result_repository->find_by_post_id( $post_id );
		$deleted = array();
		$skipped = array();
		$errors  = array();

		foreach ( $results as $result ) {
			if ( empty( $result['platformPostId'] ) ) {
				continue;
			}

			if ( 'instagram' === $result['platform'] ) {
				$skipped[] = 'instagram';
				continue;
			}

			if ( 'facebook' === $result['platform'] ) {
				$post = $this->post_repository->find_by_id( $post_id );
				if ( null === $post || empty( $post['socialAccountId'] ) ) {
					$errors[] = __( 'facebook: no connected account', 'social-media-scheduler' );
					continue;
				}
				$account = $this->social_account_repository->find_by_id( (int) $post['socialAccountId'] );
				if ( null === $account ) {
					$errors[] = __( 'facebook: connected account not found', 'social-media-scheduler' );
					continue;
				}
				try {
					$this->meta_publisher->delete_post( (string) $result['platformPostId'], (string) $account['accessToken'] );
					$deleted[] = 'facebook';
				} catch ( \Throwable $error ) {
					$errors[] = 'facebook: ' . $error->getMessage();
				}
			}
		}

		return array( 'deleted' => $deleted, 'skipped' => $skipped, 'errors' => $errors );
	}

	/**
	 * @return array{checked:int,trashed:int}
	 */
	public function sync_deleted_facebook_posts(): array {
		$checked = 0;
		$trashed = 0;

		foreach ( $this->publish_result_repository->find_all_successful() as $result ) {
			if ( ! in_array( $result['platform'], array( 'facebook', 'instagram' ), true ) || empty( $result['platformPostId'] ) ) {
				continue;
			}

			$post = $this->post_repository->find_by_id( (int) $result['postId'] );
			if ( null === $post || 'PUBLISHED' !== $post['status'] || ! empty( $post['isStory'] ) || empty( $post['socialAccountId'] ) ) {
				continue;
			}

			$account = $this->social_account_repository->find_by_id( (int) $post['socialAccountId'] );
			if ( null === $account ) {
				continue;
			}

			++$checked;
			if ( ! $this->meta_publisher->check_post_exists( (string) $result['platformPostId'], (string) $account['accessToken'] ) ) {
				++$trashed;
				$this->post_repository->update( (int) $post['id'], array( 'status' => 'CANCELLED' ) );
			}
		}

		return array( 'checked' => $checked, 'trashed' => $trashed );
	}

	/**
	 * @return array{checked:int,published:int,failed:int}
	 */
	public function sync_scheduled_facebook_posts(): array {
		$checked   = 0;
		$published = 0;
		$failed    = 0;

		foreach ( $this->publish_result_repository->find_all_scheduled() as $result ) {
			if ( 'facebook' !== $result['platform'] || empty( $result['platformPostId'] ) ) {
				continue;
			}

			$post = $this->post_repository->find_by_id( (int) $result['postId'] );
			if ( null === $post || 'SCHEDULED' !== $post['status'] || ! empty( $post['isStory'] ) || empty( $post['socialAccountId'] ) || strtotime( (string) $post['scheduledAt'] ) > time() ) {
				continue;
			}

			$account = $this->social_account_repository->find_by_id( (int) $post['socialAccountId'] );
			if ( null === $account ) {
				continue;
			}

			++$checked;
			if ( $this->meta_publisher->check_post_exists( (string) $result['platformPostId'], (string) $account['accessToken'] ) ) {
				$this->publish_result_repository->resolve_pending(
					(int) $post['id'],
					'facebook',
					array(
						'status'         => 'success',
						'platformPostId' => $result['platformPostId'],
						'permalink'      => $result['permalink'],
						'publishedAt'    => gmdate( DATE_ATOM ),
					)
				);
				$this->post_repository->update( (int) $post['id'], array( 'status' => 'PUBLISHED' ) );
				++$published;
				continue;
			}

			$this->publish_result_repository->resolve_pending(
				(int) $post['id'],
				'facebook',
				array(
					'status'         => 'failed',
					'platformPostId' => $result['platformPostId'],
					'permalink'      => $result['permalink'],
					'error'          => __( 'Facebook scheduled post was not found after its scheduled publish time.', 'social-media-scheduler' ),
				)
			);
			$this->post_repository->update( (int) $post['id'], array( 'status' => 'FAILED' ) );
			++$failed;
		}

		return array( 'checked' => $checked, 'published' => $published, 'failed' => $failed );
	}

	public function persist_auto_schedule_failure( int $post_id, string $platform, mixed $error ): void {
		$error_message = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
		if ( $this->has_tracked_scheduled_result( $post_id, $platform ) ) {
			$this->publish_result_repository->create_failure(
				array(
					'postId'   => $post_id,
					'platform' => $platform,
					'error'    => $error_message,
				)
			);
		} else {
			$this->publish_result_repository->resolve_pending( $post_id, $platform, array( 'status' => 'failed', 'error' => $error_message ) );
		}

		$this->post_repository->update( $post_id, array( 'status' => 'FAILED' ) );
	}

	/**
	 * @param array<string,mixed> $input Pending publish result input.
	 */
	private function ensure_pending_result( array $input ): void {
		foreach ( $this->publish_result_repository->find_by_post_id( (int) $input['postId'] ) as $result ) {
			if ( $result['platform'] === $input['platform'] && 'pending' === $result['status'] ) {
				return;
			}
		}

		$this->publish_result_repository->create( array_merge( $input, array( 'status' => 'pending' ) ) );
	}

	private function has_tracked_scheduled_result( int $post_id, string $platform ): bool {
		foreach ( $this->publish_result_repository->find_by_post_id( $post_id ) as $result ) {
			if ( $result['platform'] === $platform && 'scheduled' === $result['status'] && ! empty( $result['platformPostId'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function find_scheduled_result( int $post_id, string $platform ): ?array {
		foreach ( $this->publish_result_repository->find_by_post_id( $post_id ) as $result ) {
			if ( $result['platform'] === $platform && 'scheduled' === $result['status'] && ! empty( $result['platformPostId'] ) ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $results Publish results.
	 */
	private function update_post_status_after_results( int $post_id, array $results ): void {
		$any_deferred           = false;
		$any_platform_scheduled = false;
		$any_immediate_success  = false;

		foreach ( $results as $result ) {
			$is_scheduled = ! empty( $result['isScheduled'] );
			$has_id       = ! empty( $result['platformPostId'] );
			$any_deferred = $any_deferred || ( $is_scheduled && ! $has_id );
			$any_platform_scheduled = $any_platform_scheduled || ( $is_scheduled && $has_id );
			$any_immediate_success  = $any_immediate_success || ( $has_id && ! $is_scheduled );
		}

		if ( $any_deferred || $any_platform_scheduled ) {
			$this->post_repository->update( $post_id, array( 'status' => 'SCHEDULED' ) );
		} elseif ( $any_immediate_success ) {
			$this->post_repository->update( $post_id, array( 'status' => 'PUBLISHED' ) );
		} else {
			$this->post_repository->update( $post_id, array( 'status' => 'FAILED' ) );
		}
	}

	/**
	 * @param array<string,mixed> $post Scheduled post.
	 * @return array<string,mixed>
	 */
	private function find_account_for_post( array $post, string $platform ): array {
		if ( empty( $post['socialAccountId'] ) ) {
			throw new PublishError( __( 'Post has no connected account selected. Please choose an account first.', 'social-media-scheduler' ), 400 );
		}

		$account = $this->social_account_repository->find_by_id( (int) $post['socialAccountId'] );
		if ( null === $account ) {
			throw new PublishError( __( 'Selected connected account no longer exists. Please reselect destination.', 'social-media-scheduler' ), 400 );
		}

		if ( $account['platform'] !== $platform ) {
			throw new PublishError(
				sprintf(
					/* translators: %s: required account platform. */
					__( 'Selected account is not a %s account.', 'social-media-scheduler' ),
					$platform
				),
				400
			);
		}

		if ( ! empty( $account['tokenExpiresAt'] ) && strtotime( (string) $account['tokenExpiresAt'] ) < time() ) {
			throw new PublishError(
				sprintf(
					/* translators: %s: account platform with an expired token. */
					__( '%s access token expired. Please reconnect.', 'social-media-scheduler' ),
					$platform
				),
				401
			);
		}

		return $account;
	}
}
