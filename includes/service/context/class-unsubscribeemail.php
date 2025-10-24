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
 * Client Service Context Unsubscribe Email
 *
 * @class \Newsman\Service\Context\UnsubscribeEmail
 */
class UnsubscribeEmail extends Blog {
	/**
	 * Subscriber E-mail
	 *
	 * @var string
	 */
	protected $email;

	/**
	 * Subscriber IP address
	 *
	 * @var string
	 */
	protected $ip;

	/**
	 * Set subscriber E-mail
	 *
	 * @param string $email Subscriber E-mail.
	 * @return $this
	 */
	public function set_email( $email ) {
		$this->email = $email;
		return $this;
	}

	/**
	 * Get subscriber E-mail
	 *
	 * @return string
	 */
	public function get_email() {
		return $this->email;
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
