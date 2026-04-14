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
 * Class Export Retriever Platform Has Products
 *
 * @class \Newsman\Export\Retriever\PlatformHasProducts
 */
class PlatformHasProducts extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Process platform has products retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process( $data = array(), $blog_id = null ) {
		$has_products = ( new \Newsman\Util\WooCommerceExist() )->exist() ? 1 : 0;
		return array( 'has_products' => $has_products );
	}
}
