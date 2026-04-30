<?php
/**
 * TikTok external post fetcher.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service\ExternalPosts;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TikTokPostFetcher {
	/**
	 * @return list<array<string,mixed>>
	 */
	public function fetch_posts( int $account_id, string $token ): array {
		$response = wp_remote_post(
			'https://open.tiktokapis.com/v2/video/list/?fields=id,title,create_time,cover_image_url,share_url,video_description',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => "Bearer {$token}",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'max_count' => 100 ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new RuntimeException(
				sprintf(
					/* translators: 1: HTTP status code, 2: response body. */
					__( 'TikTok video list failed (%1$s): %2$s', 'social-media-scheduler' ),
					$status,
					$body
				)
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( __( 'TikTok video list returned invalid JSON.', 'social-media-scheduler' ) );
		}

		$videos = $data['data']['videos'] ?? array();
		if ( ! is_array( $videos ) ) {
			$videos = array();
		}

		return array_map(
			static fn ( array $video ): array => array(
				'platform'       => 'tiktok',
				'accountId'      => $account_id,
				'platformPostId' => (string) $video['id'],
				'content'        => (string) ( $video['title'] ?? $video['video_description'] ?? '' ),
				'mediaUrl'       => (string) ( $video['cover_image_url'] ?? '' ),
				'permalink'      => (string) ( $video['share_url'] ?? '' ),
				'publishedAt'    => gmdate( DATE_ATOM, (int) ( $video['create_time'] ?? time() ) ),
				'metadata'       => wp_json_encode( array( 'type' => 'VIDEO' ) ),
			),
			$videos
		);
	}
}
