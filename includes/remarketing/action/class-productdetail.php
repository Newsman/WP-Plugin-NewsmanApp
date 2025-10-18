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
 * Remarketing action product detail
 *
 * @class \Newsman\Remarketing\Action\ProductDetail
 */
class ProductDetail extends AbstractAction {
	/**
	 * Get JS code
	 *
	 * @return string
	 */
	public function get_js( ) {
		$data = $this->get_data();
		$product = false;

		if ( isset( $data['product'] ) ) {
			$product = $data['product'];
		}

		if ( empty( $product) ) {
			return '';
		}

		$run = $this->remarketing_config->get_js_track_run_func();
		$js = $run . "( 'ec:addProduct', {
			'id': '" . esc_js( $product->get_id() ? $product->get_id() : ( $product->get_sku() ) ) . "',
			'name': '" . esc_js( $product->get_title() ) . "',
			'category': " . $this->get_product_category_line( $product ) . "
			'price': '" . esc_js( $product->get_price() ) . "',
		} ); ";
		$js .= $run . "( 'ec:setAction', 'detail' );";

		return apply_filters(
			'newsman_remarketing_action_product_detail_js',
			$js,
			array(
				'product' => $product
			)
		);
	}
}
