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

use Newsman\Service\Context\Blog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Context Configuration User
 *
 * @class \Newsman\Service\Context\Configuration\User
 */
class User extends Blog {
	/**
	 * API user ID
	 *
	 * @var string|int
	 */
	protected $user_id;

	/**
	 * API key
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Set API user ID
	 *
	 * @param string|int $user_id API user ID.
	 * @return $this
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = $user_id;
		return $this;
	}

	/**
	 * Get API user ID
	 *
	 * @return int|string
	 */
	public function get_user_id() {
		return $this->user_id;
	}

	/**
	 * Set API key
	 *
	 * @param string $api_key API key.
	 * @return $this
	 */
	public function set_api_key( string $api_key ) {
		$this->api_key = $api_key;
		return $this;
	}

	/**
	 * Get API key
	 *
	 * @return string
	 */
	public function get_api_hey() {
		return $this->api_key;
	}
}
