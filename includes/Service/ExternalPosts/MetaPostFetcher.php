<?php
/**
 * Meta external post fetcher.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service\ExternalPosts;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MetaPostFetcher {
	/**
	 * @return string|null
	 */
	public static function parse_facebook_datetime( mixed $value ): ?string {
		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && preg_match( '/^\d+$/', trim( $value ) ) ) ) {
			return gmdate( DATE_ATOM, (int) $value );
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			$raw = trim( $value );
			$parsed = strtotime( $raw );
			if ( false !== $parsed ) {
				return gmdate( DATE_ATOM, $parsed );
			}

			$normalized = preg_replace( '/([+-]\d{2})(\d{2})$/', '$1:$2', $raw );
			$parsed     = strtotime( (string) $normalized );
			if ( false !== $parsed ) {
				return gmdate( DATE_ATOM, $parsed );
			}
		}

		return null;
	}

	/**
	 * @param array<string,string> $meta Account metadata.
	 * @return array{posts:list<array<string,mixed>>,errors:list<string>}
	 */
	public function fetch_posts( int $account_id, string $token, array $meta ): array {
		$posts  = array();
		$errors = array();

		if ( ! empty( $meta['fbPageId'] ) ) {
			try {
				$posts = array_merge( $posts, $this->fetch_facebook_posts( (string) $meta['fbPageId'], $token, $account_id ) );
			} catch ( \Throwable $error ) {
				$errors[] = sprintf(
					/* translators: %s: fetch error message. */
					__( 'facebook: %s', 'social-media-scheduler' ),
					$error->getMessage()
				);
			}

			try {
				$posts = array_merge( $posts, $this->fetch_facebook_stories( (string) $meta['fbPageId'], $token, $account_id ) );
			} catch ( \Throwable $error ) {
				error_log( '[external-posts] Could not fetch FB stories: ' . $error->getMessage() );
			}
		}

		if ( ! empty( $meta['igBusinessAccountId'] ) ) {
			try {
				$posts = array_merge( $posts, $this->fetch_instagram_posts( (string) $meta['igBusinessAccountId'], $token, $account_id ) );
			} catch ( \Throwable $error ) {
				$errors[] = sprintf(
					/* translators: %s: fetch error message. */
					__( 'instagram: %s', 'social-media-scheduler' ),
					$error->getMessage()
				);
			}

			try {
				$posts = array_merge( $posts, $this->fetch_instagram_stories( (string) $meta['igBusinessAccountId'], $token, $account_id ) );
			} catch ( \Throwable $error ) {
				error_log( '[external-posts] Could not fetch IG stories: ' . $error->getMessage() );
			}
		}

		return array( 'posts' => $posts, 'errors' => $errors );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function fetch_facebook_posts( string $page_id, string $token, int $account_id ): array {
		$data = $this->request_json(
			$this->graph_url(
				"{$page_id}/published_posts",
				array(
					'fields' => 'id,message,created_time,attachments{url,media_type,media},permalink_url,is_published',
					'limit'  => 100,
				)
			),
			$token,
			__( 'Facebook published posts fetch failed', 'social-media-scheduler' )
		);

		$published_posts = array();
		foreach ( $data['data'] ?? array() as $post ) {
			if ( isset( $post['is_published'] ) && false === $post['is_published'] ) {
				continue;
			}
			$attachment = $post['attachments']['data'][0] ?? array();
			$published_posts[] = array(
				'platform'       => 'facebook',
				'accountId'      => $account_id,
				'platformPostId' => (string) $post['id'],
				'content'        => (string) ( $post['message'] ?? '' ),
				'mediaUrl'       => (string) ( $attachment['media']['image']['src'] ?? '' ),
				'permalink'      => (string) ( $post['permalink_url'] ?? '' ),
				'publishedAt'    => (string) $post['created_time'],
				'metadata'       => wp_json_encode( array( 'type' => (string) ( $attachment['media_type'] ?? 'text' ) ) ),
			);
		}

		$all = $published_posts;
		foreach ( array( 'fetch_facebook_scheduled_posts', 'fetch_facebook_unpublished_feed_posts', 'fetch_facebook_scheduled_video_posts' ) as $method ) {
			try {
				$all = array_merge( $all, $this->{$method}( $page_id, $token, $account_id ) );
			} catch ( \Throwable $error ) {
				error_log( '[external-posts] Could not fetch FB supplemental posts: ' . $error->getMessage() );
			}
		}

		$unique = array();
		foreach ( $all as $post ) {
			$unique[ $post['platform'] . ':' . $post['platformPostId'] ] = $post;
		}

		return array_values( $unique );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function fetch_facebook_scheduled_posts( string $page_id, string $token, int $account_id ): array {
		$data = $this->request_json(
			$this->graph_url( "{$page_id}/scheduled_posts", array( 'fields' => 'id,message,scheduled_publish_time,attachments{url,media_type,media}', 'limit' => 100 ) ),
			$token,
			__( 'Facebook scheduled posts fetch failed', 'social-media-scheduler' )
		);

		$posts = array();
		foreach ( $data['data'] ?? array() as $post ) {
			$scheduled_at = self::parse_facebook_datetime( $post['scheduled_publish_time'] ?? null );
			if ( null === $scheduled_at ) {
				continue;
			}
			$attachment = $post['attachments']['data'][0] ?? array();
			$posts[] = array(
				'platform'       => 'facebook',
				'accountId'      => $account_id,
				'platformPostId' => (string) $post['id'],
				'content'        => (string) ( $post['message'] ?? '' ),
				'mediaUrl'       => (string) ( $attachment['media']['image']['src'] ?? '' ),
				'permalink'      => '',
				'publishedAt'    => $scheduled_at,
				'metadata'       => wp_json_encode( array( 'type' => (string) ( $attachment['media_type'] ?? 'text' ), 'scheduled' => true ) ),
			);
		}

		return $posts;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function fetch_facebook_unpublished_feed_posts( string $page_id, string $token, int $account_id ): array {
		$data = $this->request_json(
			$this->graph_url( "{$page_id}/feed", array( 'published' => 'false', 'fields' => 'id,message,scheduled_publish_time,created_time,attachments{media_type,media},permalink_url', 'limit' => 100 ) ),
			$token,
			__( 'Facebook unpublished feed fetch failed', 'social-media-scheduler' )
		);

		$posts = array();
		foreach ( $data['data'] ?? array() as $post ) {
			$scheduled_at = self::parse_facebook_datetime( $post['scheduled_publish_time'] ?? null );
			if ( null === $scheduled_at ) {
				continue;
			}
			$attachment = $post['attachments']['data'][0] ?? array();
			$posts[] = array(
				'platform'       => 'facebook',
				'accountId'      => $account_id,
				'platformPostId' => (string) $post['id'],
				'content'        => (string) ( $post['message'] ?? '' ),
				'mediaUrl'       => (string) ( $attachment['media']['image']['src'] ?? '' ),
				'permalink'      => (string) ( $post['permalink_url'] ?? '' ),
				'publishedAt'    => $scheduled_at,
				'metadata'       => wp_json_encode( array( 'type' => (string) ( $attachment['media_type'] ?? 'text' ), 'scheduled' => true, 'source' => 'feed' ) ),
			);
		}

		return $posts;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function fetch_facebook_scheduled_video_posts( string $page_id, string $token, int $account_id ): array {
		$data = $this->request_json(
			$this->graph_url( "{$page_id}/videos", array( 'fields' => 'id,description,created_time,published,permalink_url,status', 'limit' => 100 ) ),
			$token,
			__( 'Facebook videos fetch failed', 'social-media-scheduler' )
		);

		$posts = array();
		foreach ( $data['data'] ?? array() as $video ) {
			$publish_time = $video['status']['publishing_phase']['publish_time'] ?? null;
			$status       = strtolower( (string) ( $video['status']['publishing_phase']['publish_status'] ?? '' ) );
			$scheduled_at = self::parse_facebook_datetime( $publish_time );
			if ( null === $scheduled_at ) {
				continue;
			}
			$looks_scheduled = str_contains( $status, 'scheduled' ) || str_contains( $status, 'pending' ) || false === ( $video['published'] ?? true );
			if ( strtotime( $scheduled_at ) <= time() || ! $looks_scheduled ) {
				continue;
			}

			$posts[] = array(
				'platform'       => 'facebook',
				'accountId'      => $account_id,
				'platformPostId' => (string) $video['id'],
				'content'        => (string) ( $video['description'] ?? '' ),
				'mediaUrl'       => '',
				'permalink'      => (string) ( $video['permalink_url'] ?? '' ),
				'publishedAt'    => $scheduled_at,
				'metadata'       => wp_json_encode( array( 'type' => 'video', 'scheduled' => true, 'source' => 'videos', 'publishStatus' => $status ) ),
			);
		}

		return $posts;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function fetch_instagram_posts( string $ig_business_id, string $token, int $account_id ): array {
		$data = $this->request_json(
			$this->graph_url( "{$ig_business_id}/media", array( 'fields' => 'id,caption,timestamp,media_url,permalink,media_type', 'limit' => 100 ) ),
			$token,
			__( 'Instagram media fetch failed', 'social-media-scheduler' )
		);

		return array_map(
			static fn ( array $media ): array => array(
				'platform'       => 'instagram',
				'accountId'      => $account_id,
				'platformPostId' => (string) $media['id'],
				'content'        => (string) ( $media['caption'] ?? '' ),
				'mediaUrl'       => (string) ( $media['media_url'] ?? '' ),
				'permalink'      => (string) ( $media['permalink'] ?? '' ),
				'publishedAt'    => (string) $media['timestamp'],
				'metadata'       => wp_json_encode( array( 'type' => (string) ( $media['media_type'] ?? 'IMAGE' ) ) ),
			),
			is_array( $data['data'] ?? null ) ? $data['data'] : array()
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function fetch_instagram_stories( string $ig_business_id, string $token, int $account_id ): array {
		$data = $this->request_json(
			$this->graph_url( "{$ig_business_id}/stories", array( 'fields' => 'id,caption,timestamp,media_url,permalink,media_type', 'limit' => 100 ) ),
			$token,
			__( 'Instagram stories fetch failed', 'social-media-scheduler' )
		);

		return array_map(
			static fn ( array $media ): array => array(
				'platform'       => 'instagram',
				'accountId'      => $account_id,
				'platformPostId' => (string) $media['id'],
				'content'        => (string) ( $media['caption'] ?? '' ),
				'mediaUrl'       => (string) ( $media['media_url'] ?? '' ),
				'permalink'      => (string) ( $media['permalink'] ?? '' ),
				'publishedAt'    => (string) $media['timestamp'],
				'metadata'       => wp_json_encode( array( 'type' => 'STORY' ) ),
			),
			is_array( $data['data'] ?? null ) ? $data['data'] : array()
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function fetch_facebook_stories( string $page_id, string $token, int $account_id ): array {
		$data = $this->request_json(
			$this->graph_url( "{$page_id}/stories", array( 'fields' => 'post_id,status,creation_time,url,media_type' ) ),
			$token,
			__( 'Facebook stories fetch failed', 'social-media-scheduler' )
		);

		$posts = array();
		foreach ( $data['data'] ?? array() as $story ) {
			if ( ! empty( $story['status'] ) && 'published' !== strtolower( (string) $story['status'] ) ) {
				continue;
			}
			$posts[] = array(
				'platform'       => 'facebook',
				'accountId'      => $account_id,
				'platformPostId' => (string) ( $story['post_id'] ?? $story['id'] ?? '' ),
				'content'        => '',
				'mediaUrl'       => '',
				'permalink'      => (string) ( $story['url'] ?? '' ),
				'publishedAt'    => gmdate( DATE_ATOM, (int) ( $story['creation_time'] ?? time() ) ),
				'metadata'       => wp_json_encode( array( 'type' => 'story', 'mediaType' => $story['media_type'] ?? null ) ),
			);
		}

		return $posts;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function request_json( string $url, string $token, string $context ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => "Bearer {$token}" ),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$body   = wp_remote_retrieve_body( $response );
		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new RuntimeException( "{$context} ({$status}): {$body}" );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: request context. */
					__( '%s: invalid JSON response', 'social-media-scheduler' ),
					$context
				)
			);
		}

		return $data;
	}

	private function graph_url( string $path, array $query = array() ): string {
		return add_query_arg( $query, 'https://graph.facebook.com/v25.0/' . ltrim( $path, '/' ) );
	}
}
