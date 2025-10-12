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
 * @extends \WC_Integration
 */
class Remarketing extends \WC_Integration {

	/**
	 * Remarketing ID.
	 *
	 * @var string
	 */
	protected $remarketingid;

	/**
	 * Dismissed info banner.
	 *
	 * @var string
	 */
	protected $dismissed_info_banner;

	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id                    = 'newsman_remarketing';
		$this->method_title          = __( 'Newsman Remarketing', 'newsman' );
		$this->method_description    = __( 'Setup your Newsman Remarketing <a href="/wp-admin/admin.php?page=NewsmanRemarketing">here</a>.', 'newsman' );
		$this->dismissed_info_banner = get_option( 'woocommerce_dismissed_info_banner' );

		// Load the settings.
		$this->init_settings();
		$constructor = $this->init_options();

		// Contains snippets/JS tracking code.
		// todo uncomment, implement, reactor.
		\Newsman\Remarketing\RemarketingJs::get_instance( $constructor );

		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );

		// Tracking code.
		add_action( 'wp_head', array( $this, 'tracking_code_display' ), 999999 );

		// Event tracking code.
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_cart' ) );
		add_action( 'wp_footer', array( $this, 'loop_add_to_cart' ) );
		add_action( 'woocommerce_after_cart', array( $this, 'remove_from_cart' ) );
		add_action( 'woocommerce_after_mini_cart', array( $this, 'remove_from_cart' ) );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'remove_from_cart_attributes' ), 10, 2 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_impression' ) );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_click' ) );
		add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'checkout_process' ) );

		// utm_nooverride parameter for Google AdWords.
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );
	}

	/**
	 * Init options.
	 *
	 * @return array
	 */
	public function init_options() {
		$options = array(
			'remarketingid',
		);

		$constructor = array();
		foreach ( $options as $option ) {
			$this->$option          = $this->get_option( $option );
			$constructor[ $option ] = $this->$option;
		}

		return $constructor;
	}

	/**
	 * Load admin assets.
	 *
	 * @return void
	 */
	public function load_admin_assets() {
		$screen = get_current_screen();
		if ( 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( empty( $_GET['tab'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( 'integration' !== $_GET['tab'] ) {
			return;
		}
	}

	/**
	 * Display the tracking codes
	 * Acts as a controller to figure out which code to display
	 */
	public function tracking_code_display() {
		global $wp;

		if ( $this->disable_tracking() ) {
			return;
		}

		// Check if is order received page and stop when the products and not tracked.
		if ( is_order_received_page() && 'yes' ) {
			$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;

			if ( 0 < $order_id ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_ecommerce_tracking_code( $order_id );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_standard_tracking_code();
	}

	/**
	 * Standard Google Analytics tracking
	 */
	protected function get_standard_tracking_code() {
		$script_logic = '';

		if ( is_user_logged_in() ) {
			$current_user  = wp_get_current_user();
			$script_logic .= "

			/*
			//obsolete

			function wait_to_load_and_identify() {
				if (typeof _nzm.get_tracking_id === 'function') {
					if (_nzm.get_tracking_id() == '') {
						_nzm.identify({ email: \"" . esc_attr( $current_user->user_email ) . '", first_name: "' . esc_attr( $current_user->user_firstname ) . '", last_name: "' . esc_attr( $current_user->user_lastname ) . '" });
					}
				} else {
					setTimeout(function() {wait_to_load_and_identify()}, 50)
				}
			}
		    
			wait_to_load_and_identify();
			*/

			_nzm.identify({ email: "' . esc_attr( $current_user->user_email ) . '", first_name: "' . esc_attr( $current_user->user_firstname ) . '", last_name: "' . esc_attr( $current_user->user_lastname ) . '" });
			';
		}

		return '
		' . \Newsman\Remarketing\RemarketingJs::get_instance()->header() . "
		<script type='text/javascript'>" . \Newsman\Remarketing\RemarketingJs::get_instance()->load_analytics() .
			$script_logic .
		'</script>
		';
	}

	/**
	 * Ecommerce tracking.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	protected function get_ecommerce_tracking_code( $order_id ) {
		// Get the order and output tracking code.
		$order = wc_get_order( $order_id );

		// Make sure we have a valid order object.
		if ( ! $order ) {
			return '';
		}

		$code  = \Newsman\Remarketing\RemarketingJs::get_instance()->load_analytics();
		$code .= \Newsman\Remarketing\RemarketingJs::get_instance()->add_transaction( $order );

		/* @codingStandardsIgnoreStart */
		// Mark the order as tracked.
		// update_post_meta($order_id, '_ga_tracked', 1);

		/*
		return "
		<!-- WooCommerce Newsman Remarketing -->
		" . \Newsman\Remarketing\RemarketingJs::get_instance()->header() . "
		<script type='text/javascript'>$code</script>
		<!-- /WooCommerce Newsman Remarketing -->
		";
		*/
		/* @codingStandardsIgnoreEnd */

		return '';
	}

	/**
	 * Check if tracking is disabled.
	 *
	 * @return bool True if tracking for a certain setting is disabled.
	 */
	private function disable_tracking() {
		$remarketingid = get_option( 'newsman_remarketingid' );

		if ( is_admin() || current_user_can( 'manage_options' ) || empty( $remarketingid ) ) {
			return true;
		}
	}

	/**
	 * Newsman Remarketing event tracking for single product add to cart
	 *
	 * @return void
	 */
	public function add_to_cart() {
		if ( $this->disable_tracking() ) {
			return;
		}
		if ( ! is_single() ) {
			return;
		}

		global $product;

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters.
		$parameters             = array();
		$parameters['category'] = "'" . __( 'Products', 'newsman' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'newsman' ) . "'";
		$parameters['label']    = "'" . esc_js( $product->get_sku() ? __( 'ID:', 'newsman' ) . ' ' . $product->get_sku() : '#' . $product->get_id() ) . "'";

		if ( ! $this->disable_tracking() ) {
			$code = '' . \Newsman\Remarketing\RemarketingJs::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// $code .= "'id': '" . esc_js($product->get_sku() ? $product->get_sku() : ('#' . $product->get_id())) . "',";
			$code                  .= "'id': '" . esc_js( ( $product->get_id() ) ? ( $product->get_id() ) : $product->get_sku() ) . "',";
			$code                  .= "'name': '" . esc_js( $product->get_title() ) . "',";
			$code                  .= "'quantity': $( 'input.qty' ).val() ? $( 'input.qty' ).val() : '1'";
			$code                  .= '} );';
			$parameters['enhanced'] = $code;
		}

		\Newsman\Remarketing\RemarketingJs::get_instance()->event_tracking_code( $parameters, '.single_add_to_cart_button' );
	}

	/**
	 * Enhanced Analytics event tracking for removing a product from the cart
	 */
	public function remove_from_cart() {
		if ( $this->disable_tracking() ) {
			return;
		}

		\Newsman\Remarketing\RemarketingJs::get_instance()->remove_from_cart();
	}

	/**
	 * Adds the product ID and SKU to the remove product link if not present.
	 *
	 * @param string $url URL.
	 * @param string $key Cart key.
	 * @return string
	 */
	public function remove_from_cart_attributes( $url, $key ) {
		if ( strpos( $url, 'data-product_id' ) !== false ) {
			return $url;
		}

		if ( ! is_object( WC()->cart ) ) {
			return $url;
		}

		$item    = WC()->cart->get_cart_item( $key );
		$product = $item['data'];

		if ( ! is_object( $product ) ) {
			return $url;
		}

		$url = str_replace( 'href=', 'data-product_id="' . esc_attr( $product->get_id() ) . '" data-product_sku="' . esc_attr( $product->get_sku() ) . '" href=', $url );
		return $url;
	}

	/**
	 * Newsman Remarketing event tracking for loop add to cart
	 *
	 * @return void
	 */
	public function loop_add_to_cart() {
		if ( $this->disable_tracking() ) {
			return;
		}

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters.
		$parameters             = array();
		$parameters['category'] = "'" . __( 'Products', 'newsman' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'newsman' ) . "'";
		// Product SKU or ID.
		$parameters['label'] = "($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id'))";

		if ( ! $this->disable_tracking() ) {
			$code = '' . \Newsman\Remarketing\RemarketingJs::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// $code .= "'id': ($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id')),";
			$code                  .= "'id': ($(this).data('product_id')) ?  ($(this).data('product_id')) : ($(this).data('product_sku')),";
			$code                  .= "'quantity': $(this).data('quantity')";
			$code                  .= '} );';
			$parameters['enhanced'] = $code;
		}

		\Newsman\Remarketing\RemarketingJs::get_instance()->event_tracking_code( $parameters, '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)' );
	}

	/**
	 * Measures a listing impression (from search results)
	 */
	public function listing_impression() {
		if ( $this->disable_tracking() ) {
			return;
		}

		global $product, $woocommerce_loop;
		\Newsman\Remarketing\RemarketingJs::get_instance()->listing_impression( $product, $woocommerce_loop['loop'] );
	}

	/**
	 * Measure a product click from a listing page
	 */
	public function listing_click() {
		if ( $this->disable_tracking() ) {
			return;
		}

		global $product, $woocommerce_loop;
		\Newsman\Remarketing\RemarketingJs::get_instance()->listing_click( $product, $woocommerce_loop['loop'] );
	}

	/**
	 * Measure a product detail view
	 */
	public function product_detail() {
		if ( $this->disable_tracking() ) {
			return;
		}

		global $product;
		\Newsman\Remarketing\RemarketingJs::get_instance()->product_detail( $product );
	}

	/**
	 * Tracks when the checkout form is loaded.
	 *
	 * @param mixed $checkout Checkout.
	 * @return void
	 */
	public function checkout_process( $checkout ) {
		if ( $this->disable_tracking() ) {
			return;
		}

		\Newsman\Remarketing\RemarketingJs::get_instance()->checkout_process( WC()->cart->get_cart() );
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
