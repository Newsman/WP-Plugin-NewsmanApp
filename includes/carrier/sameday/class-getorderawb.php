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

namespace Newsman\Carrier\Sameday;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get SamedayCourier AWB from order
 *
 * @class \Newsman\Carrier\Sameday\GetOrderAwb
 */
class GetOrderAwb {
	/**
	 * Get AWB from order
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public function get( $order_id ) {
		$awb = '';
		if ( class_exists( '\SamedayCourierQueryDb' ) && method_exists( '\SamedayCourierQueryDb', 'getAwbForOrderId' ) ) {
			$data = \SamedayCourierQueryDb::getAwbForOrderId( $order_id );
			if ( is_object( $data ) && property_exists( $data, 'awb_number' ) ) {
				$awb = $data->awb_number;
			} elseif ( is_array( $data ) && isset( $data['awb_number'] ) ) {
				$awb = $data['awb_number'];
			}
		}

		return apply_filters( 'newsman_carrier_sameday_get_order_awb', $awb );
	}
}
