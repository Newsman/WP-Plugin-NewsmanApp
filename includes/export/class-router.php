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
use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Request
 *
 * @class \Newsman\Export\Router
 */
class Router {
	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Newsman logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config = Config::init();
		$this->logger = Logger::init();
	}

	/**
	 * Export data action.
	 * Used by newsman.app to fetch data from store.
	 *
	 * @return void
	 * @throws \Exception Throws standard exception on errors.
	 */
	public function execute() {
		$export_request = new \Newsman\Export\Request();

		// Check for inbound Newsman webhook events (highest priority).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['newsman_events'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			$events_raw = wp_unslash( $_POST['newsman_events'] );
			$events     = json_decode( $events_raw, true );
			$webhooks   = new \Newsman\Webhooks();
			$result     = $webhooks->process( is_array( $events ) ? $events : array(), get_current_blog_id() );
			$page       = new \Newsman\Page\Renderer();
			$page->display_json( $result );
		}

		// Check for API v1 JSON payload via explicit GET parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['newsman_api'] ) && 'v1' === sanitize_text_field( wp_unslash( $_GET['newsman_api'] ) ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_body = (string) file_get_contents( 'php://input' );
			$this->execute_v1( $raw_body, get_current_blog_id() );
			return;
		}

		if ( ! $export_request->is_export_request() ) {
			return;
		}

		if ( ! $this->config->is_enabled_with_api() ) {
			$result = array(
				'status'  => 403,
				'message' => 'API setting is not enabled in plugin',
			);
			$page   = new \Newsman\Page\Renderer();
			$page->display_json( $result );
		}

		try {
			$parameters = $export_request->get_request_parameters();
			$processor  = new \Newsman\Export\Retriever\Processor();
			$code       = $processor->get_code_by_data( $parameters );

			// Block legacy access for endpoints available in API v1.
			if ( false !== $code && in_array( $code, \Newsman\Export\V1\PayloadParser::get_method_map(), true ) ) {
				$page = new \Newsman\Page\Renderer();
				$page->display_json(
					array(
						'error' => 'This endpoint is only available via API v1 (JSON POST).',
					)
				);
				return;
			}

			$result = $processor->process(
				$code,
				get_current_blog_id(),
				$parameters
			);

			$page = new \Newsman\Page\Renderer();
			$page->display_json( $result );
		} catch ( \OutOfBoundsException $e ) {
			$this->logger->log_exception( $e );
			$result = array(
				'status'  => 403,
				'message' => $e->getMessage(),
			);

			$page = new \Newsman\Page\Renderer();
			$page->display_json( $result );
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// wp_die('Access Forbidden', 'Forbidden', array('response' => 403)); .
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			$result = array(
				'status'  => 0,
				'message' => $e->getMessage(),
			);

			$page = new \Newsman\Page\Renderer();
			$page->display_json( $result );
		}
	}

	/**
	 * Handle an API v1 JSON payload request.
	 *
	 * Parses the JSON body, authenticates via the Bearer token, dispatches to
	 * the appropriate retriever, and renders a JSON response. Errors are returned
	 * as {"error": {"code": <int>, "message": "<string>"}} with the matching
	 * HTTP status code.
	 *
	 * @param string   $raw_body Raw HTTP request body.
	 * @param null|int $blog_id  WordPress blog ID.
	 * @return void
	 * @throws \Newsman\Export\V1\ApiV1Exception When the payload is invalid or the plugin is not configured for the target blog.
	 */
	protected function execute_v1( $raw_body, $blog_id ) {
		$page = new \Newsman\Page\Renderer();

		// Extract Bearer token from the Authorization header.
		$api_key = '';
		$auth    = '';
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}
		if ( empty( $auth ) && function_exists( 'getallheaders' ) ) {
			foreach ( getallheaders() as $name => $value ) {
				if ( 'authorization' === strtolower( $name ) ) {
					$auth = sanitize_text_field( $value );
					break;
				}
			}
		}
		if ( ! empty( $auth ) ) {
			if ( stripos( $auth, 'Bearer' ) !== false ) {
				$api_key = trim( str_ireplace( 'Bearer', '', $auth ) );
			} else {
				$api_key = trim( $auth );
			}
		}

		try {
			$v1_parser = new \Newsman\Export\V1\PayloadParser();
			$parsed    = $v1_parser->parse( $raw_body );

			$code = $parsed['code'];
			$data = $parsed['data'];

			// Override blog_id from JSON params if provided.
			if ( ! empty( $data['blog_id'] ) ) {
				$blog_id = (int) $data['blog_id'];
			}

			$config = new \Newsman\Config();
			if ( ! $config->is_enabled_with_api( $blog_id ) ) {
				throw new \Newsman\Export\V1\ApiV1Exception( 1011, 'API not available', 403 );
			}

			// Inject API key so the Processor authenticator can validate it.
			if ( ! empty( $api_key ) ) {
				$data[ \Newsman\Export\Retriever\Authenticator::API_KEY_PARAM ] = $api_key;
			}

			$processor = new \Newsman\Export\Retriever\Processor();
			$result    = $processor->process( $code, $blog_id, $data );

			$page->display_json( $result );
		} catch ( \Newsman\Export\V1\ApiV1Exception $e ) {
			$this->logger->log_exception( $e );
			http_response_code( $e->get_http_status() );
			$page->display_json(
				array(
					'error' => array(
						'code'    => $e->get_error_code(),
						'message' => $e->getMessage(),
					),
				)
			);
		} catch ( \OutOfBoundsException $e ) {
			$this->logger->log_exception( $e );
			http_response_code( 403 );
			$page->display_json(
				array(
					'error' => array(
						'code'    => 1001,
						'message' => 'Authentication failed',
					),
				)
			);
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			http_response_code( 500 );
			$page->display_json(
				array(
					'error' => array(
						'code'    => 1009,
						'message' => 'Internal server error',
					),
				)
			);
		}
	}
}
