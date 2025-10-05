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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Export Retriever Cron Subscribers WooCommerce to API Newsman
 *
 * @class Newsman_Export_Retriever_SubscribersWoocommerce
 */
class Newsman_Export_Retriever_SubscribersWoocommerce extends Newsman_Export_Retriever_CronSubscribers {
	/**
	 * Fetch subscribers from WooCommerce users role subscriber
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @param null|int $start Start batch.
	 * @param null|int $limit Limit batch.
	 * @param bool     $cronlast Is last entities.
	 * @return array
	 */
	public function get_subscribers( $blog_id, $start, $limit, $cronlast ) {
		if ( true === $cronlast ) {
			$args    = array(
				'limit'  => -1,
				'status' => 'completed',
				'return' => 'ids',
			);
			$all_ids = wc_get_orders( $args );
			$count   = count( $all_ids );
			unset( $all_ids );

			$start = $count - $limit;
			if ( $start < 0 ) {
				$start = 0;
			}
		}

		$args = array(
			'status' => 'completed',
			'offset' => $start,
			'number' => $limit,
		);

		return wc_get_orders( $args );
	}

	/**
	 * Process WooCommerce subscriber
	 *
	 * @param WC_Order $subscriber Subscriber.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process_subscriber( $subscriber, $blog_id = null ) {
		$data = json_decode( wp_json_encode( $subscriber->data ), true );

		$row = array(
			'email'              => $data['billing']['email'],
			'firstname'          => ( ! empty( $data['billing']['first_name'] ) ) ? $data['billing']['first_name'] : '',
			'lastname'           => ( ! empty( $data['billing']['first_name'] ) ) ? $data['billing']['last_name'] : '',
			'tel'                => ( ! empty( $data['billing']['phone'] ) ) ?
				$this->clean_phone( $data['billing']['phone'] ) : '',
			'telephone'          => ( ! empty( $data['billing']['phone'] ) ) ?
				$this->clean_phone( $data['billing']['phone'] ) : '',
			'billing_telephone'  => ( ! empty( $data['billing']['phone'] ) ) ?
				$this->clean_phone( $data['billing']['phone'] ) : '',
			'shipping_telephone' => ( ! empty( $data['shipping']['phone'] ) ) ?
				$this->clean_phone( $data['shipping']['phone'] ) : '',
		);

		return $row;
	}

	/**
	 * Is valid subscriber
	 *
	 * @param mixed    $subscriber Subscriber.
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_valid_subscriber( $subscriber, $blog_id = null ) {
		$data = json_decode( wp_json_encode( $subscriber->data ), true );

		if ( empty( $data ) ) {
			return false;
		}

		if ( ! ( is_array( $data ) && isset( $data['billing'] ) ) ) {
			return false;
		}

		return true;
	}
}
