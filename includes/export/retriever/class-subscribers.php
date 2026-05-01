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

use Newsman\Export\V1\ApiV1Exception;

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
	 * @throws ApiV1Exception When neither subscriber source is enabled in API v1 context.
	 * @throws \Exception When neither subscriber source is enabled in legacy context.
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

		if ( isset( $data['_v1_filter_fields'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ApiV1Exception( 3002, 'Subscriber export not enabled', 500 );
		}

		throw new \Exception( 'No subscriber export enabled.' );
	}
}
