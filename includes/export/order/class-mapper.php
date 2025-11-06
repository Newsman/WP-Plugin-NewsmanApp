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

namespace Newsman\Export\Order;

use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Order Mapper
 *
 * @class \Newsman\Export\Order\Mapper
 */
class Mapper {
	/**
	 * Telephone util
	 *
	 * @var Telephone
	 */
	protected $telephone;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->telephone = new Telephone();
	}

	/**
	 * To array
	 *
	 * @param \WC_Order $order Woo Commerce Order object.
	 * @return array Returns ['details' => [], 'products' => []].
	 */
	public function to_array( $order ) {
		$item_data = $order->get_data();

		$details = array(
			'order_no'      => $order->get_order_number(),
			'lastname'      => $order->get_billing_last_name(),
			'firstname'     => $order->get_billing_first_name(),
			'email'         => $order->get_billing_email(),
			'phone'         => $this->telephone->clean( $item_data['billing']['phone'] ),
			'status'        => $order->get_status(),
			'created_at'    => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
			'discount_code' => implode( ',', $order->get_coupon_codes() ),
			'discount'      => ( empty( $item_data['billing']['discount_total'] ) ) ? 0 :
				(float) $item_data['billing']['discount_total'],
			'shipping'      => (float) $item_data['shipping_total'],
			'rebates'       => 0,
			'fees'          => 0,
			'total'         => (float) wc_format_decimal( $order->get_total(), 2 ),
			'currency'      => $order->get_currency(),
		);

		$products = array();
		$items    = $order->get_items();
		foreach ( $items as $item ) {
			$products[] = array(
				'id'             => (string) $item['product_id'],
				'quantity'       => $item['quantity'],
				'price'          => round( $item->get_total() / $item->get_quantity(), 2 ),
				'variation_code' => '',
			);
		}

		$return = array(
			'details'  => $details,
			'products' => $products,
		);

		return apply_filters( 'newsman_export_order_mapper_to_array', $return, $order );
	}
}
