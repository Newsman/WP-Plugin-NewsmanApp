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

namespace Newsman\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Server IP Resolver
 *
 * Resolves the server's public IP address by querying well-known free
 * IP-lookup services in random order and falling back to $_SERVER['SERVER_ADDR'].
 *
 * @class \Newsman\Util\ServerIpResolver
 */
class ServerIpResolver {
	/**
	 * Free IP-lookup service URLs.
	 * Each service returns the public IP as plain text.
	 *
	 * @var array
	 */
	protected $services = array(
		'https://api.ipify.org',
		'https://ipinfo.io/ip',
		'https://ifconfig.me/ip',
		'https://icanhazip.com',
	);

	/**
	 * Resolve the server's public IP address.
	 *
	 * Tries the lookup services in random order and returns the first valid
	 * IP address found. Falls back to $_SERVER['SERVER_ADDR'] if all services
	 * are unreachable.
	 *
	 * @return string
	 */
	public function resolve() {
		$services = $this->services;
		shuffle( $services );

		foreach ( $services as $url ) {
			$ip = $this->fetch_from_service( $url );
			if ( $this->is_valid_ip( $ip ) ) {
				return $ip;
			}
		}

		return isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Fetch the IP from a single lookup service.
	 *
	 * @param string $url Lookup service URL.
	 * @return string Trimmed response body, or empty string on failure.
	 */
	private function fetch_from_service( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 5,
				'user-agent' => 'NewsmanApp/' . ( defined( 'NEWSMAN_VERSION' ) ? NEWSMAN_VERSION : '' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		return trim( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Check whether a string is a valid IP address.
	 *
	 * @param string $ip Value to check.
	 * @return bool
	 */
	private function is_valid_ip( $ip ) {
		return ! empty( $ip ) && false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}
}
