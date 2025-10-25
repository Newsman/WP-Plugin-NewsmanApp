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

use Newsman\Config;
use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client API Client
 *
 * @class \Newsman\Api\Client
 */
class Client implements ClientInterface {
	/**
	 * Newsman Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Newsman Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * HTTP status code
	 *
	 * @var int|string|null
	 */
	protected $status;

	/**
	 * API error code
	 *
	 * @var string
	 */
	protected $error_code;

	/**
	 * API error message
	 *
	 * @var string
	 */
	protected $error_message;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config = Config::init();
		$this->logger = Logger::init();
	}

	/**
	 * Make API GET request
	 *
	 * @param ContextInterface $context API request context.
	 * @param array            $params GET parameters.
	 *
	 * @return array Response from API
	 */
	public function get( $context, $params = array() ) {
		return $this->request( $context, 'GET', $params );
	}

	/**
	 * Make API POST request
	 *
	 * @param ContextInterface $context API request context.
	 * @param array            $get_params GET parameters.
	 * @param array            $post_params POST parameters.
	 *
	 * @return array Response from API
	 */
	public function post( $context, $get_params = array(), $post_params = array() ) {
		return $this->request( $context, 'POST', $get_params, $post_params );
	}

	/**
	 * Make API request
	 *
	 * @param ContextInterface $context API request context.
	 * @param string           $method GET or POST request type.
	 * @param array            $get_params GET parameters.
	 * @param array            $post_params POST parameters.
	 *
	 * @return array|string|mixed
	 *
	 * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
	 */
	public function request( $context, $method, $get_params = array(), $post_params = array() ) {
		$this->status        = null;
		$this->error_message = null;
		$this->error_code    = 0;
		$result              = array();
		$args                = array();

		$url  = $this->config->get_api_url();
		$url .= sprintf(
			'%s/rest/%s/%s/%s.json',
			$this->config->get_api_version(),
			$context->get_user_id(),
			$context->get_api_key(),
			$context->get_endpoint()
		);

		$log_url = $url;
		if ( is_array( $get_params ) && ! empty( $get_params ) ) {
			$url .= '?' . http_build_query( $get_params );
			if ( isset( $get_params['auth_header_name'] ) ) {
				$get_params['auth_header_name']  = '****';
				$get_params['auth_header_value'] = '****';
			}
			$log_url .= '?' . http_build_query( $get_params );
		}
		$log_hash = uniqid();
		$this->logger->debug( '[' . $log_hash . '] ' . str_replace( $context->get_api_key(), '****', $log_url ) );

		$args['timeout'] = $this->config->get_api_timeout( $context->get_blog_id() );
		$args['headers'] = array(
			'Content-Type' => 'application/json',
		);

		try {
			$start_time = microtime( true );
			if ( 'POST' === $method ) {
				$args['body']  = $post_params;
				$remote_result = wp_remote_post( $url, $args );

				$this->logger->debug( wp_json_encode( $post_params ) );
			} else {
				$remote_result = wp_remote_get( $url, $args );
			}
			$elapsed_ms = round( ( microtime( true ) - $start_time ) * 1000 );
			$this->logger->debug(
				sprintf(
					'[%s] Requested in %s',
					$log_hash,
					$this->format_time_duration( $elapsed_ms )
				)
			);

			if ( $remote_result instanceof \WP_Error ) {
				throw new \Exception( $remote_result->get_error_message(), (int) $remote_result->get_error_code() );
			}

			$this->status = (int) $remote_result['response']['code'];
			if ( 200 === $this->status ) {
				try {
					$result    = json_decode( $remote_result['body'], true );
					$api_error = $this->parse_api_error( $result );
					if ( false !== $api_error ) {
						$this->error_code    = (int) $api_error['code'];
						$this->error_message = $api_error['message'];
						$this->logger->warning( $this->error_code . ' | ' . $this->error_message );
					} else {
						$this->logger->notice( wp_json_encode( $result ) );
					}
				} catch ( \Exception $e ) {
					$this->error_code    = 1;
					$this->error_message = $e->getMessage();
					$this->logger->log_exception( $e );
					return array();
				}
			} else {
				$this->error_code = (int) $this->status;
				try {
					if ( stripos( $remote_result['body'], '{' ) !== false ) {
						$body      = json_decode( $remote_result['body'], true );
						$api_error = $this->parse_api_error( $body );
						if ( false !== $api_error ) {
							$this->error_code    = (int) $api_error['code'];
							$this->error_message = $api_error['message'];
						} else {
							$this->error_message = 'Error: ' . $this->error_code;
						}
					}
				} catch ( \Exception $e ) {
					$this->error_message = 'Error: ' . $this->error_code;
				}
				$this->logger->error( $this->status . ' | ' . $remote_result['body'] );
			}
		} catch ( \Exception $e ) {
			$this->error_code    = (int) $e->getCode();
			$this->error_message = $e->getMessage();
			$this->logger->log_exception( $e );
		}

		return $result;
	}

	/**
	 * Parse API returned error
	 *
	 * @param array $result API result from response.
	 * @return array|false
	 */
	protected function parse_api_error( $result ) {
		if ( ! ( is_array( $result ) && isset( $result['err'] ) ) ) {
			return false;
		}

		return array(
			'code'    => isset( $result['code'] ) ? $result['code'] : 0,
			'message' => $result['message'] ?? '',
		);
	}

	/**
	 * Get HTTP response status code
	 *
	 * @return int|string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Get error code from API, HTTP Error Code or JSON error == 1
	 *
	 * @return int
	 */
	public function get_error_code() {
		return $this->error_code;
	}

	/**
	 * Get error message from API, HTTP error body message or JSON parse error
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->error_message;
	}

	/**
	 * API error check
	 *
	 * @return bool
	 */
	public function has_error() {
		return $this->error_code > 0;
	}

	/**
	 * Format time duration based on thresholds
	 *
	 * @param int $milliseconds The number of milliseconds to format.
	 * @return string Formatted time.
	 */
	public function format_time_duration( $milliseconds ) {
		if ( $milliseconds < 1000 ) {
			return sprintf( '%d ms', $milliseconds );
		}

		$total_seconds = $milliseconds / 1000;

		if ( $total_seconds < 60 ) {
			return sprintf( '%.1f s', $total_seconds );
		}

		$minutes           = floor( $total_seconds / 60 );
		$seconds_remainder = $total_seconds % 60;

		return sprintf( '%d min %.3f s', $minutes, $seconds_remainder );
	}
}
