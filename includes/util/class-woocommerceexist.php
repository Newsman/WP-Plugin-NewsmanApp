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
 * Woo Commerce exists
 *
 * @class \Newsman\Util\WooCommerceExist
 */
class WooCommerceExist {
	/**
	 * Woo
	 *
	 * @return bool
	 */
	public function exist() {
		$exists = class_exists( '\WooCommerce' );
		if ( function_exists( 'apply_filters' ) ) {
			$exists = apply_filters( 'newsman_woocommerce_exist', $exists );
		}
		return $exists;
	}

	/**
	 * Check if the current WooCommerce version is less than a given version.
	 *
	 * @param string $version Version to compare.
	 * @return bool
	 */
	public function is_wc_before( $version ) {
		$wc_version = defined( 'WC_VERSION' ) ? constant( 'WC_VERSION' ) : ( function_exists( 'WC' ) && isset( WC()->version ) ? WC()->version : '0.0.0' );
		return version_compare( $wc_version, $version, '<' );
	}
}
