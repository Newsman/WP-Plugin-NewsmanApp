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

namespace Newsman\Service\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service Context Init Unsubscribe Email
 *
 * @class \Newsman\Service\Context\InitUnsubscribeEmail
 */
class InitUnsubscribeEmail extends UnsubscribeEmail {
	/**
	 * Options request parameter API
	 *
	 * @var array|null
	 */
	protected $options;

	/**
	 * Set options
	 *
	 * @param array $options Options.
	 * @return $this
	 */
	public function set_options( $options ) {
		$this->options = $options;
		return $this;
	}

	/**
	 * Get options
	 *
	 * @return array|string
	 */
	public function get_options() {
		return $this->options;
	}
}
