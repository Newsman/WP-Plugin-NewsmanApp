<?php
/**
 * Plugin Name: NewsmanApp for WordPress
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Description: NewsmanApp for WordPress (sign up widget, subscribers sync, create and send newsletters from blog posts)
 * Version: 2.7.7
 * Author: Newsman
 * Author URI: https://www.newsman.com
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'vendor/Newsman/class-newsman-client.php';

/**
 * Newsman WP main class
 */
class WP_Newsman {
	/**
	 * Instance of a Newsman_Client.
	 *
	 * @var Newsman_Client
	 */
	public $client;

	/**
	 * First element in array is the type of message (success or error).
	 * Second element in array is the message string.
	 *
	 * @var array
	 */
	public $message;

	/**
	 * The user id of the Newsman_Client.
	 *
	 * @var integer
	 */
	public $userid;

	/**
	 * The api key of the Newsman_Client.
	 *
	 * @var string
	 */
	public $apikey;

	/**
	 * If credentials (combination of user id and api key) are correct true, else false.
	 *
	 * @var bool
	 */
	public $valid_credentials = true;

	/**
	 * Array containing the names of the html files found in the templates directory.
	 * (as defined by the templates_dir constant).
	 *
	 * @var array
	 */
	public $templates = array();

	/**
	 * Bulk operations batch size.
	 *
	 * @var int
	 */
	public $batch_size = 9000;

	/**
	 * WP was synchronized.
	 *
	 * @var bool
	 */
	public $wp_sync = false;

	/**
	 * Woo Commerce customers synchronized with Newsman.
	 *
	 * @var bool
	 */
	public $woo_commerce_sync = false;

	/**
	 * Retargeting JS endpoint
	 *
	 * @var string
	 */
	public static $endpoint = 'https://retargeting.newsmanapp.com/js/retargeting/track.js';

