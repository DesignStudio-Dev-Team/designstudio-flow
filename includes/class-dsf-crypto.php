<?php
/**
 * Reversible at-rest encryption for stored secrets (API keys, OAuth tokens,
 * reCAPTCHA secret). Uses AES-256-CBC with a key derived from the site's auth
 * salt, which lives in wp-config.php — outside the database — so a database leak
 * alone does not expose credentials.
 *
 * Values are self-describing: ciphertext is prefixed so decryption is
 * unambiguous and legacy plaintext (no prefix) is returned unchanged, then
 * re-encrypted on the next save. OpenSSL is treated as optional; when missing,
 * values pass through as plaintext (functionality preserved, no fatal errors).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Crypto {

	const PREFIX = 'dsfenc:';

	/**
	 * 32-byte AES key derived from the site auth salt.
	 *
	 * @return string
	 */
	private static function key() {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'dsf-static-fallback-key';
		return hash( 'sha256', $salt . '|dsf-secret-store', true );
	}

	/**
	 * Encrypt a plaintext secret. Returns input unchanged when empty, already
	 * encrypted, or when OpenSSL/CSPRNG is unavailable.
	 *
	 * @param string $value Plaintext.
	 * @return string
	 */
	public static function encrypt( $value ) {
		$value = (string) $value;
		if ( '' === $value || 0 === strpos( $value, self::PREFIX ) || ! function_exists( 'openssl_encrypt' ) ) {
			return $value;
		}
		try {
			$iv = random_bytes( 16 );
		} catch ( \Exception $e ) {
			return $value;
		}
		$cipher = openssl_encrypt( $value, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return $value;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary ciphertext, not obfuscation.
		return self::PREFIX . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a stored secret. Non-prefixed values are treated as legacy
	 * plaintext and returned unchanged.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function decrypt( $value ) {
		$value = (string) $value;
		if ( '' === $value || 0 !== strpos( $value, self::PREFIX ) ) {
			return $value;
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary ciphertext, not obfuscation.
		$raw = base64_decode( substr( $value, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return '';
		}
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}
}
