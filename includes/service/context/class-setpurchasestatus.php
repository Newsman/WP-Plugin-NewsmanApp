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

namespace Newsman\Service\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Context Order Set Purchase Status
 *
 * @class \Newsman\Service\Context\SetPurchaseStatus
 */
class SetPurchaseStatus extends Blog {
	/**
	 * Order ID
	 *
	 * @var string
	 */
	protected $order_id;

	/**
	 * Order status
	 *
	 * @var string
	 */
	protected $order_status;

	/**
	 * Set order ID
	 *
	 * @param string $order_id Order ID.
	 * @return $this
	 */
	public function set_order_id( $order_id ) {
		$this->order_id = $order_id;
		return $this;
	}

	/**
	 * Get order ID
	 *
	 * @return string
	 */
	public function get_order_id() {
		return $this->order_id;
	}

	/**
	 * Set order status
	 *
	 * @param string $order_status Order status.
	 * @return $this
	 */
	public function set_order_status( $order_status ) {
		$this->order_status = $order_status;
		return $this;
	}

	/**
	 * Get order status
	 *
	 * @return string
	 */
	public function get_order_status() {
		return $this->order_status;
	}
}
