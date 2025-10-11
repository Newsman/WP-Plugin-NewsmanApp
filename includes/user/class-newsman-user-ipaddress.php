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
 * Get user IP address
 *
 * @class Newsman_User_IpAddress
 */
class Newsman_User_IpAddress {
	/**
	 * Config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * Config
	 *
	 * @var Newsman_User_HostIpAddress
	 */
	protected $host_ip_address;

	/**
	 * Config
	 *
	 * @var Newsman_User_RemoteIpAddress
	 */
	protected $remote_ip_address;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config            = Newsman_Config::init();
		$this->host_ip_address   = Newsman_User_HostIpAddress::init();
		$this->remote_ip_address = Newsman_User_RemoteIpAddress::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self Newsman_User_IpAddress
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Newsman_User_IpAddress();
		}

		return $instance;
	}

	/**
	 * Get the subscriber ip address. (Necessary for Newsman subscription).
	 *
	 * @return string The ip address.
	 */
	public function get_ip() {
		if ( $this->config->get_developer_user_ip() ) {
			return $this->config->get_developer_user_ip();
		}

		if ( ! $this->config->is_send_user_ip() ) {
			return $this->host_ip_address->get_ip();
		}

		$ip = $this->remote_ip_address->get_ip();

		if ( '127.0.0.1' === $ip || empty( $ip ) ) {
			return $this->host_ip_address->get_ip();
		}

		return $ip;
	}
}
