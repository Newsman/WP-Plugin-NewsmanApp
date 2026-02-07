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
use Newsman\Export\Retriever\Pool;

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
	 * Retriever pool
	 *
	 * @var Pool
	 */
	protected $pool;

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
		$this->pool   = new Pool();
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

		$dynamic_params = $this->get_dynamic_parameters();

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

		$known_get = array_merge( $this->known_get_parameters, $dynamic_params );
		foreach ( $known_get as $parameter ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			if ( isset( $_GET[ $parameter ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$parameters[ $parameter ] = $this->sanitize_parameter( $_GET[ $parameter ] );
			}
		}

		$known_post = array_merge( $this->known_post_parameters, $dynamic_params );
		foreach ( $known_post as $parameter ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $parameter ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$parameters[ $parameter ] = $this->sanitize_parameter( $_POST[ $parameter ] );
			}
		}

		return apply_filters( 'newsman_export_request_get_all_known_parameters', $parameters );
	}

	/**
	 * Sanitize parameter
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	protected function sanitize_parameter( $value ) {
		$value = wp_unslash( $value );
		if ( is_array( $value ) ) {
			return map_deep( $value, 'sanitize_text_field' );
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Get dynamic parameters from retrievers
	 *
	 * @return array
	 */
	protected function get_dynamic_parameters() {
		$dynamic_params = array( 'sort', 'order' );
		$retrievers     = $this->pool->get_retrievers_with_filters();

		foreach ( $retrievers as $retriever_def ) {
			try {
				$instance = $this->pool->get_retriever_by_code( $retriever_def['code'], array() );
				if ( method_exists( $instance, 'get_where_parameters_mapping' ) ) {
					$dynamic_params = array_merge( $dynamic_params, array_keys( $instance->get_where_parameters_mapping() ) );
				}
				if ( method_exists( $instance, 'get_allowed_sort_fields' ) ) {
					$dynamic_params = array_merge( $dynamic_params, array_keys( $instance->get_allowed_sort_fields() ) );
				}
			} catch ( \Exception $e ) {
				continue;
			}
		}

		return array_unique( $dynamic_params );
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

		return $auth;
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
