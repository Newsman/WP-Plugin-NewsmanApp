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

use Newsman\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get user IP address
 *
 * @class \Newsman\User\IpAddress
 */
class IpAddress {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Config
	 *
	 * @var HostIpAddress
	 */
	protected $host_ip_address;

	/**
	 * Config
	 *
	 * @var RemoteIpAddress
	 */
	protected $remote_ip_address;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config            = Config::init();
		$this->host_ip_address   = HostIpAddress::init();
		$this->remote_ip_address = RemoteIpAddress::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self IpAddress
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new IpAddress();
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
