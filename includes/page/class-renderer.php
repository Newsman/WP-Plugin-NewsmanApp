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

namespace Newsman\Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page renderer
 *
 * @class \Newsman\Page\Renderer
 */
class Renderer {
	/**
	 * Encode array or object and set headers.
	 *
	 * @param array|Object $data Array or object to encode.
	 * @param int          $flags JSON encode flags.
	 * @return void
	 */
	public function display_json( $data, $flags = 0 ) {
		// Prevent WordPress from loading the theme.
		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', false );
		}

		header( 'Content-Type: application/json' );

		// Disable caching.
		header( 'Pragma: no-cache' );
		nocache_headers();
		// Old IE headers to prevent caching. Remove in the future.
		header( 'Cache-Control: post-check=0, pre-check=0', false );

		echo wp_json_encode( $data, $flags );
		exit( 0 );
	}
}
