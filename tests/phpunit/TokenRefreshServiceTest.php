<?php
/**
 * Token refresh eligibility tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Service\TokenRefreshService;

final class TokenRefreshServiceTest extends SmsTestCase {
	public function test_token_refresh_eligibility_uses_one_hour_window(): void {
		$service = new TokenRefreshService();
		$method = new ReflectionMethod( TokenRefreshService::class, 'needs_refresh' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $service, null ) );
		$this->assertFalse( $method->invoke( $service, gmdate( DATE_ATOM, time() + 2 * HOUR_IN_SECONDS ) ) );
		$this->assertTrue( $method->invoke( $service, gmdate( DATE_ATOM, time() + 30 * MINUTE_IN_SECONDS ) ) );
		$this->assertTrue( $method->invoke( $service, gmdate( DATE_ATOM, time() - MINUTE_IN_SECONDS ) ) );
	}
}
