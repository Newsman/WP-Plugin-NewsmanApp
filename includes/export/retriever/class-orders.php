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

namespace Newsman\Export\Retriever;

use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Export Retriever Orders
 *
 * @class \Newsman\Export\Retriever\Orders
 */
class Orders implements RetrieverInterface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Remarketing Config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Telephone util
	 *
	 * @var Telephone
	 */
	protected $telephone;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->remarketing_config = RemarketingConfig::init();
		$this->logger             = Logger::init();
		$this->telephone          = new Telephone();
	}

	/**
	 * Process orders retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process( $data = array(), $blog_id = null ) {
		if ( isset( $data['order_id'] ) ) {
			if ( empty( $data['order_id'] ) ) {
				return array();
			}

			$this->logger->info(
				/* translators: 1: Order ID, 2: WordPress blog ID */
				sprintf( esc_html__( 'Export order %1$d, store ID %2$s', 'newsman' ), $data['order_id'], $blog_id )
			);

			if ( $this->is_different_blog( $blog_id ) ) {
				switch_to_blog( $blog_id );
			}
			$order = wc_get_order( $data['order_id'] );
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			if ( empty( $order ) ) {
				return array();
			}
			$result = array( $this->process_order( $order, $blog_id ) );

			$this->logger->info(
				/* translators: 1: Order ID, 2: WordPress blog ID */
				sprintf( esc_html__( 'Exported order %1$d, store ID %2$s', 'newsman' ), $data['order_id'], $blog_id )
			);

			return $result;
		}

		$start = ! empty( $data['start'] ) && $data['start'] > 0 ? $data['start'] : 0;
		$limit = empty( $data['limit'] ) ? self::DEFAULT_PAGE_SIZE : $data['limit'];

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Export orders %1$d, %2$d, blog ID %3$s', 'newsman' ),
				$start,
				$limit,
				$blog_id
			)
		);

		$all_statuses      = array_keys( wc_get_order_statuses() );
		$filtered_statuses = array_diff( $all_statuses, array( 'wc-checkout-draft' ) );
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
			\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$query  = new \WC_Order_Query(
				array(
					'limit'  => $limit,
					'offset' => $start,
					'status' => $filtered_statuses,
				)
			);
			$orders = $query->get_orders();
		} else {
			$args   = array(
				'limit'  => $limit,
				'offset' => $start,
				'status' => $filtered_statuses,
			);
			$orders = wc_get_orders( $args );
		}

		if ( empty( $orders ) ) {
			return array();
		}

		$result = array();
		foreach ( $orders as $order ) {
			try {
				$result[] = $this->process_order( $order, $blog_id );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Exported orders %1$d, %2$d, blog ID %3$s', 'newsman' ),
				$start,
				$limit,
				$blog_id
			)
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Process order
	 *
	 * @param \WC_Order $order Order instance.
	 * @param null|int  $blog_id WP blog ID.
	 * @return array
	 */
	public function process_order( $order, $blog_id = null ) {
		$products      = $order->get_items();
		$products_data = array();

		$item_data = $order->get_data();

		foreach ( $products as $prod ) {
			$_prod = wc_get_product( $prod['product_id'] );

			$image_id  = $_prod->get_image_id();
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );
			$url       = get_permalink( $_prod->get_id() );

			$_price_old = 0;
			if ( empty( $_prod->get_sale_price() ) ) {
				$_price = $_prod->get_price();
			} else {
				$_price     = $_prod->get_sale_price();
				$_price_old = $_prod->get_regular_price();
			}

			$products_data[] = array(
				'id'        => (string) $prod['product_id'],
				'name'      => $prod['name'],
				'quantity'  => (int) $prod['quantity'],
				'price'     => (float) $_price,
				'price_old' => (float) $_price_old,
				'image_url' => $image_url,
				'url'       => $url,
			);
		}

		$date = $order->get_date_created();
		$date = $date->getTimestamp();

		$row = array(
			'order_no'      => $order->get_order_number(),
			'date'          => $date,
			'status'        => $order->get_status(),
			'lastname'      => $order->get_billing_last_name(),
			'firstname'     => $order->get_billing_first_name(),
			'email'         => $order->get_billing_email(),
			'phone'         => $this->telephone->clean( $item_data['billing']['phone'] ),
			'state'         => $item_data['billing']['state'],
			'city'          => $item_data['billing']['city'],
			'address'       => $item_data['billing']['address_1'],
			'discount'      => ( empty( $item_data['billing']['discount_total'] ) ) ? 0 :
				(float) $item_data['billing']['discount_total'],
			'discount_code' => '',
			'shipping'      => (float) $item_data['shipping_total'],
			'fees'          => 0,
			'rebates'       => 0,
			'total'         => (float) wc_format_decimal( $order->get_total(), 2 ),
			'products'      => $products_data,
		);

		foreach ( $this->remarketing_config->get_customer_attributes() as $attribute ) {
			if ( strpos( $attribute, 'billing_' ) === 0 || strpos( $attribute, 'shipping_' ) === 0 ) {
				$getter = 'get_' . $attribute;
				if ( method_exists( $order, $getter ) ) {
					$row[ $attribute ] = $order->$getter();
				}
			}
		}

		if ( ! $this->remarketing_config->is_send_telephone() ) {
			unset( $row['phone'] );
		}

		return $row;
	}

	/**
	 * Is different WP blog than current
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_different_blog( $blog_id = null ) {
		$current_blog_id = get_current_blog_id();
		return ( null !== $current_blog_id ) && ( (int) $blog_id !== $current_blog_id );
	}
}
