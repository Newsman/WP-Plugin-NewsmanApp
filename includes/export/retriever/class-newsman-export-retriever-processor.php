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
 * Client Export Retriever Processor
 *
 * @class Newsman_Export_Retriever_Processor
 */
class Newsman_Export_Retriever_Processor {
	/**
	 * Retriever pool
	 *
	 * @var Newsman_Export_Retriever_Pool
	 */
	protected $pool;

	/**
	 * Retriever authenticator
	 *
	 * @var Newsman_Export_Retriever_Authenticator
	 */
	protected $authenticator;

	/**
	 * Newsman Config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * Newsman logger
	 *
	 * @var Newsman_WC_Logger
	 */
	protected $logger;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->pool          = new Newsman_Export_Retriever_Pool();
		$this->authenticator = new Newsman_Export_Retriever_Authenticator();
		$this->config        = Newsman_Config::init();
		$this->logger        = Newsman_WC_Logger::init();
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
			/* translators: 1: WordPress blog ID */
			esc_html( sprintf( __( 'Processing fetch data for blog ID %d.', 'newsman' ), $blog_id ) )
		);

		$tmp_data = $data;
		unset( $tmp_data[ Newsman_Export_Retriever_Authenticator::API_KEY_PARAM ] );
		$this->logger->info( wp_json_encode( $tmp_data, true ) );
		unset( $tmp_data );

		try {
			$api_key = $this->get_api_key_from_data( $code, $data );
			$this->authenticator->authenticate( $api_key, $blog_id );
		} catch ( \OutOfBoundsException $e ) {
			$this->logger->critical( $e->getCode() . ' ' . $e->getMessage() . $e->getTraceAsString() );
			throw $e;
		}

		// Get list ID by specified blog ID (usually the current blog ID).
		$list_id  = $this->config->get_list_id( $blog_id );
		$blog_ids = $this->config->get_blog_ids_by_list_id( $list_id );
		if ( empty( $blog_ids ) ) {
			$this->logger->notice( esc_html__( 'No blog IDs found for retriever', 'newsman' ) );
			return array();
		}

		$retriever = $this->pool->get_retriever_by_code( $code );
		unset( $data[ Newsman_Export_Retriever_Authenticator::API_KEY_PARAM ] );

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// if ( $retriever instanceof Newsman_Export_Retriever_Config ) {
		// $retriever->set_request_apy_key( $api_key );
		// }.

		return $retriever->process( $data, $blog_ids );
	}

	/**
	 * Get API key or authentication token key from request data
	 *
	 * @param string $code Retriever code.
	 * @param array  $data Request data.
	 * @return string
	 */
	protected function get_api_key_from_data( $code, $data ) {
		if ( ! empty( $data[ Newsman_Export_Retriever_Authenticator::API_KEY_PARAM ] ) ) {
			return $data[ Newsman_Export_Retriever_Authenticator::API_KEY_PARAM ];
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
