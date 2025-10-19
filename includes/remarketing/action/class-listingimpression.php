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
 * Remarketing action listing impression
 *
 * @class \Newsman\Remarketing\Action\ListingImpression
 */
class ListingImpression extends AbstractAction {
	/**
	 * Position in listing
	 *
	 * @var int
	 */
	public static $position = 0;

	/**
	 * Get JS code
	 *
	 * @return string
	 */
	public function get_js() {
		$data             = $this->get_data();
		$product          = false;
		$woocommerce_loop = false;

		if ( isset( $data['product'] ) ) {
			$product = $data['product'];
		}
		if ( isset( $data['woocommerce_loop'] ) ) {
			$woocommerce_loop = $data['woocommerce_loop'];
		}

		if ( empty( $product ) ) {
			return '';
		}

		if ( false !== $woocommerce_loop ) {
			$position = $woocommerce_loop;
			$position = (int) $position + 1;
		} else {
			++self::$position;
			$position = self::$position;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( isset( $_GET['s'] ) ) {
			$list = 'Search Results';
		} else {
			$list = 'Product List';
		}

		$run = $this->remarketing_config->get_js_track_run_func();

		$js = $run . "( 'ec:addImpression', {
			'id': '" . esc_js( $product->get_id() ) . "',
			'name': '" . esc_js( $product->get_title() ) . "',
			'category': " . $this->get_product_category_line( $product ) . "
			'list': '" . esc_js( $list ) . "',
			'position': '" . esc_js( $position ) . "'
		} );";

		return apply_filters(
			'newsman_remarketing_action_listing_impression_js',
			$js,
			array(
				'product'          => $product,
				'position'         => $position,
				'list'             => $list,
				'woocommerce_loop' => $woocommerce_loop,
			)
		);
	}
}
