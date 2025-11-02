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

		$row = array(
			'email'      => $subscriber->data->user_email,
			'firstname'  => $subscriber->data->display_name,
			'lastname'   => '',
			'additional' => array(),
		);

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
