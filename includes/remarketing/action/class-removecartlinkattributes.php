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

namespace Newsman\Remarketing\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remarketing action remove from cart link attributes
 *
 * @class \Newsman\Remarketing\Action\RemoveCartLinkAttributes
 */
class RemoveCartLinkAttributes extends AbstractAction {
	/**
	 * Get processed data
	 *
	 * @return string
	 */
	public function get() {
		$data = $this->get_data();
		
		$link = false;
		if ( isset( $data['link'] ) ) {
			$link = $data['link'];
		}
		if ( empty ( $link ) ) {
			return $link;
		}
		
		$item_key = false;
		if ( isset( $data['item_key'] ) ) {
			$item_key = $data['item_key'];
		}
		if ( ( false === $item_key ) || ( null === $item_key ) ) {
			return $link;
		}

		$cart = WC()->cart;
		if ( ! is_object( $cart ) ) {
			return $link;
		}

		$item    = $cart->get_cart_item( $item_key );
		$product = $item['data'];

		if ( ! is_object( $product ) ) {
			return $link;
		}

		$link = str_replace(
			'href=',
			'data-product_id="' . esc_attr( $product->get_id() ) . '" data-product_sku="' . 
				esc_attr( $product->get_sku() ) . '" href=',
			$link
		);

		return apply_filters(
			'newsman_remarketing_action_remove_cart_link_attributes',
			$link,
			array(
				'product'  => $product,
				'link'     => $link,
				'item_key' => $item_key,
			)
		);
	}
}
