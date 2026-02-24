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
 * Class Export Retriever SQL Name
 *
 * @class \Newsman\Export\Retriever\SqlName
 */
class SqlName extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Process SQL name retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process( $data = array(), $blog_id = null ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$full = (string) $wpdb->get_var( 'SELECT VERSION()' );
		$name = ( false !== stripos( $full, 'mariadb' ) ) ? 'MariaDB' : 'MySQL';

		return array( 'name' => $name );
	}
}
