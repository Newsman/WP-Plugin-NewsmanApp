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

namespace Newsman\Carrier\Fancourier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get FAN Courier AWB from order
 *
 * @class \Newsman\Carrier\Fancourier\GetOrderAwb
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
		if ( class_exists( '\FANCourierQueryDb' ) && method_exists( '\FANCourierQueryDb', 'getOrderAWB' ) ) {
			$data = \FANCourierQueryDb::getOrderAWB( $order_id );
			if ( is_object( $data ) && property_exists( $data, 'fan_awb' ) ) {
				$awb = $data->fan_awb;
			} elseif ( is_array( $data ) && isset( $data[0] ) &&
				is_object( $data[0] ) &&
				property_exists( $data[0], 'fan_awb' )
			) {
				$awb = $data[0]->fan_awb;
			}
		}

		return apply_filters( 'newsman_carrier_fancourier_get_order_awb', $awb );
	}
}
