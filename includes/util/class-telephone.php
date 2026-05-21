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
 * Util functions for telephone number
 *
 * @class \Newsman\Util\Telephone
 */
class Telephone {
	/**
	 * Clean telephone number
	 *
	 * @param string $phone Telephone number.
	 * @return string
	 */
	public function clean( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}
		$phone = apply_filters( 'newsman_telephone_clean', $phone );
		// Keep only digits (0-9); users may enter '+', spaces, dashes, dots, parentheses, etc.
		return (string) preg_replace( '/\D+/', '', (string) $phone );
	}

	/**
	 * Add RO prefix to telephone number
	 *
	 * @param string $phone Telephone number.
	 * @return string
	 */
	public function add_ro_prefix( $phone ) {
		if ( empty( $phone ) ) {
			return $phone;
		}
		if ( 0 === strpos( $phone, '40' ) ) {
			return $phone;
		}

		if ( 0 === strpos( $phone, '0' ) ) {
			$phone = '4' . $phone;
		} else {
			$phone = '40' . $phone;
		}
		$phone = apply_filters( 'newsman_telephone_add_ro_prefix', $phone );
		return $phone;
	}
}
