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
 * Client Service Context Configuration List
 *
 * @class Newsman_Service_Context_Configuration_List
 */
class Newsman_Service_Context_Configuration_List extends Newsman_Service_Context_Configuration_User {
	/**
	 * API list ID
	 *
	 * @var int
	 */
	protected $list_id;

	/**
	 * Set API list ID
	 *
	 * @param int $list_id API list ID.
	 * @return $this
	 */
	public function set_list_id( int $list_id ) {
		$this->list_id = $list_id;
		return $this;
	}

	/**
	 * Get list ID
	 *
	 * @return int
	 */
	public function get_list_id() {
		return $this->list_id;
	}
}
