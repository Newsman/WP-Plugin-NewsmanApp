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
	 * Newsman config
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

		// Get cart action.
		add_action( 'wp_loaded', array( new \Newsman\Remarketing\Cart\Handler\CartAjax(), 'display_items' ) );

		// Manage subscribe to newsletter checkbox in checkout.
		$checkbox = new \Newsman\Form\Checkout\Checkbox();
		$checkbox->init_hooks();

		$checkout_processor = new \Newsman\Form\Checkout\Processor();
		$checkout_processor->init();
		$checkout_processor = new \Newsman\Form\Account\Processor();
		$checkout_processor->init_hooks();

		// Tracking code.
		add_action( 'wp_head', array( new \Newsman\Remarketing\Script\Track(), 'display_script' ), 999999 );

		// Event tracking code in footer of the page.
		add_action( 'wp_footer', array( new \Newsman\Remarketing\Action\IdentifySubscriber(), 'display_script_js' ) );
		add_action( 'wp_footer', array( new \Newsman\Remarketing\Action\Purchase(), 'display_script_js' ) );

		// Event tracking code.
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'remove_from_cart_attributes' ), 10, 2 );

		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_impression' ) );
		add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );

		// utm_nooverride parameter for Google AdWords.
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );

		$this->init_scheduled_hooks();

		// It should be the last action running (displayed in page source after all).
		add_action( 'wp_footer', array( $this, 'send_page_view' ) );
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

		$page_view = new \Newsman\Remarketing\Action\PageView();
		$page_view->set_data( array( \Newsman\Remarketing\Action\PageView::MARK_PAGE_VIEW_SENT_FLAG => true ) );
		$script .= $page_view->get_js();

		if ( ! empty( $script ) ) {
			wc_enqueue_js( $script );
		}
	}

	/**
	 * Send page viewed
	 */
	public function send_page_view() {
		global $product;

		$page_view = new \Newsman\Remarketing\Action\PageView();
		$page_view->set_data( array( \Newsman\Remarketing\Action\PageView::MARK_PAGE_VIEW_SENT_FLAG => true ) );
		$script = $page_view->get_js();
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
		$return_url = apply_filters( 'newsman_remarketing_utm_nooverride', $return_url );

		return $return_url;
	}

	/**
	 * Init remarketing Action Scheduler hooks.
	 *
	 * @return void
	 */
	public function init_scheduled_hooks() {
		foreach ( $this->get_known_scheduled_classes() as $class ) {
			if ( method_exists( $class, 'init_hooks' ) ) {
				$scheduled_class = new $class();
				$scheduled_class->init_hooks();
			}
		}
	}

	/**
	 * Get known remarketing action scheduler classes.
	 *
	 * @return array
	 */
	public function get_known_scheduled_classes() {
		$classes = array(
			'\Newsman\Scheduler\Order\Status\SendStatus',
			'\Newsman\Scheduler\Order\Status\SendSms',
			'\Newsman\Scheduler\Order\Status\SaveOrder',
		);

		return apply_filters( 'newsman_known_remarketing_scheduled_classes', $classes );
	}
}
