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
 * Class Export Retriever Subscribers WordPress Feed
 *
 * @class \Newsman\Export\Retriever\SubscribersWordpressFeed
 */
class SubscribersWordpressFeed extends Users {
	/**
	 * User role
	 */
	public const USER_ROLE = 'subscriber';

	/**
	 * Process subscribers retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws \Exception On errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		$data['wp_newsman_internal_role'] = self::USER_ROLE;
		return parent::process( $data, $blog_id );
	}

	/**
	 * Process customer
	 *
	 * @param \WP_User|\WC_Customer $customer Customer instance.
	 * @param null|int              $role Role.
	 * @param null|int              $blog_id WP blog ID.
	 * @return array
	 */
	public function process_customer( $customer, $role, $blog_id = null ) {
		$data = get_user_meta( $customer->data->ID );

		$row = array(
			'subscriber_id'   => $customer->data->ID,
			'firstname'       => $data['first_name'][0],
			'lastname'        => $data['last_name'][0],
			'email'           => $customer->data->user_email,
			'date_subscribed' => $customer->data->user_registered,
			'confirmed'       => 1,
			'source'          => 'WordPress users role ' . self::USER_ROLE,
		);

		$telephone = $this->get_telphone_from_user_data( $data );
		if ( ! empty( $telephone ) ) {
			$row['phone'] = $telephone;
		}

		$ip = '';
		$ip = apply_filters(
			'newsman_export_retriever_subscribers_wordpress_feed_get_user_ip',
			$ip,
			array(
				'data'    => $data,
				'blog_id' => $blog_id,
			)
		);
		if ( ! empty( $ip ) ) {
			$row['ip'] = $ip;
		} else {
			$ip = $this->get_user_ip_from_user_data( $data, $blog_id );
			if ( ! empty( $ip ) ) {
				$row['ip'] = $ip;
			}
		}

		return apply_filters(
			'newsman_export_retriever_subscribers_wordpress_feed_process_customer',
			$row,
			array(
				'customer' => $customer,
				'role'     => $role,
				'blog_id'  => $blog_id,
			)
		);
	}
}
