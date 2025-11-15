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

use Newsman\Export\Order\Mapper as OrderMapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Send Orders
 *
 * @class \Newsman\Export\Retriever\SendOrders
 */
class SendOrders extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 200;

	/**
	 * Default batch API size
	 */
	public const BATCH_SIZE = 500;

	/**
	 * Order Mapper
	 *
	 * @var OrderMapper
	 */
	protected $order_mapper;

	/**
	 * Class construct
	 */
	public function __construct() {
		parent::__construct();
		$this->order_mapper = new OrderMapper();
	}

	/**
	 * Process send orders retriever
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
				sprintf( esc_html__( 'Send order %1$d, store ID %2$s', 'newsman' ), $data['order_id'], $blog_id )
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
				sprintf( esc_html__( 'Sent order %1$d, store ID %2$s', 'newsman' ), $data['order_id'], $blog_id )
			);

			return $result;
		}

		$start        = ! empty( $data['start'] ) && $data['start'] > 0 ? $data['start'] : 0;
		$limit        = empty( $data['limit'] ) ? self::DEFAULT_PAGE_SIZE : $data['limit'];
		$cronlast     = ! empty( $data['cronlast'] ) && 'true' === $data['cronlast'] ? true : false;
		$date_created = empty( $data['date_created'] ) ? null : $data['date_created'];
		$pre_count    = empty( $data['pre_count'] ) ? null : $data['pre_count'];

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID, 4: Date created */
				esc_html__( 'Send orders %1$d, %2$d, blog ID %3$s, date %4$s', 'newsman' ),
				$start,
				$limit,
				$blog_id,
				$date_created
			)
		);

		$orders = $this->get_orders( $data, $start, $limit, $cronlast, $blog_id, $date_created, $pre_count );

		if ( empty( $orders ) ) {
			return array();
		}

		$result       = array();
		$count_orders = 0;
		foreach ( $orders as $order ) {
			try {
				$result[] = $this->process_order( $order, $blog_id );
				++$count_orders;
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		unset( $orders );
		$batches = array_chunk( $result, self::BATCH_SIZE );
		unset( $result );

		$count       = 0;
		$api_results = array();
		foreach ( $batches as $batch ) {
			try {
				$context = new \Newsman\Service\Context\Remarketing\SaveOrders();
				$context->set_blog_id( $blog_id )
					->set_list_id( $this->config->get_list_id() )
					->set_orders( $batch );

				$export        = new \Newsman\Service\Remarketing\SaveOrders();
				$api_results[] = $export->execute( $context );

				$count += count( $batch );

				unset( $context );
				unset( $export );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID, 4: Date created */
				esc_html__( 'Sent orders %1$d, %2$d, blog ID %3$s, Date %4$s', 'newsman' ),
				$start,
				$limit,
				$blog_id,
				$date_created
			)
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return array(
			'status'  => sprintf(
				/* translators: 1: Sent orders count, 2: Total orders count */
				esc_html__( 'Sent to NewsMAN %1$d orders out of a total of %2$d.', 'newsman' ),
				$count,
				$count_orders
			),
			'results' => $api_results,
		);
	}

	/**
	 * Get orders
	 *
	 * @param array       $data Data.
	 * @param int         $start Start.
	 * @param int         $limit Limit.
	 * @param bool        $cronlast Cron last.
	 * @param null|int    $blog_id WP blog ID.
	 * @param null|string $date_created Consider orders after date.
	 * @param null|int    $pre_count Pre count of orders.
	 * @return \WC_Order[]
	 */
	public function get_orders( $data, $start, $limit, $cronlast, $blog_id = null, $date_created = null, $pre_count = null ) {
		$date_limit = $this->remarketing_config->get_order_date() . ' 00:00:00';
		if ( ! empty( $date_created ) ) {
			$date_limit = $date_created . ' 00:00:00';
		}
		$filtered_statuses = $this->get_filtered_statuses();

		if ( true === $cronlast ) {
			if ( ! empty( $pre_count ) ) {
				$count = $pre_count;
			} else {
				$count = $this->get_count_orders( $blog_id, $date_created );
			}
			$start = $count - $limit;
			if ( $start < 0 ) {
				$start = 0;
			}
		}

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
			\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$query = new \WC_Order_Query(
				array(
					'limit'        => $limit,
					'offset'       => $start,
					'status'       => $filtered_statuses,
					'date_created' => '>=' . $date_limit,
				)
			);
			$query = apply_filters(
				'newsman_export_retriever_send_orders_process_custom_orders_query',
				$query,
				array(
					'data'         => $data,
					'blog_id'      => $blog_id,
					'date_created' => $date_limit,
				)
			);
			return $query->get_orders();
		} else {
			$args = array(
				'limit'        => $limit,
				'offset'       => $start,
				'status'       => $filtered_statuses,
				'date_created' => '>=' . $date_limit,
			);
			$args = apply_filters(
				'newsman_export_retriever_send_orders_process_args_fetch',
				$args,
				array(
					'data'         => $data,
					'blog_id'      => $blog_id,
					'date_created' => $date_limit,
				)
			);
			return wc_get_orders( $args );
		}
	}

	/**
	 * Process order
	 *
	 * @param \WC_Order $order Order instance.
	 * @param null|int  $blog_id WP blog ID.
	 * @return array
	 */
	public function process_order( $order, $blog_id = null ) {
		$order_data      = $this->order_mapper->to_array( $order );
		$row             = $order_data['details'];
		$row['products'] = $order_data['products'];

		return apply_filters(
			'newsman_export_retriever_send_orders_process_order',
			$row,
			array(
				'order'   => $order,
				'blog_id' => $blog_id,
			)
		);
	}

	/**
	 * Get total count of subscribers
	 *
	 * @param null|int    $blog_id WP blog ID.
	 * @param null|string $date_created Consider orders after date.
	 * @return int|null
	 */
	public function get_count_orders( $blog_id = null, $date_created = null ) {
		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$date_limit = $this->remarketing_config->get_order_date() . ' 00:00:00';
		if ( ! empty( $date_created ) ) {
			$date_limit = $date_created . ' 00:00:00';
		}
		$filtered_statuses = $this->get_filtered_statuses();

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
			\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$query = new \WC_Order_Query(
				array(
					'limit'        => -1,
					'status'       => $filtered_statuses,
					'date_created' => '>=' . $date_limit,
					'return'       => 'ids',
				)
			);
			$query = apply_filters(
				'newsman_export_retriever_send_orders_process_custom_orders_count_query',
				$query,
				array(
					'blog_id'      => $blog_id,
					'date_created' => $date_limit,
				)
			);
			$count = count( $query->get_orders() );
		} else {
			$args = array(
				'limit'        => -1,
				'status'       => $filtered_statuses,
				'date_created' => '>=' . $date_limit,
				'return'       => 'ids',
			);

			$args = apply_filters(
				'newsman_export_retriever_send_orders_process_args_fetch',
				$args,
				array(
					'blog_id'      => $blog_id,
					'date_created' => $date_limit,
				)
			);

			$count = count( wc_get_orders( $args ) );
		}

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $count;
	}

	/**
	 * Get order filtered statuses
	 *
	 * @return array
	 */
	public function get_filtered_statuses() {
		$all_statuses      = array_keys( wc_get_order_statuses() );
		$filtered_statuses = array_diff( $all_statuses, array( 'wc-checkout-draft' ) );
		return apply_filters( 'newsman_export_retriever_send_orders_filtered_statuses', $filtered_statuses );
	}

	/**
	 * Get batch size
	 *
	 * @return int
	 */
	public function get_batch_size() {
		$batch_size = self::BATCH_SIZE;
		return apply_filters( 'newsman_export_retriever_send_orders_batch_size', $batch_size );
	}
}
