<?php
/**
 * TikTok publisher.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service\Publish;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TikTokPublisher {
	/**
	 * @param array<string,mixed> $post Scheduled post.
	 * @return array{platformPostId:string,permalink:?string}
	 */
	public function publish( array $post, string $token ): array {
		$video = null;
		foreach ( $post['media'] ?? array() as $media ) {
			if ( 'video' === (string) $media['type'] ) {
				$video = $media;
				break;
			}
		}

		if ( null === $video ) {
			throw new PublishError( __( 'TikTok posts require at least one video.', 'social-media-scheduler' ), 400 );
		}

		$creator_response = wp_remote_post(
			'https://open.tiktokapis.com/v2/post/publish/creator_info/query/',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => "Bearer {$token}",
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
			)
		);

		if ( is_wp_error( $creator_response ) ) {
			throw new PublishError( $creator_response->get_error_message() );
		}
		if ( 401 === wp_remote_retrieve_response_code( $creator_response ) ) {
			throw new PublishError( __( 'TikTok access token expired. Please reconnect.', 'social-media-scheduler' ), 401 );
		}

		$init = $this->request_json(
			'https://open.tiktokapis.com/v2/post/publish/video/init/',
			array(
				'Authorization' => "Bearer {$token}",
				'Content-Type'  => 'application/json',
			),
			wp_json_encode(
				array(
					'post_info'   => array(
						'title'                    => (string) ( $post['title'] ?: substr( (string) $post['caption'], 0, 150 ) ),
						'privacy_level'            => 'SELF_ONLY',
						'disable_duet'             => false,
						'disable_comment'          => false,
						'disable_stitch'           => false,
						'video_cover_timestamp_ms' => 1000,
					),
					'source_info' => array(
						'source'    => 'PULL_FROM_URL',
						'video_url' => $this->absolute_media_url( (string) $video['url'] ),
					),
				)
			),
			__( 'TikTok video init failed', 'social-media-scheduler' )
		);

		$publish_id = (string) ( $init['data']['publish_id'] ?? '' );
		if ( '' === $publish_id ) {
			throw new PublishError( __( 'TikTok video init did not return a publish ID.', 'social-media-scheduler' ) );
		}

		$final_status = 'PROCESSING_UPLOAD';
		for ( $i = 0; $i < 60; ++$i ) {
			$status = $this->request_json(
				'https://open.tiktokapis.com/v2/post/publish/status/fetch/',
				array(
					'Authorization' => "Bearer {$token}",
					'Content-Type'  => 'application/json',
				),
				wp_json_encode( array( 'publish_id' => $publish_id ) ),
				__( 'TikTok publish status fetch failed', 'social-media-scheduler' )
			);

			$data         = isset( $status['data'] ) && is_array( $status['data'] ) ? $status['data'] : array();
			$final_status = (string) ( $data['status'] ?? '' );

			if ( 'PUBLISH_COMPLETE' === $final_status ) {
				return array(
					'platformPostId' => $publish_id,
					'permalink'       => isset( $data['share_url'] ) ? (string) $data['share_url'] : null,
				);
			}

			if ( 'FAILED' === $final_status ) {
				throw new PublishError(
					sprintf(
						/* translators: %s: TikTok API status message. */
						__( 'TikTok publish failed: %s', 'social-media-scheduler' ),
						(string) ( $data['error_message'] ?? __( 'unknown error', 'social-media-scheduler' ) )
					)
				);
			}

			sleep( 5 );
		}

		throw new PublishError( __( 'TikTok publish timed out.', 'social-media-scheduler' ) );
	}

	/**
	 * @param array<string,string> $headers Request headers.
	 * @return array<string,mixed>
	 */
	private function request_json( string $url, array $headers, string $body, string $context ): array {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new PublishError( "{$context}: " . $response->get_error_message(), 500, $response );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new PublishError( "{$context}: " . wp_remote_retrieve_body( $response ), $status, wp_remote_retrieve_body( $response ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
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

	private function absolute_media_url( string $url ): string {
		return preg_match( '#^https?://#i', $url ) ? $url : home_url( $url );
	}
}
