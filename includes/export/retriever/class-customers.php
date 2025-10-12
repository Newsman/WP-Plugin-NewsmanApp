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
}
