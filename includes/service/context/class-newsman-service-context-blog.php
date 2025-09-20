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
 * Client Service Context Blog
 *
 * @class Newsman_Service_Context_Blog
 */
class Newsman_Service_Context_Blog extends Newsman_Service_Context_Abstract_Context {
	/**
	 * WP blog ID
	 *
	 * @var null|int
	 */
	protected $blog_id;

	/**
	 * API list ID
	 *
	 * @var null|int
	 */
	protected $list_id;

	/**
	 * API segment ID
	 *
	 * @var null|int
	 */
	protected $segment_id;

	/**
	 * Set WP blog ID
	 *
	 * @param int $blog_id WP blog ID.
	 * @return $this
	 */
	public function set_blog_id( $blog_id ) {
		$this->blog_id = $blog_id;
		return $this;
	}

	/**
	 * Get WP blog ID
	 *
	 * @return null|int
	 */
	public function get_blog_id() {
		return $this->blog_id;
	}


	/**
	 * Set API list ID
	 *
	 * @param int $list_id API list ID.
	 * @return $this
	 */
	public function set_list_id( $list_id ) {
		$this->list_id = $list_id;
		return $this;
	}

	/**
	 * Get API list ID
	 *
	 * @return int
	 */
	public function get_list_id() {
		return $this->list_id;
	}

	/**
	 * Set API segment ID
	 *
	 * @param string $segment_id Segment ID.
	 * @return $this
	 */
	public function set_segment_id( $segment_id ) {
		$this->segment_id = $segment_id;
		return $this;
	}

	/**
	 * Get API segment ID
	 *
	 * @return string
	 */
	public function get_segment_id() {
		return $this->segment_id;
	}
}
