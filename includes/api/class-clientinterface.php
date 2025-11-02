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

namespace Newsman\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class API Client interface
 *
 * @class \Newsman\Api\ClientInterface
 */
interface ClientInterface {
	/**
	 * Make API GET request
	 *
	 * @param ContextInterface $context API request context.
	 * @param array            $params GET parameters.
	 *
	 * @return array Response from API
	 */
	public function get( $context, $params = array() );

	/**
	 * Make API POST request
	 *
	 * @param ContextInterface $context API request context.
	 * @param array            $get_params GET parameters.
	 * @param array            $post_params POST parameters.
	 *
	 * @return array Response from API
	 */
	public function post( $context, $get_params = array(), $post_params = array() );

	/**
	 * Make API request
	 *
	 * @param ContextInterface $context API request context.
	 * @param string           $method GET or POST request type.
	 * @param array            $get_params GET parameters.
	 * @param array            $post_params POST parameters.
	 *
	 * @return array
	 */
	public function request( $context, $method, $get_params = array(), $post_params = array() );

	/**
	 * Get HTTP response status code
	 *
	 * @return int|string
	 */
	public function get_status();

	/**
	 * Get error code from API, HTTP Error Code or JSON error == 1
	 *
	 * @return int
	 */
	public function get_error_code();

	/**
	 * Get error message from API, HTTP error body message or JSON parse error
	 *
	 * @return string
	 */
	public function get_error_message();

	/**
	 * API error check
	 *
	 * @return bool
	 */
	public function has_error();
}
