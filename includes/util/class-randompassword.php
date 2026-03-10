<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman remarketing class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Random alphanumeric password generator.
 *
 * @class \Newsman\Util\RandomPassword
 */
class RandomPassword {
	/**
	 * Generate a random alphanumeric password guaranteed to contain at least
	 * one lowercase letter, one uppercase letter and one digit.
	 *
	 * @param int $length Password length (minimum 3).
	 * @return string
	 */
	public static function generate( $length = 16 ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.rand_mt_rand -- wp_rand() is not available during plugin upgrade.
		$lowercase = 'abcdefghijklmnopqrstuvwxyz';
		$uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$numbers   = '0123456789';

		// Combine all characters.
		$all_chars    = $lowercase . $uppercase . $numbers;
		$chars_length = strlen( $all_chars );

		$password = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$password .= $all_chars[ mt_rand( 0, $chars_length - 1 ) ];
		}

		// Ensure password has at least one character from each required set.
		$has_lowercase = preg_match( '/[a-z]/', $password );
		$has_uppercase = preg_match( '/[A-Z]/', $password );
		$has_number    = preg_match( '/[0-9]/', $password );

		// Replace characters if any required type is missing.
		if ( ! $has_lowercase ) {
			$password[ mt_rand( 0, $length - 1 ) ] = $lowercase[ mt_rand( 0, strlen( $lowercase ) - 1 ) ];
		}

		if ( ! $has_uppercase ) {
			$password[ mt_rand( 0, $length - 1 ) ] = $uppercase[ mt_rand( 0, strlen( $uppercase ) - 1 ) ];
		}

		if ( ! $has_number ) {
			$password[ mt_rand( 0, $length - 1 ) ] = $numbers[ mt_rand( 0, strlen( $numbers ) - 1 ) ];
		}

		return $password;
	}
	// phpcs:enable WordPress.WP.AlternativeFunctions.rand_mt_rand
}
