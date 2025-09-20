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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Context Init Unsubscribe Email
 *
 * @class Newsman_Service_Context_InitUnsubscribeEmail
 */
class Newsman_Service_Context_InitUnsubscribeEmail extends Newsman_Service_Context_UnsubscribeEmail {
	/**
	 * Options request parameter API
	 *
	 * @var array|null
	 */
	protected $options;

	/**
	 * Set options
	 *
	 * @param string $options Options.
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
