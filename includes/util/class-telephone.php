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
	 * @return bool
	 */
	public function clean( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}
		$phone = str_replace( '+', '', $phone );
		$phone = preg_replace( '/\s\s+/', ' ', $phone );
		$phone = apply_filters( 'newsman_telephone_clean', $phone );
		return trim( $phone );
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
