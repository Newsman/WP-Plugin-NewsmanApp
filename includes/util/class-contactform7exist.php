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
 * Contact Form 7 exists.
 *
 * Detects whether the Contact Form 7 plugin is loaded by checking for its main class.
 *
 * @class \Newsman\Util\ContactForm7Exist
 */
class ContactForm7Exist {
	/**
	 * Whether Contact Form 7 is present.
	 *
	 * @return bool
	 */
	public function exist() {
		$exists = class_exists( '\WPCF7_ContactForm' );
		if ( function_exists( 'apply_filters' ) ) {
			$exists = apply_filters( 'newsman_contact_form_7_exist', $exists );
		}
		return $exists;
	}
}
