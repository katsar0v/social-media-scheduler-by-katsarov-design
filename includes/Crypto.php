<?php
/**
 * Recoverable encryption for OAuth tokens.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler;

use RuntimeException;

/**
 * Encrypts and decrypts OAuth tokens for database storage.
 */
final class Crypto {
	private const CIPHER = 'aes-256-cbc';
	private const PREFIX = 'sms1:';

	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length ) {
			throw new RuntimeException( 'Unable to determine cipher IV length.' );
		}

		$iv         = random_bytes( $iv_length );
		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			throw new RuntimeException( 'Unable to encrypt token.' );
		}

		return self::PREFIX . base64_encode( $iv . $ciphertext );
	}

	public static function decrypt( string $encrypted ): string {
		if ( '' === $encrypted ) {
			return '';
		}

		if ( ! str_starts_with( $encrypted, self::PREFIX ) ) {
			throw new RuntimeException( 'Encrypted token is missing the expected prefix.' );
		}

		$payload = base64_decode( substr( $encrypted, strlen( self::PREFIX ) ), true );
		if ( false === $payload ) {
			throw new RuntimeException( 'Encrypted token payload is not valid base64.' );
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length || strlen( $payload ) <= $iv_length ) {
			throw new RuntimeException( 'Encrypted token payload is malformed.' );
		}

		$iv         = substr( $payload, 0, $iv_length );
		$ciphertext = substr( $payload, $iv_length );
		$plaintext  = openssl_decrypt( $ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $plaintext ) {
			throw new RuntimeException( 'Unable to decrypt token.' );
		}

		return $plaintext;
	}

	private static function key(): string {
		return hash( 'sha256', wp_hash( 'sms_token_key', 'auth' ), true );
	}
}
