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

namespace Newsman\User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get user remote IP address
 *
 * @class \Newsman\User\RemoteIpAddress
 */
class RemoteIpAddress {
	/**
	 * Get class instance
	 *
	 * @return self RemoteIpAddress
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new RemoteIpAddress();
		}

		return $instance;
	}

	/**
	 * Get the remote ip address.
	 *
	 * @return string The ip address.
	 */
	public function get_ip() {
		$cl      = isset( $_SERVER['HTTP_CLIENT_IP'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) ) : '';
		$forward = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		$remote  = isset( $_SERVER['REMOTE_ADDR'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( filter_var( $cl, FILTER_VALIDATE_IP ) ) {
			$ip = $cl;
		} elseif ( filter_var( $forward, FILTER_VALIDATE_IP ) ) {
			$ip = $forward;
		} else {
			$ip = $remote;
		}
		return $ip;
	}
}
