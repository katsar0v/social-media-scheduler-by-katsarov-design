<?php
/**
 * OAuth token exchange helpers.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TokenExchange {
	private const META_TOKEN_URL   = 'https://graph.facebook.com/v25.0/oauth/access_token';
	private const TIKTOK_TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

	/**
	 * @param array{appId:string,appSecret:string,redirectUri:string} $config Meta app config.
	 * @return array{pages:list<array{pageAccessToken:string,pageId:string,pageName:string,igBusinessAccountId:?string,igUsername:?string}>,expiresAt:?string}
	 */
	public static function exchange_meta_code_for_page_token( string $code, array $config ): array {
		self::assert_config( $config, array( 'appId', 'appSecret', 'redirectUri' ), __( 'Meta App ID, Meta App Secret, and Meta Redirect URI must be set.', 'social-media-scheduler' ) );

		$token_data = self::remote_json(
			self::META_TOKEN_URL,
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'client_id'     => $config['appId'],
						'client_secret' => $config['appSecret'],
						'redirect_uri'  => $config['redirectUri'],
						'code'          => $code,
					),
					'',
					'&'
				),
			)
		);

		if ( empty( $token_data['access_token'] ) ) {
			throw new RuntimeException( self::graph_error_message( $token_data, __( 'Failed to get user access token.', 'social-media-scheduler' ) ) );
		}

		$long_lived_data = self::remote_json(
			self::META_TOKEN_URL,
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'grant_type'        => 'fb_exchange_token',
						'client_id'         => $config['appId'],
						'client_secret'     => $config['appSecret'],
						'fb_exchange_token' => (string) $token_data['access_token'],
					),
					'',
					'&'
				),
			)
		);

		if ( empty( $long_lived_data['access_token'] ) ) {
			throw new RuntimeException( self::graph_error_message( $long_lived_data, __( 'Failed to get long-lived user token.', 'social-media-scheduler' ) ) );
		}

		$long_lived_token = (string) $long_lived_data['access_token'];
		$raw_pages        = self::fetch_meta_pages( $long_lived_token );

		if ( empty( $raw_pages ) ) {
			throw new RuntimeException( __( 'No Facebook pages found for this user.', 'social-media-scheduler' ) );
		}

		$pages = array();
		foreach ( $raw_pages as $page ) {
			$page_id    = (string) ( $page['id'] ?? '' );
			$page_token = (string) ( $page['access_token'] ?? '' );
			if ( '' === $page_id || '' === $page_token ) {
				continue;
			}

			$ig_business_account_id = null;
			$ig_username            = null;

			try {
				$ig_data = self::remote_json(
					add_query_arg(
						array(
							'fields'       => 'instagram_business_account{id,username}',
							'access_token' => $page_token,
						),
						"https://graph.facebook.com/v25.0/{$page_id}"
					)
				);

				if ( isset( $ig_data['instagram_business_account'] ) && is_array( $ig_data['instagram_business_account'] ) ) {
					$ig_business_account_id = isset( $ig_data['instagram_business_account']['id'] ) ? (string) $ig_data['instagram_business_account']['id'] : null;
					$ig_username            = isset( $ig_data['instagram_business_account']['username'] ) ? (string) $ig_data['instagram_business_account']['username'] : null;
				}
			} catch ( RuntimeException ) {
				$ig_business_account_id = null;
				$ig_username            = null;
			}

			$pages[] = array(
				'pageAccessToken'      => $page_token,
				'pageId'               => $page_id,
				'pageName'             => (string) ( $page['name'] ?? $page_id ),
				'igBusinessAccountId'  => $ig_business_account_id,
				'igUsername'           => $ig_username,
			);
		}

		return array(
			'pages'     => $pages,
			'expiresAt' => ! empty( $long_lived_data['expires_in'] ) ? gmdate( DATE_ATOM, time() + (int) $long_lived_data['expires_in'] ) : null,
		);
	}

	/**
	 * @param array{clientKey:string,clientSecret:string,redirectUri:string} $config TikTok app config.
	 * @return array{accessToken:string,refreshToken:?string,openId:string,username:?string,expiresAt:?string}
	 */
	public static function exchange_tiktok_code_for_token( string $code, array $config ): array {
		self::assert_config( $config, array( 'clientKey', 'clientSecret', 'redirectUri' ), __( 'TikTok Client Key, TikTok Client Secret, and TikTok Redirect URI must be set.', 'social-media-scheduler' ) );

		$data = self::remote_json(
			self::TIKTOK_TOKEN_URL,
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'client_key'    => $config['clientKey'],
						'client_secret' => $config['clientSecret'],
						'code'          => $code,
						'grant_type'    => 'authorization_code',
						'redirect_uri'  => $config['redirectUri'],
					),
					'',
					'&'
				),
			)
		);

		if ( empty( $data['access_token'] ) || empty( $data['open_id'] ) ) {
			throw new RuntimeException( (string) ( $data['error_description'] ?? $data['error'] ?? __( 'Failed to get TikTok access token.', 'social-media-scheduler' ) ) );
		}

		return array(
			'accessToken'  => (string) $data['access_token'],
			'refreshToken' => isset( $data['refresh_token'] ) ? (string) $data['refresh_token'] : null,
			'openId'       => (string) $data['open_id'],
			'username'     => null,
			'expiresAt'    => ! empty( $data['expires_in'] ) ? gmdate( DATE_ATOM, time() + (int) $data['expires_in'] ) : null,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function refresh_meta_token( string $access_token, string $app_id, string $app_secret ): array {
		$data = self::remote_json(
			add_query_arg(
				array(
					'grant_type'        => 'fb_exchange_token',
					'client_id'         => $app_id,
					'client_secret'     => $app_secret,
					'fb_exchange_token' => $access_token,
				),
				self::META_TOKEN_URL
			)
		);

		if ( empty( $data['access_token'] ) ) {
			throw new RuntimeException( self::graph_error_message( $data, __( 'Failed to refresh Meta token.', 'social-media-scheduler' ) ) );
		}

		return $data;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function refresh_tiktok_token( string $refresh_token, string $client_key, string $client_secret ): array {
		$data = self::remote_json(
			self::TIKTOK_TOKEN_URL,
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'client_key'    => $client_key,
						'client_secret' => $client_secret,
						'grant_type'    => 'refresh_token',
						'refresh_token' => $refresh_token,
					),
					'',
					'&'
				),
			)
		);

		if ( empty( $data['access_token'] ) ) {
			throw new RuntimeException( (string) ( $data['error_description'] ?? $data['error'] ?? __( 'Failed to refresh TikTok token.', 'social-media-scheduler' ) ) );
		}

		return $data;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private static function fetch_meta_pages( string $long_lived_token ): array {
		$personal = self::remote_json(
			add_query_arg(
				array(
					'access_token' => $long_lived_token,
					'fields'       => 'id,name,access_token',
				),
				'https://graph.facebook.com/v25.0/me/accounts'
			)
		);

		if ( ! empty( $personal['data'] ) && is_array( $personal['data'] ) ) {
			return $personal['data'];
		}

		$businesses = self::remote_json(
			add_query_arg(
				array(
					'access_token' => $long_lived_token,
					'fields'       => 'id,name',
				),
				'https://graph.facebook.com/v25.0/me/businesses'
			)
		);

		if ( isset( $businesses['error'] ) ) {
			throw new RuntimeException( self::graph_error_message( $businesses, __( 'Failed to fetch Meta businesses.', 'social-media-scheduler' ) ) );
		}

		if ( empty( $businesses['data'] ) || ! is_array( $businesses['data'] ) ) {
			throw new RuntimeException( __( 'No Facebook pages or businesses found for this user.', 'social-media-scheduler' ) );
		}

		$pages = array();
		foreach ( $businesses['data'] as $business ) {
			if ( empty( $business['id'] ) ) {
				continue;
			}

			$page_data = self::remote_json(
				add_query_arg(
					array(
						'access_token' => $long_lived_token,
						'fields'       => 'id,name,access_token',
					),
					'https://graph.facebook.com/v25.0/' . rawurlencode( (string) $business['id'] ) . '/owned_pages'
				)
			);

			if ( ! empty( $page_data['data'] ) && is_array( $page_data['data'] ) ) {
				$pages = array_merge( $pages, $page_data['data'] );
			}
		}

		return $pages;
	}

	/**
	 * @param array<string,mixed> $args wp_remote_request args.
	 * @return array<string,mixed>
	 */
	private static function remote_json( string $url, array $args = array() ): array {
		$response = wp_remote_request(
			$url,
			array_merge(
				array(
					'timeout' => 20,
				),
				$args
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( __( 'Remote service returned invalid JSON.', 'social-media-scheduler' ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new RuntimeException( self::graph_error_message( $data, $body ) );
		}

		return $data;
	}

	/**
	 * @param array<string,mixed> $config Config array.
	 * @param list<string>        $keys Required keys.
	 */
	private static function assert_config( array $config, array $keys, string $message ): void {
		foreach ( $keys as $key ) {
			if ( empty( $config[ $key ] ) ) {
				throw new RuntimeException( $message );
			}
		}
	}

	/**
	 * @param array<string,mixed> $data Response data.
	 */
	private static function graph_error_message( array $data, string $fallback ): string {
		if ( isset( $data['error'] ) && is_array( $data['error'] ) && ! empty( $data['error']['message'] ) ) {
			return (string) $data['error']['message'];
		}

		return $fallback;
	}
}
