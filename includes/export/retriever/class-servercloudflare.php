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
 * Class Export Retriever Server Cloudflare
 *
 * @class \Newsman\Export\Retriever\ServerCloudflare
 */
class ServerCloudflare extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Process server cloudflare retriever
	 *
	 * Returns true if the current request passed through Cloudflare's proxy
	 * network, detected via the CF-Ray header that Cloudflare attaches to
	 * every proxied request.
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process( $data = array(), $blog_id = null ) {
		$cf_ray     = isset( $_SERVER['HTTP_CF_RAY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_RAY'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$cloudflare = '' !== $cf_ray;

		return array( 'cloudflare' => $cloudflare );
	}
}
