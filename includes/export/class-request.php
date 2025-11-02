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

namespace Newsman\Export;

use Newsman\Config;
use Newsman\Export\Retriever\Authenticator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Request
 *
 * @class \Newsman\Export\Request
 */
class Request {
	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Known HTTP GET parameters intercepted
	 *
	 * @var array
	 */
	protected $known_get_parameters = array(
		// Retriever code.
		'newsman',
		// Lists export in general.
		'start',
		'limit',
		// Orders export.
		'order_id',
		// Products export.
		'product_id',
		// Method whether WordPress or WooCommerce.
		'method',
		// Cron last.
		'cronlast',
		// Coupons export.
		'type',
		'value',
		'batch_size',
		'prefix',
		'expire_date',
		'min_amount',
		'currency',
	);

	/**
	 * Known HTTP POST parameters intercepted
	 *
	 * @var array
	 */
	protected $known_post_parameters = array(
		// Retriever code.
		'newsman',
		// Lists export in general.
		'start',
		'limit',
		// Orders export.
		'order_id',
		// Products export.
		'product_id',
		// Method whether WordPress or WooCommerce.
		'method',
		// Cron last.
		'cronlast',
		// Coupons export.
		'type',
		'value',
		'batch_size',
		'prefix',
		'expire_date',
		'min_amount',
		'currency',
	);

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Is called Newsman export request
	 *
	 * @return bool
	 */
	public function is_export_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$newsman = ( empty( $_GET['newsman'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['newsman'] ) );
		if ( empty( $newsman ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$newsman = empty( $_POST['newsman'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['newsman'] ) );
		}

		return ! empty( $newsman );
	}

	/**
	 * Get request GET, POST and API key parameters
	 *
	 * @return array
	 */
	public function get_request_parameters() {
		$parameters = $this->get_all_known_parameters();

		$api_key = $this->get_api_key_from_header();
		if ( ! empty( $api_key ) && empty( $parameters[ Authenticator::API_KEY_PARAM ] ) ) {
			$parameters[ Authenticator::API_KEY_PARAM ] = $api_key;
		}

		return $parameters;
	}

	/**
	 * Get all known parameters
	 *
	 * @return array
	 */
	protected function get_all_known_parameters() {
		$parameters = array();

		$hash_key = Authenticator::API_KEY_PARAM;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_GET[ $hash_key ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$parameters[ $hash_key ] = sanitize_text_field( wp_unslash( $_GET[ $hash_key ] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		} elseif ( ! empty( $_POST[ $hash_key ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$parameters[ $hash_key ] = sanitize_text_field( wp_unslash( $_POST[ $hash_key ] ) );
		}

		foreach ( $this->known_get_parameters as $parameter ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			if ( isset( $_GET[ $parameter ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
				$parameters[ $parameter ] = sanitize_text_field( wp_unslash( $_GET[ $parameter ] ) );
			}
		}

		foreach ( $this->known_post_parameters as $parameter ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $parameter ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
				$parameters[ $parameter ] = sanitize_text_field( wp_unslash( $_POST[ $parameter ] ) );
			}
		}

		return apply_filters( 'newsman_export_request_get_all_known_parameters', $parameters );
	}

	/**
	 * Get API key from request HTTP headers
	 *
	 * @return string
	 */
	protected function get_api_key_from_header() {
		$auth = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$auth = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		if ( empty( $auth ) ) {
			$auth = $this->get_header_value( 'authorization' );
			if ( empty( $auth ) ) {
				$name = $this->config->get_export_authorize_header_name();
				if ( ! empty( $name ) ) {
					$auth = trim( (string) $this->get_header_value( $name ) );
					if ( ! empty( $auth ) ) {
						return $auth;
					}
				}
				return '';
			}
		}

		if ( stripos( $auth, 'Bearer' ) !== false ) {
			return trim( str_ireplace( 'Bearer', '', $auth ) );
		}
		return '';
	}

	/**
	 * Get HTTP header by name
	 *
	 * @param string $name Header name.
	 * @return false|string
	 */
	protected function get_header_value( $name ) {
		$name = strtolower( $name );
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			foreach ( $headers as $a_name => $value ) {
				if ( strtolower( $a_name ) === $name ) {
					return $value;
				}
			}
		}
		return false;
	}
}
