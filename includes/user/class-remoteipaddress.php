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
		$real     = isset( $_SERVER['HTTP_X_REAL_IP'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) : '';
		$cl       = isset( $_SERVER['HTTP_CLIENT_IP'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) ) : '';
		$forward  = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		$forward2 = isset( $_SERVER['HTTP_X_FORWARDED'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED'] ) ) : '';
		$forward3 = isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED_FOR'] ) ) : '';
		$forward4 = isset( $_SERVER['HTTP_FORWARDED'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED'] ) ) : '';
		$remote   = isset( $_SERVER['REMOTE_ADDR'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( filter_var( $real, FILTER_VALIDATE_IP ) ) {
			$ip = $real;
		} elseif ( filter_var( $cl, FILTER_VALIDATE_IP ) ) {
			$ip = $cl;
		} elseif ( filter_var( $forward, FILTER_VALIDATE_IP ) ) {
			$ip = $forward;
		} elseif ( filter_var( $forward2, FILTER_VALIDATE_IP ) ) {
			$ip = $forward2;
		} elseif ( filter_var( $forward3, FILTER_VALIDATE_IP ) ) {
			$ip = $forward3;
		} elseif ( filter_var( $forward4, FILTER_VALIDATE_IP ) ) {
			$ip = $forward4;
		} else {
			$ip = $remote;
		}

		return apply_filters( 'newsman_remote_ip_address_get_ip', $ip );
	}
}
