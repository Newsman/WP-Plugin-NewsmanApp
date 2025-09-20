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
 * Client API Context interface
 *
 * @class Newsman_Api_ContextInterface
 */
interface Newsman_Api_ContextInterface {
	/**
	 * Get API user ID
	 *
	 * @return string
	 */
	public function get_user_id();

	/**
	 * Get API segment ID
	 *
	 * @return string
	 */
	public function get_segment_id();

	/**
	 * Get API key
	 *
	 * @return string
	 */
	public function get_api_key();

	/**
	 * Set blog ID
	 *
	 * @param int|string $blog_id WP blog ID.
	 * @return Newsman_Api_ContextInterface
	 */
	public function set_blog_id( $blog_id );

	/**
	 * Get WP blog ID
	 *
	 * @return int|string
	 */
	public function get_blog_id();

	/**
	 * Set API user ID
	 *
	 * @param int|string $user_id API user ID.
	 * @return Newsman_Api_ContextInterface
	 */
	public function set_user_id( $user_id );

	/**
	 * Set API segment ID
	 *
	 * @param string $segment_id Segment ID.
	 * @return Newsman_Api_ContextInterface
	 */
	public function set_segment_id( $segment_id );

	/**
	 * Set API key
	 *
	 * @param string $api_key API key.
	 * @return Newsman_Api_ContextInterface
	 */
	public function set_api_key( $api_key );

	/**
	 * API REST HTTP endpoint
	 *
	 * @param string $endpoint API REST endpoint.
	 * @return Newsman_Api_ContextInterface
	 */
	public function set_endpoint( $endpoint );

	/**
	 * Get API REST endpoint
	 *
	 * @return string
	 */
	public function get_endpoint();

	/**
	 * Set API list ID
	 *
	 * @param int $list_id API list ID.
	 * @return Newsman_Api_ContextInterface
	 */
	public function set_list_id( $list_id );

	/**
	 * Get API list ID
	 *
	 * @return int
	 */
	public function get_list_id();
}
