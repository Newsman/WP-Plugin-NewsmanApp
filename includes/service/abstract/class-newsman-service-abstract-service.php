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
 * Client Service Abstract Service
 *
 * @class Newsman_Service_Abstract_Service
 */
class Newsman_Service_Abstract_Service {
	/**
	 * API context
	 *
	 * @var Newsman_Api_ContextInterface
	 */
	protected $api_context;

	/**
	 * API client
	 *
	 * @var Newsman_Api_ClientInterface
	 */
	protected $api_client;

	/**
	 * Email validator
	 *
	 * @var Newsman_Validator_Email
	 */
	protected $validator_email;

	/**
	 * WP blog ID
	 *
	 * @var null|int
	 */
	protected $blog_id;

	/**
	 * Config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * Logger
	 *
	 * @var Newsman_WC_Logger
	 */
	protected $logger;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->api_context     = new Newsman_Api_Context();
		$this->api_client      = new Newsman_Api_Client();
		$this->validator_email = Newsman_Validator_Email::init();
		$this->config          = Newsman_Config::init();
		$this->logger          = Newsman_WC_Logger::init();
	}

	/**
	 * Create API context
	 *
	 * @return Newsman_Api_Context|Newsman_Api_ContextInterface
	 */
	public function create_api_context() {
		$this->api_context = new Newsman_Api_Context();
		$this->api_context->set_blog_id( $this->get_blog_id() );
		return $this->api_context;
	}

	/**
	 * Create API client
	 *
	 * @return Newsman_Api_Client|Newsman_Api_ClientInterface
	 */
	public function create_api_client() {
		$this->api_client = new Newsman_Api_Client();
		return $this->api_client;
	}

	/**
	 * Execute API service
	 *
	 * @param Newsman_Service_Context_Abstract_Context $context APi service context.
	 * @return array
	 */
	public function execute( $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return array();
	}

	/**
	 * Set WP blog ID
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return $this
	 */
	public function set_blog_id( $blog_id ) {
		$this->blog_id = $blog_id;
		return $this;
	}

	/**
	 * Get blog ID
	 *
	 * @return int|null WP blog ID.
	 */
	public function get_blog_id() {
		return $this->blog_id;
	}

	/**
	 * Validate email address
	 *
	 * @param string $email Email address to validate.
	 * @return void
	 * @throws Exception Throws error on invalid email address.
	 */
	public function validate_email( $email ) {
		$validator = new Newsman_Validator_Email();
		if ( ! $validator->is_valid( $email ) ) {
			$e = new Exception(
				sprintf(
					/* translators: 1: Email */
					esc_html__( 'Invalid email address %1', 'newsman' ),
					$email
				)
			);
			$this->logger->log_exception( $e );
			throw $e;
		}
	}
}
