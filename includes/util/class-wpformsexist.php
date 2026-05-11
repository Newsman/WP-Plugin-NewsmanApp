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

namespace Newsman\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPForms exists.
 *
 * Detects whether the WPForms (Lite or Pro) plugin is loaded by checking the
 * `WPFORMS_VERSION` constant defined in `wpforms.php` at boot.
 *
 * @class \Newsman\Util\WPFormsExist
 */
class WPFormsExist {
	/**
	 * Whether WPForms is present.
	 *
	 * @return bool
	 */
	public function exist() {
		$exists = defined( 'WPFORMS_VERSION' );
		if ( function_exists( 'apply_filters' ) ) {
			$exists = apply_filters( 'newsman_wpforms_exist', $exists );
		}
		return $exists;
	}
}
