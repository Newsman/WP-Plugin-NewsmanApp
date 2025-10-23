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

namespace Newsman;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman Remarketing
 * Allows tracking code to be inserted into store pages.
 *
 * @class   \Newsman\Remarketing
 */
class Remarketing {
	/**
	 * SMS config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self Remarketing
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Remarketing();
		}

		return $instance;
	}

	/**
	 * Init WordPress and Woo Commerce hooks
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Declare compatibility with custom_order_tables.
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'custom_order_tables',
						__FILE__,
						true
					);
				}
			}
		);

		// Get cart action.
		add_action( 'wp_loaded', array( new \Newsman\Remarketing\Cart\Handler\CartAjax(), 'display_items' ) );

		// Manage subscribe to newsletter checkbox in checkout.
		add_action(
			'woocommerce_review_order_before_submit',
			array(
				new \Newsman\Form\Checkout\Checkbox(),
				'add_field',
			)
		);
		add_action(
			'woocommerce_checkout_order_processed',
			array(
				new \Newsman\Form\Checkout\Processor(),
				'process',
			),
			10,
			2
		);

		// Tracking code.
		add_action( 'wp_head', array( new \Newsman\Remarketing\Script\Track(), 'display_script' ), 999999 );

		// Event tracking code in footer of the page.
		add_action( 'wp_footer', array( new \Newsman\Remarketing\Action\PageView(), 'display_script_js' ) );
		add_action( 'wp_footer', array( new \Newsman\Remarketing\Action\IdentifySubscriber(), 'display_script_js' ) );
		add_action( 'wp_footer', array( new \Newsman\Remarketing\Action\Purchase(), 'display_script_js' ) );

		// Event tracking code.
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'remove_from_cart_attributes' ), 10, 2 );

		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_impression' ) );
		add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );

		// utm_nooverride parameter for Google AdWords.
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );

		// Order status change hooks.
		foreach ( $this->config->get_order_status_to_name() as $status => $name ) {
			$send_status = new \Newsman\Order\SendStatus();
			if ( method_exists( $send_status, $name ) ) {
				add_action( 'woocommerce_order_status_' . $status, array( $send_status, $name ) );
			}
		}
	}

	/**
	 * Adds the product ID and SKU to the remove product link if not present.
	 *
	 * @param string $link Link.
	 * @param string $item_key Cart item key.
	 * @return string
	 */
	public function remove_from_cart_attributes( $link, $item_key ) {
		if ( false !== strpos( $link, 'data-product_id' ) ) {
			return $link;
		}

		$attributes = new \Newsman\Remarketing\Action\RemoveCartLinkAttributes();
		$attributes->set_data(
			array(
				'link'     => $link,
				'item_key' => $item_key,
			)
		);
		return $attributes->get();
	}

	/**
	 * Measures a listing impression (from search results)
	 */
	public function listing_impression() {
		global $product, $woocommerce_loop;

		$listing_impression = new \Newsman\Remarketing\Action\ListingImpression();
		$listing_impression->set_data(
			array(
				'product'          => $product,
				'woocommerce_loop' => $woocommerce_loop['loop'],
			)
		);
		$script = $listing_impression->get_js();
		if ( ! empty( $script ) ) {
			wc_enqueue_js( $script );
		}
	}

	/**
	 * Measure a product detail view
	 */
	public function product_detail() {
		global $product;

		$detail = new \Newsman\Remarketing\Action\ProductDetail();
		$detail->set_data( array( 'product' => $product ) );
		$script = $detail->get_js();
		if ( ! empty( $script ) ) {
			wc_enqueue_js( $script );
		}
	}

	/**
	 * Check for GET parameter utm_nooverride and add it if not exists.
	 *
	 * @param  string $return_url WooCommerce Return URL.
	 * @return string URL
	 */
	public function utm_nooverride( $return_url ) {
		// We don't know if the URL already has the parameter so we should remove it just in case.
		$return_url = remove_query_arg( 'utm_nooverride', $return_url );

		// Now add the utm_nooverride query arg to the URL.
		$return_url = add_query_arg( 'utm_nooverride', '1', $return_url );

		return $return_url;
	}
}
