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
 * Client Export Retriever Customers
 *
 * @class \Newsman\Export\Retriever\Customers
 */
class Customers extends Users {
	/**
	 * User role
	 */
	public const USER_ROLE = 'customer';

	/**
	 * Process customers retriever
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
	 * @param \WC_Customer $customer Customer instance.
	 * @param null|int     $role Role.
	 * @param null|int     $blog_id WP blog ID.
	 * @return array
	 */
	public function process_customer( $customer, $role, $blog_id = null ) {
		$row = parent::process_customer( $customer, $role, $blog_id );

		if ( $this->remarketing_config->is_send_telephone() && method_exists( $customer, 'get_billing_phone' ) ) {
			$row['phone'] = $customer->get_billing_phone();
		}

		foreach ( $this->remarketing_config->get_customer_attributes() as $attribute ) {
			if ( strpos( $attribute, 'billing_' ) === 0 || strpos( $attribute, 'shipping_' ) === 0 ) {
				$getter = 'get_' . $attribute;
				if ( method_exists( $customer, $getter ) ) {
					$row[ $attribute ] = $customer->$getter();
				}
			}
		}

		return apply_filters(
			'newsman_export_retriever_customers_process_customer',
			$row,
			array(
				'customer' => $customer,
				'role'     => $role,
				'blog_id'  => $blog_id,
			)
		);
	}
}
