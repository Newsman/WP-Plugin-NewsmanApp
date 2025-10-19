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

namespace Newsman\Remarketing\Cart\Handler;

use Newsman\Remarketing\Config as RemarketingConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart AJAX handler
 *
 * @class \Newsman\Remarketing\Cart\Handler\CartAjax
 */
class CartAjax {
	/**
	 * GET parameter to identify cart JSON page
	 */
	public const CART_PARAMETER = 'getCart.json';

	/**
	 * Remarketing config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Construct class
	 */
	public function __construct() {
		$this->remarketing_config = RemarketingConfig::init();
	}

	/**
	 * Handles displaying product items based on the 'newsman' GET parameter.
	 *
	 * @return void Outputs JSON-formatted data for cart items or an error message, and terminates execution.
	 */
	public function display_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$newsman = ( empty( $_GET['newsman_cart'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['newsman_cart'] ) );
		if ( empty( $newsman ) || ! $this->remarketing_config->is_active() ) {
			return;
		}

		$exist = new \Newsman\Util\WooCommerceExist();
		if ( ! $exist->exist() ) {
			require ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';

			$page = new \Newsman\Page\Renderer();
			$page->display_json(
				array(
					'status'  => 0,
					'message' => 'WooCommerce is not installed',
				)
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::CART_PARAMETER !== $newsman ) {
			$page = new \Newsman\Page\Renderer();
			$page->display_json(
				array(
					'status'  => 0,
					'message' => 'bad url',
				)
			);
		}

		$cart_items = WC()->cart->get_cart();
		$result     = array();
		foreach ( $cart_items as $cart_item ) {
			$result[] = array(
				'id'       => $cart_item['product_id'],
				'name'     => $cart_item['data']->get_name(),
				'price'    => $cart_item['data']->get_price(),
				'quantity' => $cart_item['quantity'],
			);
		}

		$page = new \Newsman\Page\Renderer();
		$page->display_json( $result, JSON_PRETTY_PRINT );
	}
}
