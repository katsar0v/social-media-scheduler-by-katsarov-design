<?php
/**
 * OAuth configuration tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Installer;
use KatsarovDesign\SocialMediaScheduler\Service\MetaOAuthService;
use KatsarovDesign\SocialMediaScheduler\Service\TikTokOAuthService;

final class OAuthServiceConfigTest extends SmsTestCase {
	public function test_meta_authorization_requires_app_credentials(): void {
		update_option(
			Installer::OPTION_SETTINGS,
			array_merge(
				Installer::default_settings(),
				array(
					'metaAppId'     => '',
					'metaAppSecret' => '',
				)
			),
			false
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Meta App ID and App Secret must be configured' );

		( new MetaOAuthService() )->create_authorization_url();
	}

	public function test_meta_authorization_url_targets_facebook_when_configured(): void {
		update_option(
			Installer::OPTION_SETTINGS,
			array_merge(
				Installer::default_settings(),
				array(
					'metaAppId'       => 'meta-app-id',
					'metaAppSecret'   => 'meta-app-secret',
					'metaRedirectUri' => 'https://stale.example/callback',
				)
			),
			false
		);

		$url = ( new MetaOAuthService() )->create_authorization_url();
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertStringStartsWith( 'https://www.facebook.com/v25.0/dialog/oauth?', $url );
		$this->assertStringContainsString( 'client_id=meta-app-id', $url );
		$this->assertStringContainsString( 'response_type=code', $url );
		$this->assertSame( MetaOAuthService::redirect_uri(), $query['redirect_uri'] );
	}

	public function test_tiktok_authorization_requires_app_credentials(): void {
		update_option(
			Installer::OPTION_SETTINGS,
			array_merge(
				Installer::default_settings(),
				array(
					'tiktokClientKey'    => '',
					'tiktokClientSecret' => '',
				)
			),
			false
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'TikTok Client Key and Client Secret must be configured' );

		( new TikTokOAuthService() )->create_authorization_url();
	}
}
