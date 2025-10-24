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

namespace Newsman\Service\Context\Configuration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Context Configuration UpdateFeed
 *
 * @class \Newsman\Service\Context\Configuration\UpdateFeed
 */
class UpdateFeed extends EmailList {
	/**
	 * Feed ID
	 *
	 * @var string|int
	 */
	protected $feed_id;

	/**
	 * Properties
	 *
	 * @var array
	 */
	protected $properties = array();


	/**
	 * Set feed ID
	 *
	 * @param string $feed_id Feed ID.
	 * @return $this
	 */
	public function set_feed_id( $feed_id ) {
		$this->feed_id = $feed_id;
		return $this;
	}

	/**
	 * Get feed ID
	 *
	 * @return string
	 */
	public function get_feed_id() {
		return $this->feed_id;
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
