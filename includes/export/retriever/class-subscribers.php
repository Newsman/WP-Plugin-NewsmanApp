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
 * Class Export Retriever Subscribers
 *
 * @class \Newsman\Export\Retriever\Subscribers
 */
class Subscribers extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Process subscribers retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws \Exception On errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		if ( $this->remarketing_config->is_export_woocommerce_subscribers( $blog_id ) ) {
			$retriever = new SubscribersWoocommerceFeed();
			return $retriever->process( $data, $blog_id );
		}

		if ( $this->remarketing_config->is_export_wordpress_subscribers( $blog_id ) ) {
			$retriever = new SubscribersWordpressFeed();
			return $retriever->process( $data, $blog_id );
		}

		throw new \Exception( 'No subscriber export enabled.' );
	}
}
