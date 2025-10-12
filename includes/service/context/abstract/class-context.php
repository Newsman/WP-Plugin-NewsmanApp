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

namespace Newsman\Service\Context\Abstract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Context Abstract Context
 *
 * @class \Newsman\Service\Context\Abstract\Context
 */
class Context {
	/**
	 * Null value sent as request parameter to Newsman API
	 */
	public const NULL_VALUE = 'null';

	/**
	 * Get API request parameter value NULL
	 *
	 * @return string
	 */
	public function get_null_value() {
		return self::NULL_VALUE;
	}
}
