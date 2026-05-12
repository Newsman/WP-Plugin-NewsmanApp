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
 * Gravity Forms exists.
 *
 * Detects whether the Gravity Forms plugin is loaded by checking the
 * `GF_MIN_WP_VERSION` constant Gravity Forms defines in its main file at boot.
 * `class_exists('GFForms')` would also work but requires the class to be
 * autoloaded — using the constant matches the timing pattern of `WPForms_Exist`
 * and avoids triggering Gravity's own bootstrap before our integration runs.
 *
 * @class \Newsman\Util\GravityFormsExist
 */
class GravityFormsExist {
	/**
	 * Whether Gravity Forms is present.
	 *
	 * @return bool
	 */
	public function exist() {
		$exists = defined( 'GF_MIN_WP_VERSION' );
		if ( function_exists( 'apply_filters' ) ) {
			$exists = apply_filters( 'newsman_gravity_forms_exist', $exists );
		}
		return $exists;
	}
}
