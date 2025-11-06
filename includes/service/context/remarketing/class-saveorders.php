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
 * Class Service Context Save Orders
 *
 * @class \Newsman\Service\Context\Remarketing\SaveOrders
 */
class SaveOrders extends Blog {
	/**
	 * Orders
	 *
	 * @var array
	 */
	protected $orders;

	/**
	 * Set orders
	 *
	 * @param array $orders Orders.
	 * @return $this
	 */
	public function set_orders( $orders ) {
		$this->orders = $orders;
		return $this;
	}

	/**
	 * Get orders
	 *
	 * @return array
	 */
	public function get_orders() {
		return $this->orders;
	}
}
