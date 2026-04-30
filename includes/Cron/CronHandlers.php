<?php
/**
 * WP-Cron handlers.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Cron;

use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PublishResultRepository;
use KatsarovDesign\SocialMediaScheduler\Service\ExternalPostService;
use KatsarovDesign\SocialMediaScheduler\Service\PublishService;
use KatsarovDesign\SocialMediaScheduler\Service\TokenRefreshService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CronHandlers {
	private const PUBLISH_LOCK = 'sms_publish_lock';

	public static function register(): void {
		add_action( CronRegistrar::PUBLISH_TICK, array( self::class, 'handle_publish_tick' ) );
		add_action( CronRegistrar::TOKEN_REFRESH, array( self::class, 'handle_token_refresh' ) );
		add_action( CronRegistrar::EXTERNAL_POSTS_REFRESH, array( self::class, 'handle_external_posts_refresh' ) );
	}

	/**
	 * @return array{checked:int,published:int,failed:int,skipped:int}
	 */
	public static function handle_publish_tick(): array {
		if ( get_transient( self::PUBLISH_LOCK ) ) {
			return array( 'checked' => 0, 'published' => 0, 'failed' => 0, 'skipped' => 1 );
		}

		set_transient( self::PUBLISH_LOCK, 1, 90 );

		$checked = 0;
		$published = 0;
		$failed = 0;

		try {
			$post_repository = new PostRepository();
			$publish_service = new PublishService( $post_repository );
			$scheduled_posts = $post_repository->list( array( 'status' => 'SCHEDULED' ) );

			foreach ( $scheduled_posts as $post ) {
				if ( strtotime( (string) $post['scheduledAt'] ) > time() ) {
					continue;
				}

				$platform = (string) $post['platform'];
				$is_due   = 'tiktok' === $platform || 'instagram' === $platform || ( 'facebook' === $platform && ! empty( $post['isStory'] ) );
				if ( ! $is_due ) {
					continue;
				}

				++$checked;
				try {
					if ( 'instagram' === $platform ) {
						$publish_service->publish_to_meta( array( 'postId' => (int) $post['id'], 'targetPlatforms' => array( 'instagram' ) ) );
					} elseif ( 'facebook' === $platform ) {
						$publish_service->publish_to_meta( array( 'postId' => (int) $post['id'], 'targetPlatforms' => array( 'facebook' ) ) );
					} elseif ( 'tiktok' === $platform ) {
						$publish_service->publish_to_tiktok( array( 'postId' => (int) $post['id'] ) );
					}
					++$published;
				} catch ( \Throwable $error ) {
					++$failed;
					error_log( sprintf( '[sms-cron] Publish failed for post %d: %s', (int) $post['id'], $error->getMessage() ) );
				}
			}

			return array( 'checked' => $checked, 'published' => $published, 'failed' => $failed, 'skipped' => 0 );
		} finally {
			delete_transient( self::PUBLISH_LOCK );
		}
	}

	/**
	 * @return array{checked:int,refreshed:int,failed:int}
	 */
	public static function handle_token_refresh(): array {
		return ( new TokenRefreshService() )->refresh_due_accounts();
	}

	/**
	 * @return array{accounts:int,posts:int,errors:list<string>}
	 */
	public static function handle_external_posts_refresh(): array {
		return ( new ExternalPostService( publish_result_repository: new PublishResultRepository() ) )->refresh();
	}
}
