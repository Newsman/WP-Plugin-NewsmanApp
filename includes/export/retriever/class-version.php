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
 * Class Export Retriever Version
 *
 * @class \Newsman\Export\Retriever\Version
 */
class Version extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Process version retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process( $data = array(), $blog_id = null ) {
		return array( 'version' => 'Wordpress ' . get_bloginfo( 'version' ) );
	}
}
