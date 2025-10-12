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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Newsman_PHP' ) ) {

	/**
	 * Verify that vendor dependencies are installed.
	 *
	 * @class WP_Newsman_PHP
	 */
	class WP_Newsman_PHP {
		/**
		 * Check if vendor has all dependencies installed and notify in admin.
		 *
		 * @return void
		 */
		public static function vendor_check_and_notify() {
			printf(
				'<div id="newsman-vendor-missing" class="notice notice-error"><p>Dependencies for Newsman need to be installed. Run <code>composer install --no-dev</code> from the <code>%s</code> directory.</p></div>',
				esc_html( dirname( __DIR__ ) )
			);
		}
	}
}
