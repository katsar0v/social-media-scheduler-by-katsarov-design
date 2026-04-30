<?php
/**
 * Crypto smoke test.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( 'wp_hash' ) ) {
	function wp_hash( string $data, string $scheme = 'auth' ): string {
		return hash_hmac( 'sha256', $data, 'test-' . $scheme );
	}
}

require_once dirname( __DIR__ ) . '/includes/Crypto.php';

use KatsarovDesign\SocialMediaScheduler\Crypto;

$token     = 'test-oauth-token-' . bin2hex( random_bytes( 8 ) );
$encrypted = Crypto::encrypt( $token );
$decrypted = Crypto::decrypt( $encrypted );

if ( $encrypted === $token ) {
	throw new RuntimeException( 'Encrypted token should not match plaintext.' );
}

if ( ! str_starts_with( $encrypted, 'sms1:' ) ) {
	throw new RuntimeException( 'Encrypted token should include storage prefix.' );
}

if ( $decrypted !== $token ) {
	throw new RuntimeException( 'Decrypted token did not match plaintext.' );
}

echo "Crypto round-trip passed.\n";
