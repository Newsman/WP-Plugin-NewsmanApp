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

namespace Newsman\Service\Context\Remarketing;

use Newsman\Service\Context\Blog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service Context Save Order
 *
 * @class \Newsman\Service\Context\Remarketing\SaveOrder
 */
class SaveOrder extends Blog {
	/**
	 * Order details
	 *
	 * @var array
	 */
	protected $order_details;

	/**
	 * Order products
	 *
	 * @var array
	 */
	protected $order_products;

	/**
	 * Set order details
	 *
	 * @param array $order_details Order details.
	 * @return $this
	 */
	public function set_order_details( $order_details ) {
		$this->order_details = $order_details;
		return $this;
	}

	/**
	 * Get order details
	 *
	 * @return array
	 */
	public function get_order_details() {
		return $this->order_details;
	}
	/**
	 * Set order products
	 *
	 * @param array $order_products Order products.
	 * @return $this
	 */
	public function set_order_products( $order_products ) {
		$this->order_products = $order_products;
		return $this;
	}

	/**
	 * Get order products
	 *
	 * @return array
	 */
	public function get_order_products() {
		return $this->order_products;
	}
}
