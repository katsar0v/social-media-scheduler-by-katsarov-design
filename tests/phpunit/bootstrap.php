<?php
/**
 * PHPUnit bootstrap for WordPress integration tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

$tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $tests_dir ) {
	$tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! is_readable( $tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find WordPress test suite. Set WP_TESTS_DIR or run these tests through wp-env.\n" );
	exit( 1 );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/social-media-scheduler.php';
		KatsarovDesign\SocialMediaScheduler\Plugin::instance()->activate();
	}
);

require $tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/SmsTestCase.php';