	/**
	 * Newsman endpoint host.
	 *
	 * @var string
	 */
	public static $endpoint_host = 'https://retargeting.newsmanapp.com';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->construct_client();
		$this->init_hooks();
	}

	/**
	 * Is OAuth allow or redirect.
	 *
	 * @param bool $inside_oauth In OAuth process than redirect.
	 * @return void
	 */
	public function is_oauth( $inside_oauth = false ) {

		if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
			return;
		}

		$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		if ( $inside_oauth ) {
			if ( ! empty( get_option( 'newsman_userid' ) ) ) {
				wp_safe_redirect( 'https://' . $host . '/wp-admin/admin.php?page=NewsmanSettings' );
			}

			return;
		}

		if ( empty( get_option( 'newsman_userid' ) ) ) {
			wp_safe_redirect( 'https://' . $host . '/wp-admin/admin.php?page=NewsmanOauth' );
		}
	}

	/**
	 * Set's up the Newsman_Client instance.
	 *
	 * @param integer | string $userid The user id for Newsman (default's to null).
	 * @param string           $apikey The api key for Newsman (default's to null).
	 * @return void
	 */
	public function construct_client( $userid = null, $apikey = null ) {
		$this->userid = ( ! is_null( $userid ) ) ? $userid : get_option( 'newsman_userid' );
		$this->apikey = ( ! is_null( $apikey ) ) ? $apikey : get_option( 'newsman_apikey' );

		try {
			$this->client = new Newsman_Client( $this->userid, $this->apikey );
			$this->client->setCallType( 'rest' );
		} catch ( Exception $e ) {
			$this->valid_credentials = false;
		}
	}

	/**
	 * Tests the Newsman Client Instance for valid credentials
	 *
	 * @return boolean
	 */
	public function show_on_front() {
		try {
			$test = $this->client->list->all();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Encode array or object and set headers.
	 *
	 * @param array|Object $obj Array or object to encode.
	 * @return void
	 */
	public function display_json( $obj ) {
		header( 'Content-Type: application/json' );
		echo wp_json_encode( $obj, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Export data to newsman action.
	 *
	 * @return void
	 * @throws Exception Throws standard exception on errors.
	 */
	public function newsman_fetch_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$newsman = ( empty( $_GET['newsman'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['newsman'] ) );
		if ( empty( $newsman ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$newsman = empty( $_POST['newsman'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['newsman'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$apikey = ( empty( $_GET['nzmhash'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['nzmhash'] ) );
		if ( empty( $apikey ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$apikey = empty( $_POST['nzmhash'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['nzmhash'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$authorization_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
		if ( strpos( $authorization_header, 'Bearer' ) !== false ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$apikey = trim( str_replace( 'Bearer', '', $authorization_header ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$start = ( ! empty( $_GET['start'] ) && $_GET['start'] > 0 ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$limit = ( empty( $_GET['limit'] ) ) ? 1000 : sanitize_text_field( wp_unslash( $_GET['limit'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$order_id = ( empty( $_GET['order_id'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['order_id'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$product_id = ( empty( $_GET['product_id'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['product_id'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$method = ( empty( $_GET['method'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['method'] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$cron_last = ( empty( $_GET['cronlast'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['cronlast'] ) );
		if ( ! empty( $cron_last ) ) {
			$cron_last = ( 'true' === $cron_last ) ? true : false;
		}

		if ( ! empty( $newsman ) && ! empty( $apikey ) ) {

			$allow_api = get_option( 'newsman_api' );
			if ( 'on' !== $allow_api ) {
				$this->display_json(
					array(
						'status'  => 403,
						'message' => 'API setting is not enabled in plugin',
					)
				);
				return;
			}

			$curr_api_key = get_option( 'newsman_apikey' );
			if ( $apikey !== $curr_api_key ) {
				$this->display_json( array( 'status' => 403 ) );
				return;
			}

			if ( ! class_exists( 'WooCommerce' ) ) {
				wp_send_json( array( 'error' => 'WooCommerce is not installed' ) );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			switch ( $_GET['newsman'] ) {
				case 'orders.json':
					$orders = null;

					$args = array(
						'limit'  => $limit,
						'offset' => $start,
					);

					if ( ! empty( $order_id ) ) {
						$orders = wc_get_order( $order_id );
						$orders = array(
							$orders,
						);

						if ( empty( $orders[0] ) ) {
							$this->display_json( array() );
							return;
						}
					} elseif ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
							$query  = new WC_Order_Query(
								array(
									'limit'  => $limit,
									'offset' => $start,
								)
							);
							$orders = $query->get_orders();
					} else {
						$orders = wc_get_orders( $args );
					}

					$orders_obj = array();
					foreach ( $orders as $item ) {
						$user = get_userdata( $item->get_user_id() );

						$products      = $item->get_items();
						$products_json = array();

						$item_data = $item->get_data();

						foreach ( $products as $prod ) {
							$_prod = wc_get_product( $prod['product_id'] );

							$image_id  = $_prod->get_image_id();
							$image_url = wp_get_attachment_image_url( $image_id, 'full' );
							$url       = get_permalink( $_prod->get_id() );

							$_price     = 0;
							$_price_old = 0;

							if ( empty( $_prod->get_sale_price() ) ) {
								$_price = $_prod->get_price();
							} else {
								$_price     = $_prod->get_sale_price();
								$_price_old = $_prod->get_regular_price();
							}

							$products_json[] = array(
								'id'        => (string) $prod['product_id'],
								'name'      => $prod['name'],
								'quantity'  => (int) $prod['quantity'],
								'price'     => (float) $_price,
								'price_old' => (float) $_price_old,
								'image_url' => $image_url,
								'url'       => $url,
							);
						}

						$date = $item->get_date_created();
						$date = $date->getTimestamp();

						$orders_obj[] = array(
							'order_no'      => $item->get_order_number(),
							'date'          => $date,
							'status'        => $item->get_status(),
							'lastname'      => ( empty( $user ) ? $item->get_billing_last_name() : $user->last_name ),
							'firstname'     => ( empty( $user ) ? $item->get_billing_first_name() : $user->first_name ),
							'email'         => ( empty( $user ) ? $item->get_billing_email() : $user->first_name ),
							'phone'         => $item_data['billing']['phone'],
							'state'         => $item_data['billing']['state'],
							'city'          => $item_data['billing']['city'],
							'address'       => $item_data['billing']['address_1'],
							'discount'      => ( empty( $item_data['billing']['discount_total'] ) ) ? 0 : (float) $item_data['billing']['discount_total'],
							'discount_code' => '',
							'shipping'      => (float) $item_data['shipping_total'],
							'fees'          => 0,
							'rebates'       => 0,
							'total'         => (float) wc_format_decimal( $item->get_total(), 2 ),
							'products'      => $products_json,
						);
					}

					$this->display_json( $orders_obj );
					exit;

				case 'products.json':
					$products = null;

					$args = array(
						'stock_status' => 'instock',
						'limit'        => $limit,
						'offset'       => $start - 1,
					);

					if ( ! empty( $product_id ) ) {
						$products = wc_get_product( $product_id );
						$products = array(
							$products,
						);

						if ( empty( $products[0] ) ) {
							$this->display_json( array() );
							return;
						}
					} else {
						$products = wc_get_products( $args );
					}

					$products_json = array();

					foreach ( $products as $prod ) {

						$image_id  = $prod->get_image_id();
						$image_url = wp_get_attachment_image_url( $image_id, 'full' );
						$url       = get_permalink( $prod->get_id() );

						$_price     = 0;
						$_price_old = 0;

						if ( empty( $prod->get_sale_price() ) ) {
							$_price = $prod->get_price();
						} else {
							$_price     = $prod->get_sale_price();
							$_price_old = $prod->get_regular_price();
						}

						$cat_ids  = $prod->get_category_ids();
						$category = '';

						foreach ( (array) $cat_ids as $cat_id ) {
							$cat_term = get_term_by( 'id', (int) $cat_id, 'product_cat' );
							if ( $cat_term ) {
								$category = $cat_term->name;
								break;
							}
						}

						$products_json[] = array(
							'id'             => (string) $prod->get_id(),
							'category'       => $category,
							'name'           => $prod->get_name(),
							'stock_quantity' => ( empty( $prod->get_stock_quantity() ) ) ? null : (float) $prod->get_stock_quantity(),
							'price'          => (float) $_price,
							'price_old'      => (float) $_price_old,
							'image_url'      => $image_url,
							'url'            => $url,
						);
					}

					$this->display_json( $products_json );
					exit;

				case 'customers.json':
					$args    = array(
						'role'   => 'customer',
						'offset' => $start,
						'number' => $limit,
					);
					$wp_cust = get_users( $args );
					$custs   = array();

					foreach ( $wp_cust as $users => $user ) {
						$data = get_user_meta( $user->data->ID );

						$custs[] = array(
							'email'     => $user->data->user_email,
							'firstname' => $data['first_name'][0],
							'lastname'  => $data['last_name'][0],
						);
					}

					$this->display_json( $custs );
					exit;

				case 'subscribers.json':
					$args           = array(
						'role'   => 'subscriber',
						'offset' => $start,
						'number' => $limit,
					);
					$wp_subscribers = get_users( $args );
					$subs           = array();

					foreach ( $wp_subscribers as $users => $user ) {
						$data = get_user_meta( $user->data->ID );

						$subs[] = array(
							'email'     => $user->data->user_email,
							'firstname' => $data['first_name'][0],
							'lastname'  => $data['last_name'][0],
						);
					}

					$this->display_json( $subs );
					exit;

				case 'version.json':
					$version = array(
						'version' => 'Wordpress ' . get_bloginfo( 'version' ),
					);

					echo wp_json_encode( $version, JSON_PRETTY_PRINT );
					exit;

				case 'cron.json':
					$list_id  = get_option( 'newsman_list' );
					$segments = get_option( 'newsman_segments' );

					if ( empty( $list_id ) ) {
						$this->display_json( array( 'status' => 'List setup incomplete' ) );
					}

					switch ( $method ) {
						case 'woocommerce':
							if ( class_exists( 'WooCommerce' ) ) {
								$this->import_woocommerce_subscribers( $list_id, $segments, $start, $limit, $cron_last );

								$json = array(
									'status' => 'success',
								);

								$this->display_json( $json );
							} else {
								$this->display_json( array( 'status' => 'woocommerce is not installed' ) );
							}

							exit;

						case 'WordPress':
							$this->import_wp_subscribers( $list_id, $segments, $start, $limit, $cron_last );

							$json = array(
								'status' => 'success',
							);

								$this->display_json( $json );

							exit;
					}

					$this->display_json( array( 'status' => 'method does not exist' ) );

					return;

				case 'coupons.json':
					try {

						if ( ! class_exists( 'WC_Coupon' ) ) {
							include_once WC()->plugin_path() . '/includes/class-wc-coupon.php';
						}

						// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
						$discount_type = ! isset( $_GET['type'] ) ? -1 : (int) $_GET['type'];
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
						$value = ! isset( $_GET['value'] ) ? -1 : (int) $_GET['value'];
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
						$batch_size = ! isset( $_GET['batch_size'] ) ? 1 : (int) $_GET['batch_size'];
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
						$prefix = ! isset( $_GET['prefix'] ) ? '' : sanitize_text_field( wp_unslash( $_GET['prefix'] ) );
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
						$expire_date = isset( $_GET['expire_date'] ) ? sanitize_text_field( wp_unslash( $_GET['expire_date'] ) ) : null;
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
						$min_amount = ! isset( $_GET['min_amount'] ) ? -1 : (float) $_GET['min_amount'];
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
						$currency = isset( $_GET['currency'] ) ? sanitize_text_field( wp_unslash( $_GET['currency'] ) ) : '';

						if ( empty( $discount_type ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
							$discount_type = empty( $_POST['type'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['type'] ) );
						}
						if ( empty( $value ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
							$value = empty( $_POST['value'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['value'] ) );
						}
						if ( empty( $batch_size ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
							$batch_size = empty( $_POST['batch_size'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['batch_size'] ) );
						}
						if ( empty( $prefix ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
							$prefix = empty( $_POST['prefix'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['prefix'] ) );
						}
						if ( empty( $expire_date ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
							$expire_date = empty( $_POST['expire_date'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['expire_date'] ) );
						}
						if ( empty( $min_amount ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
							$min_amount = empty( $_POST['min_amount'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['min_amount'] ) );
						}
						if ( empty( $currency ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
							$currency = empty( $_POST['currency'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['currency'] ) );
						}

						if ( -1 === $discount_type || '-1' === $discount_type ) {
							$this->display_json(
								array(
									'status' => 0,
									'msg'    => 'Missing type param',
								)
							);
						} elseif ( -1 === $value || '-1' === $value ) {
							$this->display_json(
								array(
									'status' => 0,
									'msg'    => 'Missing value param',
								)
							);
						}

						$coupons_list = array();

						for ( $int = 0; $int < $batch_size; $int++ ) {
							$coupon = new WC_Coupon();

							switch ( $discount_type ) {
								case 1:
									$coupon->set_discount_type( 'percent' );
									break;
								case 0:
									$coupon->set_discount_type( 'fixed_cart' );
									break;
							}

							$characters  = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
							$coupon_code = '';

							do {
								$coupon_code = '';
								for ( $i = 0; $i < 8; $i++ ) {
									$coupon_code .= $characters[ wp_rand( 0, strlen( $characters ) - 1 ) ];
								}
								$full_coupon_code   = $prefix . $coupon_code;
								$existing_coupon_id = wc_get_coupon_id_by_code( $full_coupon_code );
							} while ( 0 !== $existing_coupon_id );

							$coupon->set_code( $full_coupon_code );
							$coupon->set_description( 'NewsMAN generated coupon code' );
							$coupon->set_amount( $value );

							if ( null !== $expire_date ) {
								// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
								$formatted_expire_date = date( 'Y-m-d H:i:s', strtotime( $expire_date ) );
								$coupon->set_date_expires( strtotime( $formatted_expire_date ) );
							}

							if ( -1 !== $min_amount && '-1' !== $min_amount ) {
								$coupon->set_minimum_amount( $min_amount );
							}

							$coupon->save();

							array_push( $coupons_list, $coupon->get_code() );
						}

						$this->display_json(
							array(
								'status' => 1,
								'codes'  => $coupons_list,
							)
						);
					} catch ( Exception $exception ) {
						$this->display_json(
							array(
								'status' => 0,
								'msg'    => $exception->getMessage(),
							)
						);
					}

					break;
			}
		}
	}

	/**
	 * Imports subscribers from WordPress Into Newsman and creates a message.
	 *
	 * @param string         $list_id The ID of the list into which to import the subscribers.
	 * @param string | array $segments The ID of the segment into which to import the subscribers.
	 * @param int            $start Batch start.
	 * @param int            $limit Batch limit.
	 * @param bool           $cron_last Is last.
	 * @return void
	 */
	public function import_wp_subscribers( $list_id, $segments, $start = 1, $limit = 1000, $cron_last = false ) {
		// Get WordPress subscribers as array.
		if ( $cron_last ) {
			$args           = array( 'role' => 'subscriber' );
			$wp_subscribers = get_users( $args );

			$data = count( $wp_subscribers );

			$start = $data - $limit;

			if ( $start < 1 ) {
				$start = 1;
			}
		}

		$args           = array(
			'role'   => 'subscriber',
			'offset' => $start,
			'number' => $limit,
		);
		$wp_subscribers = get_users( $args );

		// Synchronize with Newsman.
		try {
			$_segments           = ( ! empty( $segments ) ) ? array( $segments ) : '';
			$customers_to_import = array();

			foreach ( $wp_subscribers as $users => $user ) {
				$customers_to_import[] = array(
					'email'     => $user->data->user_email,
					'firstname' => $user->data->display_name,
					'lastname'  => '',
					'tel'       => '',
				);
				if ( ( count( $customers_to_import ) % $this->batch_size ) === 0 ) {
					$this->import_data( $customers_to_import, $list_id, $this->client, 'newsman plugin WordPress subscribers CRON', $_segments );
				}
			}
			if ( count( $customers_to_import ) > 0 ) {
				$this->import_data( $customers_to_import, $list_id, $this->client, 'newsman plugin WordPress subscribers CRON', $_segments );
			}

			unset( $customers_to_import );

			$this->wp_sync = true;
			$this->set_message_backend( 'updated', 'Subscribers synced with Newsman.' );

		} catch ( Exception $e ) {
			$this->set_message_backend( 'error', 'Failure to sync subscribers with Newsman.' . $e->getMessage() );
		}

		if ( empty( $this->message ) ) {
			$this->set_message_backend( 'updated', 'Options saved.' );
		}
	}

	/**
	 * Import subscribers.
	 *
	 * @param string         $list_id List ID.
	 * @param string | array $segments Segment(s) ID(s).
	 * @param int            $start Batch start.
	 * @param int            $limit Batch limit.
	 * @param bool           $cron_last Is last.
	 *
	 * @return void
	 */
	public function import_woocommerce_subscribers( $list_id, $segments, $start = 1, $limit = 1000, $cron_last = false ) {
		if ( $cron_last ) {
			$woocommerce_filter = array(
				'status' => 'completed',
			);

			$all_orders = wc_get_orders( $woocommerce_filter );
			$data       = count( $all_orders );

			$start = $data - $limit;

			if ( $start < 1 ) {
				$start = 1;
			}
		}

		$woocommerce_filter = array(
			'status' => 'completed',
			'limit'  => $limit,
			'offset' => $start,
		);

		$all_orders = wc_get_orders( $woocommerce_filter );

		try {
			$_segments = ( ! empty( $segments ) ) ? array( $segments ) : array();

			$customers_to_import = array();

			foreach ( $all_orders as $user ) {

				$data = json_decode( wp_json_encode( $user->data ) );

				if ( empty( $data ) ) {
					continue;
				}

				if ( ! array_key_exists( 'billing', $user->data ) ) {
					continue;
				}

				$customers_to_import[] = array(
					'email'     => $user->data['billing']['email'],
					'firstname' => ( ! empty( $user->data['billing']['first_name'] ) ) ? $user->data['billing']['first_name'] : '',
					'lastname'  => ( ! empty( $user->data['billing']['first_name'] ) ) ? $user->data['billing']['last_name'] : '',
					'tel'       => ( ! empty( $user->data['billing']['phone'] ) ) ? $user->data['billing']['phone'] : '',
				);

				if ( ( count( $customers_to_import ) % $this->batch_size ) === 0 ) {
					$this->import_data( $customers_to_import, $list_id, $this->client, 'newsman plugin WordPress woocommerce CRON', $_segments );
				}
			}
			if ( count( $customers_to_import ) > 0 ) {
				$this->import_data( $customers_to_import, $list_id, $this->client, 'newsman plugin WordPress woocommerce CRON', $_segments );
			}

			unset( $customers_to_import );

			$this->woo_commerce_sync = true;
			$this->set_message_backend( 'updated ', 'WooCommerce customers synced with Newsman.' );

		} catch ( Exception $e ) {
			$this->set_message_backend( 'error ', 'Failed to sync Woocommerce customers with Newsman.' );
		}

		if ( empty( $this->message ) ) {
			$this->set_message_backend( 'updated', 'Options saved.' );
		}
	}

	/**
	 * Send to Newsman order status pending.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function pending( $order_id ) {
		$this->save_order_newsman( $order_id, 'pending' );
	}

	/**
	 * Send to Newsman order status failed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function failed( $order_id ) {
		$this->save_order_newsman( $order_id, 'failed' );
	}

	/**
	 * Send to Newsman order status on hold.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function hold( $order_id ) {
		$this->save_order_newsman( $order_id, 'on-hold' );
	}

	/**
	 * Send to Newsman order status processing.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function processing( $order_id ) {
		$this->save_order_newsman( $order_id, 'processing' );
	}

	/**
	 * Send to Newsman order status completed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function completed( $order_id ) {
		$this->save_order_newsman( $order_id, 'completed' );
	}

	/**
	 * Send to Newsman order status refunded.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function refunded( $order_id ) {
		$this->save_order_newsman( $order_id, 'refunded' );
	}

	/**
	 * Send to Newsman order status canceled.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function cancelled( $order_id ) {
		$this->save_order_newsman( $order_id, 'cancelled' );
	}

	/**
	 * Send to Newsman order status send order status to Newsman.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return void
	 */
	public function save_order_newsman( $order_id, $status ) {

		$newsman_usesms    = get_option( 'newsman_usesms' );
		$newsman_smslist   = get_option( 'newsman_smslist' );
		$newsman_smstest   = get_option( 'newsman_smstest' );
		$newsman_smstestnr = get_option( 'newsman_smstestnr' );

		$send_sms        = false;
		$newsman_smstext = '';

		$newsman_smspending = get_option( 'newsman_smspendingactivate' );
		if ( 'pending' === $status && 'on' === $newsman_smspending ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smspendingtext' );
		}
		$newsman_smsfailed = get_option( 'newsman_smsfailedactivate' );
		if ( 'failed' === $status && 'on' === $newsman_smsfailed ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsfailedtext' );
		}
		$newsman_smsonhold = get_option( 'newsman_smsonholdactivate' );
		if ( 'on-hold' === $status && 'on' === $newsman_smsonhold ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsonholdtext' );
		}
		$newsman_smsprocessing = get_option( 'newsman_smsprocessingactivate' );
		if ( 'processing' === $status && 'on' === $newsman_smsprocessing ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsprocessingtext' );
		}
		$newsman_smscompleted = get_option( 'newsman_smscompletedactivate' );
		if ( 'completed' === $status && 'on' === $newsman_smscompleted ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smscompletedtext' );
		}
		$newsman_smsrefunded = get_option( 'newsman_smsrefundedactivate' );
		if ( 'refunded' === $status && 'on' === $newsman_smsrefunded ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsrefundedtext' );
		}
		$newsman_smscancelled = get_option( 'newsman_smscancelledactivate' );
		if ( 'cancelled' === $status && 'on' === $newsman_smscancelled ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smscancelledtext' );
		}

		if ( $send_sms ) {
			try {
				if ( ! empty( $newsman_usesms ) && 'on' === $newsman_usesms && ! empty( $newsman_smslist ) ) {
					$order     = wc_get_order( $order_id );
					$item_data = $order->get_data();

					$date = $order->get_date_created()->date( 'F j, Y' );

					$newsman_smstext = str_replace( '{{billing_first_name}}', $item_data['billing']['first_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{billing_last_name}}', $item_data['billing']['last_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{shipping_first_name}}', $item_data['shipping']['first_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{shipping_last_name}}', $item_data['shipping']['last_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{email}}', $item_data['billing']['email'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_number}}', $item_data['id'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_date}}', $date, $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_total}}', $item_data['total'], $newsman_smstext );
					$phone           = '4' . $item_data['billing']['phone'];

					if ( $newsman_smstest ) {
						$phone = '4' . $newsman_smstestnr;
					}

					$this->client->sms->sendone( $newsman_smslist, $newsman_smstext, $phone );
				}
			} catch ( Exception $e ) {
				//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( $e->getMessage() );
			}
		}

		$list_id = get_option( 'newsman_remarketingid' );
		$list_id = explode( '-', $list_id );
		$list_id = $list_id[1];

		$url = 'https://ssl.newsman.app/api/1.2/rest/' . $this->userid . '/' . $this->apikey . '/remarketing.setPurchaseStatus.json?list_id=' . $list_id . '&order_id=' . $order_id . '&status=' . $status;

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array()
		);
	}

	/**
	 * Add checkout field subscribe to newsletter checkbox.
	 *
	 * @return void
	 */
	public function newsman_checkout() {

		$checkout = get_option( 'newsman_checkoutnewsletter' );

		if ( ! empty( $checkout ) && 'on' === $checkout ) {
			$msg     = get_option( 'newsman_checkoutnewslettermessage' );
			$default = get_option( 'newsman_checkoutnewsletterdefault' );
			$checked = '';

			if ( ! empty( $default ) && 'on' === $default ) {
				$default = 1;
				$checked = 'checked';
			} else {
				$default = 0;
			}

			woocommerce_form_field(
				'newsmanCheckoutNewsletter',
				array(
					'type'        => 'checkbox',
					'class'       => array( 'form-row newsmanCheckoutNewsletter' ),
					'label_class' => array( 'woocommerce-form__label woocommerce-form__label-for-checkbox checkbox' ),
					'input_class' => array( 'woocommerce-form__input woocommerce-form__input-checkbox input-checkbox' ),
					'required'    => false,
					'label'       => $msg,
					'default'     => $default,
					'checked'     => $checked,
				)
			);
		}
	}

	/**
	 * Process checkout action.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function newsman_checkout_action( $order_id ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['newsmanCheckoutNewsletter'] ) && 1 === (int) $_POST['newsmanCheckoutNewsletter'] ) {

			$checkout_newsletter      = get_option( 'newsman_checkoutnewsletter' );
			$checkout_sms             = get_option( 'newsman_checkoutsms' );
			$checkout_newsletter_type = get_option( 'newsman_checkoutnewslettertype' );
			$list_id                  = get_option( 'newsman_list' );
			$smslist                  = get_option( 'newsman_smslist' );

			$order      = wc_get_order( $order_id );
			$order_data = $order->get_data();

			$props = array();

			try {
				$metadata = $order->get_meta_data();

				foreach ( $metadata as $_metadata ) {
					if ( '_billing_functia' === $_metadata->key || 'billing_functia' === $_metadata->key ) {
						$props['functia'] = $_metadata->value;
					}
					if ( '_billing_sex' === $_metadata->key || 'billing_sex' === $_metadata->key ) {
						$props['sex'] = $_metadata->value;
					}
				}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( Exception $e ) {
				// Custom fields not found.
			}

			$email      = $order_data['billing']['email'];
			$first_name = $order_data['billing']['first_name'];
			$last_name  = $order_data['billing']['last_name'];

			$phone = ( ! empty( $order_data['billing']['phone'] ) ) ? $order_data['billing']['phone'] : '';

			$props['phone'] = $phone;

			$options = array();

			$segments     = get_option( 'newsman_segments' );
			$raw_segments = $segments;
			if ( ! empty( $segments ) ) {
				$segments = array( 'segments' => array( $segments ) );
			}

			$options['segments'] = array( $raw_segments );

			$form_id = get_option( 'newsman_form_id' );
			if ( ! empty( $form_id ) ) {
				$options['form_id'] = $form_id;
			}

			$checkout_type = get_option( 'newsman_checkoutnewslettertype' );

			try {
				if ( 'init' === $checkout_type ) {

					$ret = $this->client->subscriber->initSubscribe(
						$list_id,
						$email,
						$first_name,
						$last_name,
						$this->get_user_ip(),
						$props,
						$options
					);

				} elseif ( 'save' === $checkout_type ) {

					$sub_id = $this->client->subscriber->saveSubscribe(
						$list_id,
						$email,
						$first_name,
						$last_name,
						$this->get_user_ip(),
						$props
					);

					if ( ! empty( $segments ) ) {
						$segments = $segments['segments'][0];
					}

					$ret = $this->client->segment->addSubscriber( $segments, $sub_id );

				}

				// SMS sync.
				if ( ! empty( $checkout_sms ) && 'on' === $checkout_sms ) {

					if ( ! empty( $phone ) ) {
						$ret = $this->client->sms->saveSubscribe( $smslist, $phone, $first_name, $last_name, $this->get_user_ip(), $props );
					}
				}
			} catch ( Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( $e->getMessage() );
			}
		}
	}

	/**
	 * Init Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'newsman_fetch_data' ) );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'newsman_checkout' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'newsman_checkout_action' ), 10, 2 );
		// Order status change hooks.
		add_action( 'woocommerce_order_status_pending', array( $this, 'pending' ) );
		add_action( 'woocommerce_order_status_failed', array( $this, 'failed' ) );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'hold' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'processing' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'completed' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'refunded' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancelled' ) );
		add_action( 'before_woocommerce_init', 'before_woocommerce_hpos' );

		/**
		 * Declare compatibility with feature "custom_order_tables".
		 *
		 * @return void
		 */
		function before_woocommerce_hpos() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
		// Admin menu hook.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		// Add links to plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
		// Enqueue plugin styles.
		// add_action('wp_enqueue_scripts', array($this, 'register_plugin_styles'));
		// Enqueue plugin styles in admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		// Enqueue WordPress ajax library.
		add_action( 'wp_head', array( $this, 'add_ajax_library' ) );
		// Enqueue plugin scripts.
		// add_action('wp_enqueue_scripts', array($this, 'register_plugin_scripts'));
		// Enqueue plugin scripts in admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_plugin_scripts' ) );
		// Do ajax form subscribe.
		add_action( 'wp_ajax_nopriv_newsman_ajax_subscribe', array( $this, 'newsman_ajax_subscribe' ) );
		add_action( 'wp_ajax_newsman_ajax_subscribe', array( $this, 'newsman_ajax_subscribe' ) );
		// Check if plugin is active.
		add_action( 'wp_ajax_newsman_ajax_check_plugin', array( $this, 'newsman_ajax_check_plugin' ) );
		// Widget auto init.
		add_action( 'init', array( $this, 'init_widgets' ) );
	}

	/**
	 * Generate widget.
	 *
	 * @param array $attributes Attributes array.
	 * @return string
	 */
	public function generate_widget( $attributes ) {

		if ( empty( $attributes ) || ! is_array( $attributes ) || ! array_key_exists( 'formid', $attributes ) ) {
			return '';
		}
		$attributes['formid'] = sanitize_text_field( $attributes['formid'] );
		$c                    = substr_count( $attributes['formid'], '-' );

		// Backwards compatible.
		if ( 2 === $c || '2' === $c ) {
			return '<div id="' . esc_attr( $attributes['formid'] ) . '"></div>';
		} else {
			$attributes['formid'] = str_replace( 'nzm-container-', '', $attributes['formid'] );

			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			return '<script async src="https://retargeting.newsmanapp.com/js/embed-form.js" data-nzmform="' . esc_attr( $attributes['formid'] ) . '"></script>';
		}
	}

	/**
	 * Init widgets, add shortcode.
	 *
	 * @return void
	 */
	public function init_widgets() {
		add_shortcode( 'newsman_subscribe_widget', array( $this, 'generate_widget' ) );
	}

	/**
	 * Adds a menu item for Newsman on the Admin page
	 *
	 * @return void
	 */
	public function admin_menu() {
		// phpcs:ignore WordPress.WP.Capabilities.RoleFound
		add_menu_page( 'Newsman', 'Newsman', 'administrator', 'Newsman', array( $this, 'include_admin_page' ), plugin_dir_url( __FILE__ ) . 'src/img/newsman-mini.png' );
		// phpcs:ignore WordPress.WP.Capabilities.RoleFound
		add_submenu_page( 'Newsman', 'Sync', 'Sync', 'administrator', 'NewsmanSync', array( $this, 'include_admin_sync_page' ) );
		// phpcs:ignore WordPress.WP.Capabilities.RoleFound
		add_submenu_page( 'Newsman', 'Remarketing', 'Remarketing', 'administrator', 'NewsmanRemarketing', array( $this, 'include_admin_remarketing_page' ) );
		// phpcs:ignore WordPress.WP.Capabilities.RoleFound
		add_submenu_page( 'Newsman', 'SMS', 'SMS', 'administrator', 'NewsmanSMS', array( $this, 'include_admin_sms_page' ) );
		// phpcs:ignore WordPress.WP.Capabilities.RoleFound
		add_submenu_page( 'Newsman', 'Settings', 'Settings', 'administrator', 'NewsmanSettings', array( $this, 'include_admin_settings_page' ) );
		/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		add_submenu_page("Newsman", "Widget", "Widget", "administrator", "NewsmanWidget", array($this, "include_admin_widget_page"));
		*/
		// phpcs:ignore WordPress.WP.Capabilities.RoleFound
		add_submenu_page( 'Newsman', 'Oauth', 'Oauth', 'administrator', 'NewsmanOauth', array( $this, 'include_oauth_page' ) );
	}

	/**
	 * Includes the html for the admin page..
	 *
	 * @return void
	 */
	public function include_admin_page() {
		include 'src/backend.php';
	}

	/**
	 * Includes the html for the admin settings page.
	 *
	 * @return void
	 */
	public function include_admin_settings_page() {
		include 'src/backend-settings.php';
	}

	/**
	 * Includes the html for the admin sync page.
	 *
	 * @return void
	 */
	public function include_admin_sync_page() {
		include 'src/backend-sync.php';
	}

	/**
	 * Include OAuth page.
	 *
	 * @return void
	 */
	public function include_oauth_page() {
		include 'src/backend-oauth.php';
	}

	/**
	 * Includes the html for the admin remarketing page.
	 *
	 * @return void
	 */
	public function include_admin_remarketing_page() {
		include 'src/backend-remarketing.php';
	}

	/**
	 * Includes the html for the admin SMS page.
	 *
	 * @retnr void
	 */
	public function include_admin_sms_page() {
		include 'src/backend-sms.php';
	}

	/**
	 * Includes the html for the admin widget page.
	 *
	 * @return void
	 */
	public function include_admin_widget_page() {
		include 'src/backend-widget.php';
	}

	/**
	 * Binds the Newsman menu item to the menu.
	 *
	 * @param array $links Array with links.
	 * @return array
	 */
	public function plugin_links( $links ) {
		$custom_links = array(
			'<a href="' . admin_url( 'admin.php?page=NewsmanSettings' ) . '">Settings</a>',
		);
		return array_merge( $links, $custom_links );
	}

	/**
	 * Register plugin custom css.
	 *
	 * @return void
	 */
	public function register_plugin_styles() {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_style( 'newsman_css', plugins_url( 'newsmanapp/src/css/style.css' ) );
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'newsman_css' );
	}

	/**
	 * Register plugin custom javascript..
	 *
	 * @return void
	 */
	public function register_plugin_scripts() {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NotInFooter
		wp_register_script( 'newsman_js', plugins_url( 'newsmanapp/src/js/script.js' ), array( 'jquery' ) );
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'newsman_js' );
	}

	/**
	 * Includes ajax library that WordPress uses for processing ajax requests.
	 *
	 * @return void
	 */
	public function add_ajax_library() {
		$html  = '<script type="text/javascript">';
		$html .= 'var ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '"';
		$html .= '</script>';

		if ( ! class_exists( 'WooCommerce' ) ) {
			$remarketingid = get_option( 'newsman_remarketingid' );
			if ( ! empty( $remarketingid ) ) {
				$html .= "
                    <script type='text/javascript'>
                    var _nzm = _nzm || []; var _nzm_config = _nzm_config || []; _nzm_tracking_server = '" . self::$endpoint_host . "';
                    (function() {var a, methods, i;a = function(f) {return function() {_nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
                    }};methods = ['identify', 'track', 'run'];for(i = 0; i < methods.length; i++) {_nzm[methods[i]] = a(methods[i])};
                    s = document.getElementsByTagName('script')[0];var script_dom = document.createElement('script');script_dom.async = true;
                    script_dom.id = 'nzm-tracker';script_dom.setAttribute('data-site-id', '" . esc_js( $remarketingid ) . "');
                    script_dom.src = '" . self::$endpoint . "';s.parentNode.insertBefore(script_dom, s);})();
                    </script>
                    ";
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}

	/**
	 * Precess ajax request for the subscription form..
	 * Initializes the subscription process for a new user.
	 *
	 * @return void
	 */
	public function newsman_ajax_subscribe() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['email'] ) && ! empty( $_POST['email'] ) ) {

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$email = isset( $_POST['email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['email'] ) ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$name = isset( $_POST['name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$prename = isset( $_POST['prename'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['prename'] ) ) ) : '';
			$list_id = get_option( 'newsman_list' );
			try {
				if ( $this->newsman_list_email_exists( $email, $list_id ) ) {
					$message = 'Email deja inscris la newsletter';
					$this->send_message_front( 'error', $message );
					die();
				}

				$ret = $this->client->subscriber->initSubscribe(
					$list_id, /* The list id */
					$email, /* Email address of subscriber */
					$prename, /* Firstname of subscriber, can be null. */
					$name, /* Lastname of subscriber, can be null. */
					$this->get_user_ip(), /* IP address of subscriber */
					null, /* Hash array with props (can be later used to build segment criteria) */
					null
				);

				$message = get_option( 'newsman_widget_confirm' );

				$this->send_message_front( 'success', $message );

			} catch ( Exception $e ) {
				$message = get_option( 'newsman_widget_infirm' );
				$this->send_message_front( 'error', $message );
			}
		}
		die();
	}

	/**
	 * Check if email is already subscriber in Newsman.
	 *
	 * @param string $email Email to verify.
	 * @param string $list_id List ID.
	 * @return bool
	 */
	public function newsman_list_email_exists( $email, $list_id ) {
		$bool = false;

		try {
			$ret = $this->client->subscriber->getByEmail(
				$list_id, /* The list id */
				$email /* The email address */
			);

			if ( 'subscribed' === $ret['status'] ) {
				$bool = true;
			}

			return $bool;
		} catch ( Exception $e ) {
			return $bool;
		}
	}

	/**
	 * Creates and return a message for frontend (because of the echo statement).
	 *
	 * @param string $status       The status of the message (the css class of the message).
	 * @param string $message      The actual message.
	 * @return void
	 */
	public function send_message_front( $status, $message ) {
		$this->message = wp_json_encode(
			array(
				'status'  => $status,
				'message' => $message,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->message;
	}

	/**
	 * Creates and return a message for backend.
	 *
	 * @param string $status       The status of the message (the css class of the message).
	 * @param string $message      The actual message.
	 * @return void
	 */
	public function set_message_backend( $status, $message ) {
		$this->message = array(
			'status'  => $status,
			'message' => $message,
		);
	}

	/**
	 * Returns the current message for the backend.
	 *
	 * @return array The message array
	 */
	public function get_backend_message() {
		return $this->message;
	}

	/**
	 * Get the subscriber ip address. (Necessary for Newsman subscription).
	 *
	 * @return string The ip address.
	 */
	public function get_user_ip() {
		$cl      = isset( $_SERVER['HTTP_CLIENT_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) ) : '';
		$forward = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		$remote  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( filter_var( $cl, FILTER_VALIDATE_IP ) ) {
			$ip = $cl;
		} elseif ( filter_var( $forward, FILTER_VALIDATE_IP ) ) {
			$ip = $forward;
		} else {
			$ip = $remote;
		}
		return $ip;
	}

	/**
	 * Includes the html for the subscription form.
	 *
	 * @return void
	 */
	public function newsman_display_form() {
		include 'src/frontend.php';
	}

	/**
	 * Check is newsman plugin active with AJAX..
	 *
	 * @return void
	 */
	public function newsman_ajax_check_plugin() {
		$active_plugins = get_option( 'active_plugins' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$plugin = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';

		if ( in_array( $plugin, $active_plugins, true ) ) {
			echo wp_json_encode( array( 'status' => 1 ) );
			exit();
		}
		echo wp_json_encode( array( 'status' => 0 ) );
		exit();
	}

	/**
	 * Format string safe for CSV.
	 *
	 * @param string $str String to format.
	 * @return string
	 */
	public function safe_for_csv( $str ) {
		return '"' . str_replace( '"', '""', $str ) . '"';
	}

	/**
	 * Execute import to Newsman API.
	 *
	 * @param array          $data Array with data for CSV file.
	 * @param string         $list_id List ID.
	 * @param Newsman_Client $client API client.
	 * @param string         $source Source.
	 * @param array          $segments Segments.
	 * @return void
	 * @throws Exception     Throw standard exception.
	 */
	public function import_data( &$data, $list_id, $client, $source, $segments = null ) {
		$csv = '"email","firstname","lastname","tel","source"' . PHP_EOL;
		foreach ( $data as $_dat ) {
			$csv .= sprintf(
				'%s,%s,%s,%s',
				$this->safe_for_csv( $_dat['email'] ),
				$this->safe_for_csv( $_dat['firstname'] ),
				$this->safe_for_csv( $_dat['lastname'] ),
				$this->safe_for_csv( $_dat['tel'] ),
				$this->safe_for_csv( $source )
			);
			$csv .= PHP_EOL;
		}
		$ret = null;
		try {
			if ( is_array( $segments ) && count( $segments ) > 0 ) {
				$ret = $client->import->csv( $list_id, $segments, $csv );
			} else {
				$ret = $client->import->csv( $list_id, array(), $csv );
			}
			if ( empty( $ret ) ) {
				throw new Exception( 'Import failed' );
			}
		} catch ( Exception $e ) {
			throw new Exception( 'Import failed' );
		}
		$data = array();
	}
}

	$wp_newsman = new WP_Newsman();

	// include the widget.
	// include 'class-newsman-subscribe-widget.php';.
