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
 * Class Export Retriever Orders
 *
 * @class \Newsman\Export\Retriever\Orders
 */
class Orders extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Process orders retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process( $data = array(), $blog_id = null ) {
		if ( isset( $data['order_id'] ) && ! is_array( $data['order_id'] ) ) {
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

		$data['default_page_size'] = self::DEFAULT_PAGE_SIZE;

		$processed_params = $this->process_list_parameters( $data, $blog_id );

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Export orders %1$d, %2$d, blog ID %3$s', 'newsman' ),
				$processed_params['start'],
				$processed_params['limit'],
				$blog_id
			)
		);

		$date_limit        = $this->remarketing_config->get_order_date() . ' 00:00:00';
		$all_statuses      = array_keys( wc_get_order_statuses() );
		$filtered_statuses = array_diff( $all_statuses, array( 'wc-checkout-draft' ) );

		$args = array(
			'limit'        => $processed_params['limit'],
			'offset'       => $processed_params['start'],
			'status'       => $filtered_statuses,
			'date_created' => '>=' . $date_limit,
		);

		if ( isset( $processed_params['sort'] ) ) {
			$args['orderby'] = $processed_params['sort'];
			$args['order']   = $processed_params['order'];
		}

		foreach ( $processed_params['filters'] as $filter ) {
			$field    = $filter['field'];
			$operator = $this->get_expressions_definition()[ $filter['operator'] ];
			$value    = $filter['value'];

			if ( 'date_created' === $field ) {
				$args['date_created'] = $operator . $value;
			} elseif ( 'date_modified' === $field ) {
				$args['date_modified'] = $operator . $value;
			} elseif ( 'id' === $field ) {
				if ( 'in' === $filter['operator'] ) {
					$args['include']  = (array) $value;
					$args['post__in'] = (array) $value;
				} elseif ( 'nin' === $filter['operator'] ) {
					$args['exclude']      = (array) $value;
					$args['post__not_in'] = (array) $value;
				} else {
					$args['include']  = array( $value );
					$args['post__in'] = array( $value );
				}
			} elseif ( 'status' === $field ) {
				$args['status'] = $value;
			} elseif ( 'billing_email' === $field ) {
				$args['billing_email'] = $value;
			} else {
				if ( ! isset( $args['meta_query'] ) ) {
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					$args['meta_query'] = array();
				}
				$args['meta_query'][] = array(
					'key'     => $field,
					'value'   => $value,
					'compare' => $operator,
				);
			}
		}

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
			\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$query  = new \WC_Order_Query( $args );
			$query  = apply_filters(
				'newsman_export_retriever_orders_process_custom_orders_query',
				$query,
				array(
					'data'    => $data,
					'blog_id' => $blog_id,
				)
			);
			$orders = $query->get_orders();
		} else {
			$args   = apply_filters(
				'newsman_export_retriever_orders_process_args_fetch',
				$args,
				array(
					'data'    => $data,
					'blog_id' => $blog_id,
				)
			);
			$orders = wc_get_orders( $args );
		}

		if ( empty( $orders ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
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
				$processed_params['start'],
				$processed_params['limit'],
				$blog_id
			)
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Get allowed request parameters
	 *
	 * @return array
	 */
	public function get_where_parameters_mapping() {
		return array(
			'created_at'  => array(
				'field' => 'date_created',
				'type'  => 'string',
			),
			'modified_at' => array(
				'field' => 'date_modified',
				'type'  => 'string',
			),
			'order_id'    => array(
				'field' => 'id',
				'type'  => 'int',
			),
			'order_ids'   => array(
				'field'    => 'id',
				'multiple' => true,
				'type'     => 'int',
			),
			'email'       => array(
				'field' => 'billing_email',
				'type'  => 'string',
			),
			'status'      => array(
				'field' => 'status',
				'type'  => 'string',
			),
		);
	}

	/**
	 * Get allowed sort fields
	 *
	 * @return array
	 */
	public function get_allowed_sort_fields() {
		return array(
			'order_id'    => 'id',
			'created_at'  => 'date_created',
			'modified_at' => 'date_modified',
			'email'       => 'billing_email',
		);
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
				'id'        => $prod['product_id'],
				'name'      => $prod['name'],
				'quantity'  => (int) $prod['quantity'],
				'price'     => (float) $_price,
				'price_old' => (float) $_price_old,
				'image_url' => $image_url,
				'url'       => $url,
			);
		}

		$row = array(
			'id'                   => $order->get_order_number(),
			'billing_name'         => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'billing_company_name' => $order->get_billing_company(),
			'billing_phone'        => $this->telephone->clean( $item_data['billing']['phone'] ),
			'customer_email'       => $order->get_billing_email(),
			'shipping_amount'      => (float) $item_data['shipping_total'],
			'tax_amount'           => (float) wc_format_decimal( $order->get_total_tax(), 2 ),
			'total_amount'         => (float) wc_format_decimal( $order->get_total(), 2 ),
			'currency'             => $order->get_currency(),
			'subtotal_amount'      => (float) wc_format_decimal( $order->get_subtotal(), 2 ),
			'status'               => $order->get_status(),
			'date_created'         => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
			'date_modified'        => $order->get_date_modified()->format( 'Y-m-d H:i:s' ),
			'line_items'           => $products_data,
		);

		return apply_filters(
			'newsman_export_retriever_orders_process_order',
			$row,
			array(
				'order'   => $order,
				'blog_id' => $blog_id,
			)
		);
	}
}
