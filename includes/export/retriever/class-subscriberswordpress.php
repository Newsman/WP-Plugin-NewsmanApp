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
 * Client Export Retriever Cron Subscribers WordPress to API Newsman
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
			$args    = array(
				'role'   => 'subscriber',
				'fields' => 'ID',
			);
			$all_ids = get_users( $args );
			$count   = count( $all_ids );
			unset( $all_ids );

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

		return get_users( $args );
	}

	/**
	 * Process WordPress subscriber
	 *
	 * @param \WP_User $subscriber Subscriber.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process_subscriber( $subscriber, $blog_id = null ) {
		$row = array(
			'email'              => $subscriber->data->user_email,
			'firstname'          => $subscriber->data->display_name,
			'lastname'           => '',
			'tel'                => '',
			'phone'              => '',
			'telephone'          => '',
			'billing_telephone'  => '',
			'shipping_telephone' => '',
		);

		return $row;
	}
}
