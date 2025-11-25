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

namespace Newsman\Carrier\Cargus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Cargus AWB from order
 *
 * @class \Newsman\Carrier\Cargus\GetOrderAwb
 */
class GetOrderAwb {
	/**
	 * Get AWB from order
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public function get( $order_id ) {
		$awb = get_post_meta( $order_id, '_cargus_awb', true );
		return apply_filters( 'newsman_carrier_cargus_get_order_awb', $awb );
	}
}
