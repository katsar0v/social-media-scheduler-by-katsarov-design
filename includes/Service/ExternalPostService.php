<?php
/**
 * External post synchronization service.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Repository\ExternalPostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PublishResultRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use KatsarovDesign\SocialMediaScheduler\Service\ExternalPosts\MetaPostFetcher;
use KatsarovDesign\SocialMediaScheduler\Service\ExternalPosts\TikTokPostFetcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExternalPostService {
	private const CACHE_KEY = 'sms_external_posts_cache';
	private const CACHE_TTL = 7 * DAY_IN_SECONDS;

	private ExternalPostRepository $external_post_repository;
	private SocialAccountRepository $social_account_repository;
	private ?PublishResultRepository $publish_result_repository;
	private MetaPostFetcher $meta_fetcher;
	private TikTokPostFetcher $tiktok_fetcher;

	public function __construct(
		?ExternalPostRepository $external_post_repository = null,
		?SocialAccountRepository $social_account_repository = null,
		?PublishResultRepository $publish_result_repository = null,
		?MetaPostFetcher $meta_fetcher = null,
		?TikTokPostFetcher $tiktok_fetcher = null
	) {
		$this->external_post_repository  = $external_post_repository ?? new ExternalPostRepository();
		$this->social_account_repository = $social_account_repository ?? new SocialAccountRepository();
		$this->publish_result_repository = $publish_result_repository;
		$this->meta_fetcher              = $meta_fetcher ?? new MetaPostFetcher();
		$this->tiktok_fetcher            = $tiktok_fetcher ?? new TikTokPostFetcher();
	}

	/**
	 * @return array{posts:list<array<string,mixed>>,stale:bool}|null
	 */
	public function get_cached(): ?array {
		$cache = get_transient( self::CACHE_KEY );
		if ( ! is_array( $cache ) || empty( $cache['cachedAt'] ) || ! isset( $cache['posts'] ) || ! is_array( $cache['posts'] ) ) {
			return null;
		}

		$age = time() - (int) strtotime( (string) $cache['cachedAt'] );

		return array(
			'posts' => $cache['posts'],
			'stale' => $age > self::CACHE_TTL,
		);
	}

	/**
	 * @return array{accounts:int,posts:int,errors:list<string>}
	 */
	public function refresh(): array {
		$accounts = $this->social_account_repository->list();
		$total_posts = 0;
		$errors = array();

		$tracked_ids = $this->publish_result_repository ? $this->publish_result_repository->find_tracked_platform_post_ids() : array();
		$tracked_permalinks = $this->publish_result_repository ? $this->publish_result_repository->find_tracked_permalinks() : array();
		foreach ( $tracked_ids as $id ) {
			if ( str_contains( $id, '_' ) ) {
				$parts = explode( '_', $id );
				$tracked_ids[] = (string) end( $parts );
			}
		}
		$tracked_ids = array_values( array_unique( $tracked_ids ) );

		foreach ( $accounts as $account ) {
			try {
				$result = $this->fetch_posts_for_account( $account );
				$fetched_ids = array();
				foreach ( $result['posts'] as $post ) {
					if ( $this->is_tracked( $post, $tracked_ids, $tracked_permalinks ) ) {
						if ( ! empty( $post['permalink'] ) && null !== $this->publish_result_repository ) {
							$this->publish_result_repository->reconcile_by_platform_post_id( (string) $post['platformPostId'], (string) $post['permalink'] );
						}
						continue;
					}

					$this->external_post_repository->upsert( $post );
					$fetched_ids[] = (string) $post['platformPostId'];
					++$total_posts;
				}

				$this->external_post_repository->delete_missing_for_account( (int) $account['id'], $fetched_ids );
				foreach ( $result['errors'] as $message ) {
					$errors[] = sprintf( '%s/%s: %s', (string) $account['platform'], (string) $account['accountName'], $message );
				}
			} catch ( \Throwable $error ) {
				$errors[] = sprintf( '%s/%s: %s', (string) $account['platform'], (string) $account['accountName'], $error->getMessage() );
			}
		}

		$all_posts = $this->external_post_repository->list();
		set_transient(
			self::CACHE_KEY,
			array(
				'cachedAt' => gmdate( DATE_ATOM ),
				'posts'    => $all_posts,
			),
			self::CACHE_TTL
		);

		return array( 'accounts' => count( $accounts ), 'posts' => $total_posts, 'errors' => $errors );
	}

	public function refresh_all(): array {
		return $this->refresh();
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function list( ?int $month = null, ?int $year = null ): array {
		if ( null !== $month && null !== $year ) {
			return $this->external_post_repository->list_by_month( $year, $month );
		}

		return $this->external_post_repository->list();
	}

	/**
	 * @param array<string,mixed> $account Social account.
	 * @return array{posts:list<array<string,mixed>>,errors:list<string>}
	 */
	private function fetch_posts_for_account( array $account ): array {
		$metadata = json_decode( (string) $account['metadata'], true );
		$metadata = is_array( $metadata ) ? $metadata : array();

		if ( 'meta' === $account['platform'] ) {
			return $this->meta_fetcher->fetch_posts( (int) $account['id'], (string) $account['accessToken'], $metadata );
		}

		if ( 'tiktok' === $account['platform'] ) {
			return array(
				'posts'  => $this->tiktok_fetcher->fetch_posts( (int) $account['id'], (string) $account['accessToken'] ),
				'errors' => array(),
			);
		}

		return array( 'posts' => array(), 'errors' => array() );
	}

	/**
	 * @param array<string,mixed> $post External post.
	 * @param list<string>        $tracked_ids Tracked platform IDs.
	 * @param list<string>        $tracked_permalinks Tracked permalinks.
	 */
	private function is_tracked( array $post, array $tracked_ids, array $tracked_permalinks ): bool {
		$platform_post_id = (string) $post['platformPostId'];
		if ( in_array( $platform_post_id, $tracked_ids, true ) ) {
			return true;
		}
		if ( str_contains( $platform_post_id, '_' ) ) {
			$parts = explode( '_', $platform_post_id );
			if ( in_array( (string) end( $parts ), $tracked_ids, true ) ) {
				return true;
			}
		}

		return ! empty( $post['permalink'] ) && in_array( (string) $post['permalink'], $tracked_permalinks, true );
	}
}
