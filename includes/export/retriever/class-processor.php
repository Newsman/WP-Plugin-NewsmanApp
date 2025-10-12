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

namespace Newsman\Export\Retriever;

use Newsman\Config;
use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Export Retriever Processor
 *
 * @class \Newsman\Export\Retriever\Processor
 */
class Processor {
	/**
	 * Newsman Config
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
	 * Retriever pool
	 *
	 * @var Pool
	 */
	protected $pool;

	/**
	 * Retriever authenticator
	 *
	 * @var Authenticator
	 */
	protected $authenticator;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config        = Config::init();
		$this->logger        = Logger::init();
		$this->pool          = new Pool();
		$this->authenticator = new Authenticator();
	}

	/**
	 * Process retriever data
	 *
	 * @param string   $code Code of retriever.
	 * @param null|int $blog_id WP blog ID.
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @return array
	 * @throws \OutOfBoundsException Authentication error. Invalid credentials.
	 */
	public function process( $code, $blog_id = null, $data = array() ) {
		$this->logger->info(
			sprintf(
				/* translators: 1: Code, 2: WordPress blog ID */
				esc_html__( 'Processing fetch data (%1$s) for blog ID %2$d.', 'newsman' ),
				$code,
				$blog_id
			)
		);

		$tmp_data = $data;
		unset( $tmp_data[ Authenticator::API_KEY_PARAM ] );
		$this->logger->info( wp_json_encode( $tmp_data, true ) );
		unset( $tmp_data );

		try {
			$api_key = $this->get_api_key_from_data( $code, $data );
			$this->authenticator->authenticate( $api_key, $blog_id );
		} catch ( \OutOfBoundsException $e ) {
			$this->logger->log_exception( $e );
			throw $e;
		}

		$retriever = $this->pool->get_retriever_by_code( $code, $data );
		unset( $data[ Authenticator::API_KEY_PARAM ] );

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// if ( $retriever instanceof \Newsman\Export\Retriever\Config ) {
		// $retriever->set_request_apy_key( $api_key );
		// }.

		return $retriever->process( $data, $blog_id );
	}

	/**
	 * Get API key or authentication token key from request data
	 *
	 * @param string $code Retriever code.
	 * @param array  $data Request data.
	 * @return string
	 */
	protected function get_api_key_from_data( $code, $data ) {
		if ( ! empty( $data[ Authenticator::API_KEY_PARAM ] ) ) {
			return $data[ Authenticator::API_KEY_PARAM ];
		}
		return '';
	}

	/**
	 * Get retriever code from request data
	 *
	 * @param array $data Request data.
	 * @return false|string
	 */
	public function get_code_by_data( $data ) {
		if ( ! ( isset( $data['newsman'] ) && ! empty( $data['newsman'] ) ) ) {
			return false;
		}
		return str_replace( '.json', '', $data['newsman'] );
	}
}
