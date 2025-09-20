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
 * Client Export Retriever Products
 *
 * @class Newsman_Export_Retriever_Products
 */
class Newsman_Export_Retriever_Products implements Newsman_Export_Retriever_Interface {
	/**
	 * Process products retriever
	 *
	 * @param array $data Data to filter entities, to save entities, other.
	 * @param array $blog_ids WP blog IDs.
	 * @return array
	 */
	public function process( $data = array(), $blog_ids = array() ) {
		return array();
	}
}
