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
 * Class Service Context Anonymise Email
 *
 * @class \Newsman\Service\Context\AnonymiseEmail
 */
class AnonymiseEmail extends Blog {
	/**
	 * Subscriber E-mail
	 *
	 * @var string
	 */
	protected $email;

	/**
	 * Set subscriber email
	 *
	 * @param string $email Subscriber email.
	 * @return $this
	 */
	public function set_email( $email ) {
		$this->email = $email;
		return $this;
	}

	/**
	 * Get subscriber email
	 *
	 * @return string
	 */
	public function get_email() {
		return $this->email;
	}
}
