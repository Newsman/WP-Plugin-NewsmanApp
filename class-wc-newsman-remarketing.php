<?php
/**
 * Plugin Name: NewsmanApp Remarketing
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Description: Allows Newsman Remarketing code to be inserted into WooCommerce store pages.
 * Author: Newsman
 * Author URI: https://newsman.com
 * Version: 3.0.0
 * WC requires at least: 2.1
 * WC tested up to: 9.0.2
 * License: GPLv2 or later
 * Text Domain: newsman-remarketing
 * Domain Path: languages/
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Newsman_Remarketing' ) ) {

	/**
	 * Newsman Remarketing Integration main class.
	 */
	class WC_Newsman_Remarketing {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		public const VERSION = '1.4.6';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;


		/**
		 * Class constructor
		 */
		public function __construct() {
			// Allow non ecommerce pages.
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			add_action( 'wp_loaded', array( $this, 'newsman_get_cart' ) );

			add_action(
				'before_woocommerce_init',
				function () {
					if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
						\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
					}
				}
			);

			// Checks with WooCommerce is installed.
			include_once 'includes/class-wc-class-newsman-remarketing.php';

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		}

		/**
		 * Initialize the plugin.
		 */
		public function newsman_ajax_get_cart() {

			echo wp_json_encode( array( 'status' => 5 ) );
			exit();
		}

		/**
		 * Get cart action
		 *
		 * @return void
		 */
		public function newsman_get_cart() {
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$newsman = ( empty( $_GET['newsman'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['newsman'] ) );

			if ( ! empty( $newsman ) && ! empty( get_option( 'newsman_remarketingid' ) ) ) {

				if ( ! class_exists( 'WooCommerce' ) ) {
					require ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';

					$this->display_json(
						array(
							'status'  => 0,
							'message' => 'WooCommerce is not installed',
						)
					);
				}

				//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				switch ( $_GET['newsman'] ) {
					case 'getCart.json':
						$cart = WC()->cart;

						$prod = array();

						foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

							$prod[] = array(
								'id'       => $cart_item['product_id'],
								'name'     => $cart_item['data']->get_name(),
								'price'    => $cart_item['data']->get_price(),
								'quantity' => $cart_item['quantity'],
							);

						}

						header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
						header( 'Cache-Control: post-check=0, pre-check=0', false );
						header( 'Pragma: no-cache' );
						header( 'Content-Type:application/json' );
						echo wp_json_encode( $prod, JSON_PRETTY_PRINT );
						exit;
					default:
						//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						$this->display_json(
							array(
								'status'  => 0,
								'message' => 'bad url',
							)
						);
						break;
				}
			}
		}

		/**
		 * Display application/json header and JSON from object.
		 *
		 * @param Object|array $obj Object or array to be displayed as JSON.
		 * @return void
		 */
		public function display_json( $obj ) {
			header( 'Content-Type: application/json' );
			echo wp_json_encode( $obj, JSON_PRETTY_PRINT );
			exit;
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Load the plugin text domain for translation.
		 *
		 * @return void
		 */
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'wc-newsman-remarketing' );

			load_textdomain( 'wc-newsman-remarketing', trailingslashit( WP_LANG_DIR ) . 'wc-newsman-remarketing/wc-newsman-remarketing-' . $locale . '.mo' );
			load_plugin_textdomain( 'wc-newsman-remarketing', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Add a new integration to WooCommerce.
		 *
		 * @param  array $integrations WooCommerce integrations.
		 * @return array Newsman Remarketing integration.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Class_Newsman_Remarketing';

			return $integrations;
		}
	}

	add_action( 'plugins_loaded', array( 'WC_Newsman_Remarketing', 'get_instance' ), 0 );

}
