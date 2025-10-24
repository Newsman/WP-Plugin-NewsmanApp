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
 * Client Service Context Subscribe Email
 *
 * @class \Newsman\Service\Context\SubscribeEmail
 */
class SubscribeEmail extends UnsubscribeEmail {
	/**
	 * Subscriber firstname
	 *
	 * @var string
	 */
	protected $firstname;

	/**
	 * Subscriber lastname
	 *
	 * @var string
	 */
	protected $lastname;

	/**
	 * Properties
	 *
	 * @var array
	 */
	protected $properties = array();

	/**
	 * Set subscriber firstname
	 *
	 * @param string $firstname Subscriber firstname.
	 * @return $this
	 */
	public function set_firstname( $firstname ) {
		$this->firstname = $firstname;
		return $this;
	}

	/**
	 * Get subscriber firstname
	 *
	 * @return string
	 */
	public function get_firstsname() {
		if ( empty( $this->firstname ) ) {
			return self::NULL_VALUE;
		}
		return $this->firstname;
	}

	/**
	 * Set subscriber lastname
	 *
	 * @param string $lastname Subscriber lastname.
	 * @return $this
	 */
	public function set_lastname( $lastname ) {
		$this->lastname = $lastname;
		return $this;
	}

	/**
	 * Get subscriber lastname
	 *
	 * @return string
	 */
	public function get_lastsname() {
		if ( empty( $this->lastname ) ) {
			return self::NULL_VALUE;
		}
		return $this->lastname;
	}

	/**
	 * Set properties
	 *
	 * @param array $properties Properties.
	 * @return $this
	 */
	public function set_properties( $properties ) {
		$this->properties = $properties;
		return $this;
	}

	/**
	 * Get properties
	 *
	 * @return array
	 */
	public function get_properties() {
		return $this->properties;
	}
}
