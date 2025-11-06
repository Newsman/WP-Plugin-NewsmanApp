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
 * Class Export Retriever Cron Subscribers WordPress to API Newsman
 *
 * @class \Newsman\Export\Retriever\SubscribersWordpress
 */
class SubscribersWordpress extends CronSubscribers {
	/**
	 * Fetch subscribers from WordPress users role subscriber
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @param null|int $start Start batch.
	 * @param null|int $limit Limit batch.
	 * @param bool     $cronlast Is last entities.
	 * @return array
	 */
	public function get_subscribers( $blog_id, $start, $limit, $cronlast ) {
		if ( true === $cronlast ) {
			$args  = array(
				'role'   => 'subscriber',
				'fields' => 'ID',
			);
			$count = count( get_users( $args ) );

			$start = $count - $limit;
			if ( $start < 0 ) {
				$start = 0;
			}
		}

		$args = array(
			'role'   => 'subscriber',
			'offset' => $start,
			'number' => $limit,
		);
		$args = apply_filters(
			'newsman_export_retriever_subscribers_wordpress_process_fetch',
			$args,
			array(
				'blog_id'  => $blog_id,
				'start'    => $start,
				'limit'    => $limit,
				'cronlast' => $cronlast,
			)
		);

		return get_users( $args );
	}

	/**
	 * Process WordPress subscriber
	 *
	 * @param \WP_User $subscriber Subscriber.
	 * @param null|int $blog_id WP blog ID.
	 * @return array|false
	 */
	public function process_subscriber( $subscriber, $blog_id = null ) {
		if ( isset( $this->emails_cache[ $subscriber->data->user_email ] ) ) {
			return false;
		}
		$this->emails_cache[ $subscriber->data->user_email ] = true;
		$data = get_user_meta( $subscriber->data->ID );

		$row = array(
			'email'     => $subscriber->data->user_email,
			'firstname' => $subscriber->data->display_name,
			'lastname'  => '',
		);

		if ( $this->remarketing_config->is_send_telephone() ) {
			$row = array_merge(
				$row,
				array(
					'tel'                => ( ! empty( $data['billing_phone'] ) && ! empty( $data['billing_phone'][0] ) ) ?
						$this->clean_phone( $data['billing_phone'][0] ) : '',
					'phone'              => ( ! empty( $data['billing_phone'] ) && ! empty( $data['billing_phone'][0] ) ) ?
						$this->clean_phone( $data['billing_phone'][0] ) : '',
					'telephone'          => ( ! empty( $data['billing_phone'] ) && ! empty( $data['billing_phone'][0] ) ) ?
						$this->clean_phone( $data['billing_phone'][0] ) : '',
					'billing_telephone'  => ( ! empty( $data['billing_phone'] ) && ! empty( $data['billing_phone'][0] ) ) ?
						$this->clean_phone( $data['billing_phone'][0] ) : '',
					'shipping_telephone' => ( ! empty( $data['shipping_phone'] ) && ! empty( $data['shipping_phone'][0] ) ) ?
						$this->clean_phone( $data['shipping_phone'][0] ) : '',
				)
			);
		}

		$row['additional'] = array();
		foreach ( $this->remarketing_config->get_customer_attributes() as $attribute ) {
			if ( ! empty( $data[ $attribute ] ) && ! empty( $data[ $attribute ][0] ) ) {
				$row['additional'][ $attribute ] = $data[ $attribute ][0];
			} else {
				$row['additional'][ $attribute ] = '';
			}
		}

		return apply_filters(
			'newsman_export_retriever_subscribers_wordpress_process_subscriber',
			$row,
			array(
				'subscriber' => $subscriber,
				'blog_id'    => $blog_id,
			)
		);
	}

	/**
	 * Get total count of subscribers
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int|null
	 */
	public function get_count_subscribers( $blog_id = null ) {
		$args = array(
			'role'   => 'subscriber',
			'fields' => 'ID',
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$count = count( get_users( $args ) );

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $count;
	}
}
