<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman PHP checks class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Newsman\NewsmanPhp' ) ) {

	/**
	 * Notify that vendor dependencies are not installed.
	 *
	 * @class \Newsman\NewsmanPhp
	 */
	class NewsmanPhp {
		/**
		 * Notify in admin about missing composer.
		 *
		 * @return void
		 */
		public static function notify_missing_vendor_composer() {
			printf(
				'<div id="newsman-vendor-missing" class="notice notice-error"><p>Dependencies for NewsmanApp WordPress plugin need to be installed. Run <code>composer install --no-dev</code> from the <code>%s</code> directory.</p></div>',
				esc_html( dirname( __DIR__ ) )
			);
		}
	}
}
