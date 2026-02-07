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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Subscribers WooCommerce Feed
 *
 * @class \Newsman\Export\Retriever\SubscribersWoocommerceFeed
 */
class SubscribersWoocommerceFeed extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Subscribers emails cache
	 *
	 * @var array
	 */
	protected $emails_cache = array();

	/**
	 * Process subscribers retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws \Exception On errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		$data['default_page_size'] = self::DEFAULT_PAGE_SIZE;

		$processed_params = $this->process_list_parameters( $data, $blog_id );

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Export WooCommerce subscribers %1$d, %2$d, blog ID %3$s', 'newsman' ),
				$processed_params['start'],
				$processed_params['limit'],
				$blog_id
			)
		);

		$args = array(
			'status' => 'completed',
			'offset' => $processed_params['start'],
			'limit'  => $processed_params['limit'],
		);

		if ( isset( $processed_params['sort'] ) ) {
			$args['orderby'] = $processed_params['sort'];
			$args['order']   = $processed_params['order'];
		} else {
			$args['orderby'] = 'date_created_gmt';
			$args['order']   = 'DESC';
		}

		foreach ( $processed_params['filters'] as $filter ) {
			$field    = $filter['field'];
			$operator = $this->get_expressions_definition()[ $filter['operator'] ];
			$value    = $filter['value'];

			if ( 'date_created' === $field ) {
				$args['date_created'] = $operator . $value;
			} elseif ( 'date_modified' === $field ) {
				$args['date_modified'] = $operator . $value;
			} elseif ( 'billing_email' === $field ) {
				$args['billing_email'] = $value;
			} else {
				if ( ! isset( $args['meta_query'] ) ) {
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					$args['meta_query'] = array();
				}
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				$args['meta_query'][] = array(
					'key'     => $field,
					'value'   => $value,
					'compare' => $operator,
				);
			}
		}

		$args = apply_filters(
			'newsman_export_retriever_subscribers_woocommerce_feed_process_fetch',
			$args,
			array(
				'data'    => $data,
				'blog_id' => $blog_id,
			)
		);

		$orders = wc_get_orders( $args );

		if ( empty( $orders ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			return array();
		}

		$result = array();
		foreach ( $orders as $order ) {
			try {
				if ( ! $this->is_valid_subscriber( $order, $blog_id ) ) {
					continue;
				}
				$row = $this->process_subscriber( $order, $blog_id );
				if ( false !== $row ) {
					$result[] = $row;
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Exported WooCommerce subscribers %1$d, %2$d, blog ID %3$s', 'newsman' ),
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
			'email'       => array(
				'field' => 'billing_email',
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
			'created_at'  => 'date_created',
			'modified_at' => 'date_modified',
			'email'       => 'billing_email',
		);
	}

	/**
	 * Process WooCommerce subscriber
	 *
	 * @param \WC_Order $subscriber Subscriber.
	 * @param null|int  $blog_id WP blog ID.
	 * @return array|false
	 */
	public function process_subscriber( $subscriber, $blog_id = null ) {
		$order = $subscriber;

		if ( isset( $this->emails_cache[ $order->get_billing_email() ] ) ) {
			return false;
		}
		$this->emails_cache[ $order->get_billing_email() ] = true;

		$row = array(
			'email'           => $order->get_billing_email(),
			'firstname'       => ( ! empty( $order->get_billing_first_name() ) ) ? $order->get_billing_first_name() : '',
			'lastname'        => ( ! empty( $order->get_billing_last_name() ) ) ? $order->get_billing_last_name() : '',
			'date_subscribed' => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'confirmed'       => 1,
			'source'          => 'WooCommerce orders',
		);

		$telephone = $this->get_phone_from_order( $order );
		if ( ! empty( $telephone ) ) {
			$row['phone'] = $telephone;
		}

		$ip = $this->get_ip_from_order( $order, $blog_id );
		if ( ! empty( $ip ) ) {
			$row['ip'] = $ip;
		}

		$row['additional'] = array();
		foreach ( $this->remarketing_config->get_customer_attributes() as $attribute ) {
			if ( strpos( $attribute, 'billing_' ) === 0 || strpos( $attribute, 'shipping_' ) === 0 ) {
				$getter = 'get_' . $attribute;
				if ( method_exists( $order, $getter ) ) {
					$row['additional'][ $attribute ] = $order->$getter();
				}
			}
		}

		return apply_filters(
			'newsman_export_retriever_subscribers_woocommerce_feed_process_subscriber',
			$row,
			array(
				'subscriber' => $subscriber,
				'blog_id'    => $blog_id,
			)
		);
	}

	/**
	 * Is valid subscriber
	 *
	 * @param \WC_Order $subscriber Subscriber.
	 * @param null|int  $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_valid_subscriber( $subscriber, $blog_id = null ) {
		return $subscriber instanceof \WC_Order && $subscriber->get_billing_email();
	}

	/**
	 * Get phone from order
	 *
	 * @param \WC_Order $order Order.
	 * @return false|string
	 */
	public function get_phone_from_order( $order ) {
		if ( ! $this->remarketing_config->is_send_telephone() ) {
			return false;
		}

		if ( ! empty( $order->get_billing_phone() ) ) {
			return $this->clean_phone( $order->get_billing_phone() );
		}

		return '';
	}

	/**
	 * Get IP address from order
	 *
	 * @param \WC_Order $order Order.
	 * @param null|int  $blog_id WP blog ID.
	 * @return string
	 */
	public function get_ip_from_order( $order, $blog_id = null ) {
		$ip = '';
		$ip = apply_filters(
			'newsman_export_retriever_subscribers_woocommerce_feed_get_user_ip',
			$ip,
			array(
				'order'   => $order,
				'blog_id' => $blog_id,
			)
		);
		if ( ! empty( $ip ) ) {
			return $ip;
		}

		if ( method_exists( $order, 'get_customer_ip_address' ) ) {
			$ip = $order->get_customer_ip_address();
		}

		if ( ! empty( $ip ) ) {
			return $ip;
		}

		$server_ip = $this->config->get_server_ip( $blog_id );
		if ( ! empty( $server_ip ) && \Newsman\User\HostIpAddress::NOT_FOUND !== $server_ip ) {
			return $server_ip;
		}

		return '';
	}
}
