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
 * Client Service Context Configuration User
 *
 * @class Newsman_Service_Context_Configuration_User
 */
class Newsman_Service_Context_Configuration_SetFeedOnList extends Newsman_Service_Context_Configuration_List {
	/**
	 * The URL of the feed. For the type "fixed", send the name of the feed
	 *
	 * @var string|int
	 */
	protected $url;

	/**
	 * The website for which the feed is being set
	 *
	 * @var string
	 */
	protected $website;

	/**
	 * Type
	 *
	 * @var string
	 */
	protected $type = 'fixed';

	/**
	 * (Optional) If is true an Array containing the key feed_id (the id of the feed) will be returned
	 *
	 * @var int|bool|string
	 */
	protected $return_id = false;

	/**
	 * Set URL of the feed
	 *
	 * @param string|int $url Feed URL.
	 * @return $this
	 */
	public function set_url( $url ) {
		$this->url = $url;
		return $this;
	}

	/**
	 * Get URL of the feed
	 *
	 * @return int|string
	 */
	public function get_url() {
		return $this->url;
	}

	/**
	 * Set website for which the feed is being set
	 *
	 * @param string $website Website / WP blog URL.
	 * @return $this
	 */
	public function set_website( string $website ) {
		$this->website = $website;
		return $this;
	}

	/**
	 * Get website for which the feed is being set
	 *
	 * @return string
	 */
	public function get_website() {
		return $this->website;
	}

	/**
	 * Set type of feed
	 *
	 * @param string $type Feed type.
	 * @return $this
	 */
	public function set_type( string $type ) {
		$this->type = $type;
		return $this;
	}

	/**
	 * Get type of the feed
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Set return ID of the feed
	 *
	 * @param string $return_id Is return ID of the feed.
	 * @return $this
	 */
	public function set_return_id( string $return_id ) {
		$this->return_id = $return_id;
		return $this;
	}

	/**
	 * Get return ID of the feed
	 *
	 * @return string
	 */
	public function get_return_id() {
		return $this->return_id;
	}
}
