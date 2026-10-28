<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman subscribe form block signed settings.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Form\Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signs the subscribe form block settings (list, segment, fields) rendered in the
 * page, so the submit handler can trust them without a nonce. A nonce would expire
 * in full page caches; the signature only changes when the WordPress salts change.
 *
 * @class \Newsman\Form\Block\SignedSettings
 */
class SignedSettings {
	/**
	 * Encode and sign the settings.
	 *
	 * @param array $settings Block settings.
	 * @return array `[ 'payload' => string, 'signature' => string ]`.
	 */
	public static function encode( $settings ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport encoding for a hidden input, not obfuscation.
		$payload = base64_encode( (string) wp_json_encode( $settings ) );

		return array(
			'payload'   => $payload,
			'signature' => self::sign( $payload ),
		);
	}

	/**
	 * Verify the signature and decode the settings.
	 *
	 * @param mixed $payload   Encoded settings from the request.
	 * @param mixed $signature Signature from the request.
	 * @return array|null Settings, or null when the payload or the signature is not valid.
	 */
	public static function decode( $payload, $signature ) {
		if ( ! is_string( $payload ) || ! is_string( $signature ) || '' === $payload || '' === $signature ) {
			return null;
		}

		if ( ! hash_equals( self::sign( $payload ), $signature ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport encoding for a hidden input, not obfuscation.
		$json     = base64_decode( $payload, true );
		$settings = false !== $json ? json_decode( $json, true ) : null;

		return is_array( $settings ) ? $settings : null;
	}

	/**
	 * Sign the encoded settings.
	 *
	 * @param string $payload Encoded settings.
	 * @return string
	 */
	protected static function sign( $payload ) {
		return hash_hmac( 'sha256', 'newsman_subscribe_form|' . $payload, wp_salt( 'nonce' ) );
	}
}
