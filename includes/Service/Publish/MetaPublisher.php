<?php
/**
 * Meta Graph API publisher.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service\Publish;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MetaPublisher {
	private const GRAPH_VERSION = 'v25.0';
	private const IG_POLL_MAX_ATTEMPTS = 30;
	private const IG_POLL_INTERVAL_SECONDS = 5;
	private const IG_CONTAINER_MAX_ATTEMPTS = 4;
	private const IG_CONTAINER_RETRY_SECONDS = 3;

	private string $uploads_dir;

	public function __construct( ?string $uploads_dir = null ) {
		$upload_dir        = wp_upload_dir();
		$this->uploads_dir = $uploads_dir ?? (string) ( $upload_dir['basedir'] ?? WP_CONTENT_DIR . '/uploads' );
	}

	public function is_transient_ig_media_error( int $status, string $body ): bool {
		if ( $status >= 500 ) {
			return true;
		}

		$parsed = json_decode( $body, true );
		$error  = is_array( $parsed ) && isset( $parsed['error'] ) && is_array( $parsed['error'] ) ? $parsed['error'] : array();

		if ( array_key_exists( 'is_transient', $error ) && false === $error['is_transient'] ) {
			return false;
		}
		if ( ! empty( $error['is_transient'] ) ) {
			return true;
		}
		if ( 2207052 === (int) ( $error['error_subcode'] ?? 0 ) ) {
			return true;
		}

		$message = strtolower( (string) ( $error['message'] ?? $body ) );

		return str_contains( $message, 'media download has failed' )
			|| str_contains( $message, 'could not be fetched' )
			|| ( 9004 === (int) ( $error['code'] ?? 0 ) && str_contains( $message, 'media' ) );
	}

	/**
	 * @param array<string,mixed> $body Request body.
	 * @return array{id:string}
	 */
	public function create_ig_container( string $url, array $body, string $context ): array {
		$last_status = 0;
		$last_body   = '';

		for ( $attempt = 1; $attempt <= self::IG_CONTAINER_MAX_ATTEMPTS; ++$attempt ) {
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 30,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( $body ),
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_status = 500;
				$last_body   = $response->get_error_message();
			} else {
				$last_status = wp_remote_retrieve_response_code( $response );
				$last_body   = wp_remote_retrieve_body( $response );
				if ( $last_status >= 200 && $last_status < 300 ) {
					$data = json_decode( $last_body, true );
					if ( is_array( $data ) && ! empty( $data['id'] ) ) {
						return array( 'id' => (string) $data['id'] );
					}
				}
			}

			if ( $attempt < self::IG_CONTAINER_MAX_ATTEMPTS && $this->is_transient_ig_media_error( $last_status, $last_body ) ) {
				sleep( self::IG_CONTAINER_RETRY_SECONDS * $attempt );
				continue;
			}

			break;
		}

		throw new PublishError(
			sprintf(
				/* translators: 1: Instagram media container step, 2: response body. */
				__( '%1$s failed: %2$s', 'social-media-scheduler' ),
				$context,
				$last_body
			),
			$last_status ?: 500
		);
	}

	/**
	 * @param array<string,mixed> $post Scheduled post.
	 * @return array{id:string,isScheduled:bool,permalink:?string}
	 */
	public function publish_to_facebook( array $post, string $page_id, string $page_token, ?string $scheduled_at = null ): array {
		$schedule = $this->facebook_schedule_params( $scheduled_at );
		$photos   = array_values(
			array_filter(
				$post['media'] ?? array(),
				static fn ( array $media ): bool => 'image' === (string) $media['type']
			)
		);

		if ( count( $photos ) > 1 ) {
			$photo_ids = array();
			foreach ( $photos as $photo ) {
				$upload = $this->request_json(
					$this->graph_url( "{$page_id}/photos", array( 'access_token' => $page_token ) ),
					array(
						'method' => 'POST',
						'body'   => array(
							'url'       => $this->absolute_media_url( (string) $photo['url'] ),
							'published' => 'false',
						),
					),
					__( 'Facebook photo upload failed', 'social-media-scheduler' )
				);
				$photo_ids[] = (string) $upload['id'];
			}

			$body = array(
				'message'        => (string) $post['caption'],
				'attached_media' => wp_json_encode(
					array_map(
						static fn ( string $id ): array => array( 'media_fbid' => $id ),
						$photo_ids
					)
				),
			);
			if ( ! $schedule['published'] ) {
				$body['published']               = 'false';
				$body['scheduled_publish_time']  = (string) $schedule['scheduled_publish_time'];
			}

			$data      = $this->request_json( $this->graph_url( "{$page_id}/feed", array( 'access_token' => $page_token ) ), array( 'method' => 'POST', 'body' => $body ), __( 'Facebook multi-photo publish failed', 'social-media-scheduler' ) );
			$permalink = $this->fetch_facebook_permalink( (string) $data['id'], $page_token );

			return array( 'id' => (string) $data['id'], 'isScheduled' => $schedule['isScheduled'], 'permalink' => $permalink );
		}

		if ( 1 === count( $photos ) ) {
			$photo = $photos[0];
			$body  = array(
				'caption' => (string) $post['caption'],
				'url'     => $this->absolute_media_url( (string) $photo['url'] ),
			);

			if ( ! $schedule['published'] ) {
				$body['published']              = 'false';
				$body['scheduled_publish_time'] = (string) $schedule['scheduled_publish_time'];
			}

			$data = $this->request_json( $this->graph_url( "{$page_id}/photos", array( 'access_token' => $page_token ) ), array( 'method' => 'POST', 'body' => $body ), __( 'Facebook photo publish failed', 'social-media-scheduler' ) );
			$id   = (string) ( $data['post_id'] ?? $data['id'] );

			if ( $schedule['isScheduled'] && empty( $data['post_id'] ) ) {
				try {
					$photo_info = $this->request_json( $this->graph_url( (string) $data['id'], array( 'fields' => 'post_id', 'access_token' => $page_token ) ), array(), __( 'Facebook scheduled photo lookup failed', 'social-media-scheduler' ) );
					$id         = (string) ( $photo_info['post_id'] ?? $id );
				} catch ( PublishError ) {
					$id = (string) $data['id'];
				}
			}

			return array( 'id' => $id, 'isScheduled' => $schedule['isScheduled'], 'permalink' => $this->fetch_facebook_permalink( $id, $page_token ) );
		}

		$body = array( 'message' => (string) $post['caption'] );
		if ( ! $schedule['published'] ) {
			$body['published']              = 'false';
			$body['scheduled_publish_time'] = (string) $schedule['scheduled_publish_time'];
		}

		$data = $this->request_json( $this->graph_url( "{$page_id}/feed", array( 'access_token' => $page_token ) ), array( 'method' => 'POST', 'body' => $body ), __( 'Facebook feed publish failed', 'social-media-scheduler' ) );

		return array( 'id' => (string) $data['id'], 'isScheduled' => $schedule['isScheduled'], 'permalink' => $this->fetch_facebook_permalink( (string) $data['id'], $page_token ) );
	}

	public function publishToFacebook( array $post, string $page_id, string $page_token, ?string $scheduled_at = null ): array {
		return $this->publish_to_facebook( $post, $page_id, $page_token, $scheduled_at );
	}

	/**
	 * @param array<string,mixed> $post Scheduled post.
	 * @return array{id:string,permalink:?string}
	 */
	public function publish_to_instagram( array $post, string $ig_business_id, string $page_token ): array {
		$media = array_values( $post['media'] ?? array() );
		if ( empty( $media ) ) {
			throw new PublishError( __( 'Instagram posts require at least one media item.', 'social-media-scheduler' ), 400 );
		}

		$images = array_values(
			array_filter(
				$media,
				static fn ( array $item ): bool => 'image' === (string) $item['type']
			)
		);

		if ( count( $images ) > 1 ) {
			$child_ids = array();
			foreach ( $images as $image ) {
				$child = $this->create_ig_container(
					$this->graph_url( "{$ig_business_id}/media" ),
					array(
						'image_url'        => $this->resolve_instagram_image_url( $image ),
						'is_carousel_item' => true,
						'access_token'     => $page_token,
					),
					__( 'Instagram carousel item creation', 'social-media-scheduler' )
				);
				$child_ids[] = $child['id'];
			}

			$container = $this->create_ig_container(
				$this->graph_url( "{$ig_business_id}/media" ),
				array(
					'media_type'   => 'CAROUSEL',
					'children'     => implode( ',', $child_ids ),
					'caption'      => (string) $post['caption'],
					'access_token' => $page_token,
				),
				__( 'Instagram carousel container creation', 'social-media-scheduler' )
			);
			$container_id = $container['id'];
			$this->wait_for_ig_container( $container_id, $page_token, __( 'Instagram carousel container', 'social-media-scheduler' ) );

			$published = $this->request_json(
				$this->graph_url( "{$ig_business_id}/media_publish" ),
				array(
					'method'  => 'POST',
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( array( 'creation_id' => $container_id, 'access_token' => $page_token ) ),
				),
				__( 'Instagram carousel publish failed', 'social-media-scheduler' )
			);

			return array( 'id' => (string) $published['id'], 'permalink' => $this->fetch_instagram_permalink( (string) $published['id'], $page_token ) );
		}

		$first = $media[0];
		$body  = array(
			'caption'      => (string) $post['caption'],
			'access_token' => $page_token,
		);

		if ( 'video' === (string) $first['type'] ) {
			$body['video_url']  = $this->absolute_media_url( (string) $first['url'] );
			$body['media_type'] = 'VIDEO';
		} else {
			$body['image_url'] = $this->resolve_instagram_image_url( $first );
		}

		$container = $this->create_ig_container( $this->graph_url( "{$ig_business_id}/media" ), $body, __( 'Instagram container creation', 'social-media-scheduler' ) );
		$this->wait_for_ig_container( $container['id'], $page_token, __( 'Instagram container', 'social-media-scheduler' ) );

		$published = $this->request_json(
			$this->graph_url( "{$ig_business_id}/media_publish" ),
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'creation_id' => $container['id'], 'access_token' => $page_token ) ),
			),
			__( 'Instagram publish failed', 'social-media-scheduler' )
		);

		return array( 'id' => (string) $published['id'], 'permalink' => $this->fetch_instagram_permalink( (string) $published['id'], $page_token ) );
	}

	public function publishToInstagram( array $post, string $ig_business_id, string $page_token ): array {
		return $this->publish_to_instagram( $post, $ig_business_id, $page_token );
	}

	/**
	 * @param array<string,mixed> $post Scheduled post.
	 * @return array{id:string,isScheduled:bool,permalink:?string}
	 */
	public function publish_facebook_story( array $post, string $page_id, string $page_token ): array {
		$video = $this->first_video( $post );
		if ( null === $video ) {
			throw new PublishError( __( 'Facebook Stories require a video file.', 'social-media-scheduler' ), 400 );
		}

		$file_path = $this->media_file_path( $video );
		$start     = $this->request_json(
			$this->graph_url( "{$page_id}/video_stories" ),
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'upload_phase' => 'start', 'access_token' => $page_token ) ),
			),
			__( 'Facebook Story upload start failed', 'social-media-scheduler' )
		);

		$video_id   = (string) $start['video_id'];
		$upload_url = (string) $start['upload_url'];
		$separator  = str_contains( $upload_url, '?' ) ? '&' : '?';
		$this->raw_request(
			$upload_url . $separator . 'access_token=' . rawurlencode( $page_token ),
			array(
				'method'  => 'POST',
				'headers' => array(
					'offset'    => '0',
					'file_size' => (string) filesize( $file_path ),
				),
				'body'    => file_get_contents( $file_path ),
				'timeout' => 60,
			),
			__( 'Facebook Story video upload failed', 'social-media-scheduler' )
		);

		$finish = $this->request_json(
			$this->graph_url( "{$page_id}/video_stories" ),
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'upload_phase' => 'finish', 'video_id' => $video_id, 'access_token' => $page_token ) ),
			),
			__( 'Facebook Story publish failed', 'social-media-scheduler' )
		);

		$post_id = (string) ( $finish['post_id'] ?? $video_id );

		return array(
			'id'          => $post_id,
			'isScheduled' => false,
			'permalink'   => "https://www.facebook.com/stories/{$page_id}/{$post_id}",
		);
	}

	public function publishFacebookStory( array $post, string $page_id, string $page_token ): array {
		return $this->publish_facebook_story( $post, $page_id, $page_token );
	}

	/**
	 * @param array<string,mixed> $post Scheduled post.
	 * @return array{id:string,permalink:?string}
	 */
	public function publish_instagram_story( array $post, string $ig_business_id, string $page_token ): array {
		$video = $this->first_video( $post );
		if ( null === $video ) {
			throw new PublishError( __( 'Instagram Stories require a video file.', 'social-media-scheduler' ), 400 );
		}

		$file_path = $this->media_file_path( $video );
		$session   = $this->request_json(
			$this->graph_url( "{$ig_business_id}/media" ),
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'media_type'   => 'STORIES',
						'upload_type'  => 'resumable',
						'access_token' => $page_token,
					)
				),
			),
			__( 'Instagram Story upload session failed', 'social-media-scheduler' )
		);

		$container_id = (string) $session['id'];
		if ( ! empty( $session['uri'] ) ) {
			$this->raw_request(
				(string) $session['uri'],
				array(
					'method'  => 'POST',
					'headers' => array(
						'Authorization' => "OAuth {$page_token}",
						'offset'        => '0',
						'file_size'     => (string) filesize( $file_path ),
					),
					'body'    => file_get_contents( $file_path ),
					'timeout' => 60,
				),
				__( 'Instagram Story video upload failed', 'social-media-scheduler' )
			);
		}

		$this->wait_for_ig_container( $container_id, $page_token, __( 'Instagram Story container', 'social-media-scheduler' ) );
		$published = $this->request_json(
			$this->graph_url( "{$ig_business_id}/media_publish" ),
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'creation_id' => $container_id, 'access_token' => $page_token ) ),
			),
			__( 'Instagram Story publish failed', 'social-media-scheduler' )
		);

		return array( 'id' => (string) $published['id'], 'permalink' => $this->fetch_instagram_permalink( (string) $published['id'], $page_token ) );
	}

	public function publishInstagramStory( array $post, string $ig_business_id, string $page_token ): array {
		return $this->publish_instagram_story( $post, $ig_business_id, $page_token );
	}

	public function delete_post( string $platform_post_id, string $page_token ): void {
		$this->request_json(
			$this->graph_url( $platform_post_id, array( 'access_token' => $page_token ) ),
			array( 'method' => 'DELETE' ),
			__( 'Facebook post deletion failed', 'social-media-scheduler' )
		);
	}

	public function deletePost( string $platform_post_id, string $page_token ): void {
		$this->delete_post( $platform_post_id, $page_token );
	}

	public function check_post_exists( string $platform_post_id, string $page_token ): bool {
		$response = wp_remote_get( $this->graph_url( $platform_post_id, array( 'fields' => 'id', 'access_token' => $page_token ) ), array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return true;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status >= 200 && $status < 300 ) {
			return true;
		}
		if ( 401 === $status || 403 === $status ) {
			return true;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = is_array( $data ) && isset( $data['error']['code'] ) ? (int) $data['error']['code'] : 0;

		return 100 !== $code && 10 !== $code;
	}

	public function checkPostExists( string $platform_post_id, string $page_token ): bool {
		return $this->check_post_exists( $platform_post_id, $page_token );
	}

	/**
	 * @return array{exists:bool,createdTime?:?string,permalinkUrl?:?string,isPublished?:bool}|null
	 */
	public function fetch_facebook_post_details( string $platform_post_id, string $page_token ): ?array {
		$response = wp_remote_get(
			$this->graph_url( $platform_post_id, array( 'fields' => 'id,created_time,permalink_url,is_published', 'access_token' => $page_token ) ),
			array( 'timeout' => 20 )
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status >= 200 && $status < 300 && is_array( $data ) ) {
			return array(
				'exists'       => true,
				'createdTime'  => isset( $data['created_time'] ) ? (string) $data['created_time'] : null,
				'permalinkUrl' => isset( $data['permalink_url'] ) ? (string) $data['permalink_url'] : null,
				'isPublished'  => false !== ( $data['is_published'] ?? true ),
			);
		}

		if ( 401 === $status || 403 === $status ) {
			return null;
		}

		$code = is_array( $data ) && isset( $data['error']['code'] ) ? (int) $data['error']['code'] : 0;

		return 100 === $code || 10 === $code ? array( 'exists' => false ) : null;
	}

	/**
	 * @return array{published:bool,scheduled_publish_time?:int,isScheduled:bool}
	 */
	private function facebook_schedule_params( ?string $scheduled_at ): array {
		if ( empty( $scheduled_at ) ) {
			return array( 'published' => true, 'isScheduled' => false );
		}

		$scheduled = strtotime( $scheduled_at );
		if ( false === $scheduled || $scheduled <= time() ) {
			return array( 'published' => true, 'isScheduled' => false );
		}

		if ( $scheduled < time() + 10 * MINUTE_IN_SECONDS ) {
			throw new PublishError( __( 'Facebook scheduled publish time must be at least 10 minutes in the future.', 'social-media-scheduler' ), 400 );
		}

		return array( 'published' => false, 'scheduled_publish_time' => $scheduled, 'isScheduled' => true );
	}

	private function wait_for_ig_container( string $container_id, string $page_token, string $context ): void {
		for ( $i = 0; $i < self::IG_POLL_MAX_ATTEMPTS; ++$i ) {
			$data = $this->request_json(
				$this->graph_url( $container_id, array( 'fields' => 'status_code,status', 'access_token' => $page_token ) ),
				array(),
				sprintf(
					/* translators: %s: Instagram publishing step. */
					__( '%s status lookup failed', 'social-media-scheduler' ),
					$context
				)
			);
			if ( 'FINISHED' === ( $data['status_code'] ?? '' ) ) {
				return;
			}
			if ( 'ERROR' === ( $data['status_code'] ?? '' ) ) {
				throw new PublishError(
					sprintf(
						/* translators: 1: Instagram publishing step, 2: platform status message. */
						__( '%1$s processing failed: %2$s', 'social-media-scheduler' ),
						$context,
						(string) ( $data['status'] ?? __( 'Unknown error', 'social-media-scheduler' ) )
					)
				);
			}
			sleep( self::IG_POLL_INTERVAL_SECONDS );
		}

		throw new PublishError(
			sprintf(
				/* translators: %s: Instagram publishing step. */
				__( '%s processing timed out', 'social-media-scheduler' ),
				$context
			)
		);
	}

	/**
	 * @param array<string,mixed> $media Media item.
	 */
	private function resolve_instagram_image_url( array $media ): string {
		if ( ! $this->is_jpeg_media( $media ) ) {
			throw new PublishError(
				sprintf(
					/* translators: %s: media filename. */
					__( 'Instagram image posts require JPEG media. "%s" must be converted to JPG before publishing.', 'social-media-scheduler' ),
					(string) ( $media['filename'] ?? '' )
				),
				400
			);
		}

		return $this->absolute_media_url( (string) $media['url'] );
	}

	/**
	 * @param array<string,mixed> $media Media item.
	 */
	private function is_jpeg_media( array $media ): bool {
		$mime = (string) ( $media['mimeType'] ?? '' );
		$name = (string) ( $media['filename'] ?? '' );
		$url  = (string) ( $media['url'] ?? '' );

		return 'image/jpeg' === $mime || (bool) preg_match( '/\.jpe?g(?:$|\?)/i', $name ) || (bool) preg_match( '/\.jpe?g(?:$|\?)/i', $url );
	}

	/**
	 * @param array<string,mixed> $post Post data.
	 * @return array<string,mixed>|null
	 */
	private function first_video( array $post ): ?array {
		foreach ( $post['media'] ?? array() as $media ) {
			if ( 'video' === (string) $media['type'] ) {
				return $media;
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $media Media item.
	 */
	private function media_file_path( array $media ): string {
		if ( ! empty( $media['attachmentId'] ) ) {
			$file = get_attached_file( (int) $media['attachmentId'] );
			if ( $file && is_readable( $file ) ) {
				return $file;
			}
		}

		$filename = (string) ( $media['filename'] ?? '' );
		$file     = trailingslashit( $this->uploads_dir ) . $filename;
		if ( '' !== $filename && is_readable( $file ) ) {
			return $file;
		}

		throw new PublishError(
			sprintf(
				/* translators: %s: media filename. */
				__( 'Media file not found: %s', 'social-media-scheduler' ),
				$filename
			),
			400
		);
	}

	private function fetch_facebook_permalink( string $post_id, string $page_token ): ?string {
		try {
			$data = $this->request_json( $this->graph_url( $post_id, array( 'fields' => 'permalink_url', 'access_token' => $page_token ) ), array(), __( 'Facebook permalink lookup failed', 'social-media-scheduler' ) );
			return isset( $data['permalink_url'] ) ? (string) $data['permalink_url'] : null;
		} catch ( PublishError ) {
			return null;
		}
	}

	private function fetch_instagram_permalink( string $media_id, string $page_token ): ?string {
		try {
			$data = $this->request_json( $this->graph_url( $media_id, array( 'fields' => 'permalink', 'access_token' => $page_token ) ), array(), __( 'Instagram permalink lookup failed', 'social-media-scheduler' ) );
			return isset( $data['permalink'] ) ? (string) $data['permalink'] : null;
		} catch ( PublishError ) {
			return null;
		}
	}

	private function graph_url( string $path, array $query = array() ): string {
		return add_query_arg( $query, 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . ltrim( $path, '/' ) );
	}

	private function absolute_media_url( string $url ): string {
		if ( preg_match( '#^https?://#i', $url ) ) {
			return $this->normalize_url_path( $url );
		}

		return $this->normalize_url_path( home_url( $url ) );
	}

	private function normalize_url_path( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( false === $parts || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			throw new PublishError( __( 'Media URL is not valid for Meta publishing.', 'social-media-scheduler' ), 400 );
		}

		$path = '/' . implode( '/', array_filter( explode( '/', (string) ( $parts['path'] ?? '' ) ) ) );
		$normalized = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $path;
		if ( ! empty( $parts['query'] ) ) {
			$normalized .= '?' . $parts['query'];
		}

		if ( ! preg_match( '/^[\x21-\x7E]*$/', $normalized ) ) {
			throw new PublishError( __( 'Public media URLs must contain only US-ASCII URL characters after encoding.', 'social-media-scheduler' ), 400 );
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $args Request args.
	 * @return array<string,mixed>
	 */
	private function request_json( string $url, array $args, string $context ): array {
		$response = $this->raw_request( $url, array_merge( array( 'timeout' => 30 ), $args ), $context );
		$data     = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			throw new PublishError(
				sprintf(
					/* translators: %s: request context. */
					__( '%s: invalid JSON response', 'social-media-scheduler' ),
					$context
				)
			);
		}

		return $data;
	}

	/**
	 * @param array<string,mixed> $args Request args.
	 * @return array<string,mixed>
	 */
	private function raw_request( string $url, array $args, string $context ): array {
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new PublishError( "{$context}: " . $response->get_error_message(), 500, $response );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new PublishError( "{$context}: " . wp_remote_retrieve_body( $response ), $status, wp_remote_retrieve_body( $response ) );
		}

		return $response;
	}
}
