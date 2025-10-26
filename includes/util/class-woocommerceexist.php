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
}
