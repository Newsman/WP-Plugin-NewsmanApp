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

namespace Newsman\Service\Context\Sms;

use Newsman\Service\Context\Blog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Context SMS Unsubscribe from list
 *
 * @class \Newsman\Service\Context\Sms\Unsubscribe
 */
class Unsubscribe extends Blog {
	/**
	 * Telephone number
	 *
	 * @var string
	 */
	protected $telephone;

	/**
	 * Subscriber IP address
	 *
	 * @var string
	 */
	protected $ip;

	/**
	 * Set subscriber telephone number
	 *
	 * @param string $telephone Subscriber telephone number.
	 * @return $this
	 */
	public function set_telephone( $telephone ) {
		$this->telephone = $telephone;
		return $this;
	}

	/**
	 * Get subscriber telephone number
	 *
	 * @return string
	 */
	public function get_telephone() {
		return $this->telephone;
	}

	/**
	 * Set subscriber IP address
	 *
	 * @param string $ip Subscriber IP address.
	 * @return $this
	 */
	public function set_ip( $ip ) {
		$this->ip = $ip;
		return $this;
	}

	/**
	 * Get subscriber IP address
	 *
	 * @return string
	 */
	public function get_ip() {
		return $this->ip;
	}
}
