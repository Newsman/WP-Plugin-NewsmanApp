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

namespace Newsman\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email address validator
 *
 * @class \Newsman\Validator\Email
 */
class Email {
	/**
	 * Validate an E-mail address
	 *
	 * @param string $email Email address.
	 * @return false
	 */
	public function is_valid( $email ) {
		$result = ( false !== is_email( $email ) );
		return apply_filters( 'newsman_validator_email_is_valid', $result, $email );
	}

	/**
	 * Get class instance
	 *
	 * @return self Email
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Email();
		}

		return $instance;
	}
}
