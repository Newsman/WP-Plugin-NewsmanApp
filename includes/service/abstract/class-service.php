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

namespace Newsman\Service\Abstract;

use Newsman\Api\ClientInterface;
use Newsman\Api\ContextInterface;
use Newsman\Config;
use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Abstract Service
 *
 * @class \Newsman\Service\Abstract\Service
 */
class Service {
	/**
	 * API context
	 *
	 * @var ContextInterface
	 */
	protected $api_context;

	/**
	 * API client
	 *
	 * @var ClientInterface
	 */
	protected $api_client;

	/**
	 * Email validator
	 *
	 * @var \Newsman\Validator\Email
	 */
	protected $validator_email;

	/**
	 * WP blog ID
	 *
	 * @var null|int
	 */
	protected $blog_id;

	/**
	 * Remarketing Config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->api_context        = new \Newsman\Api\Context();
		$this->api_client         = new \Newsman\Api\Client();
		$this->validator_email    = \Newsman\Validator\Email::init();
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->logger             = Logger::init();
	}

	/**
	 * Create API context
	 *
	 * @return \Newsman\Api\Context|ContextInterface
	 */
	public function create_api_context() {
		$this->api_context = new \Newsman\Api\Context();
		$this->api_context->set_blog_id( $this->get_blog_id() );
		return $this->api_context;
	}

	/**
	 * Create API client
	 *
	 * @return \Newsman\Api\Client|ClientInterface
	 */
	public function create_api_client() {
		$this->api_client = new \Newsman\Api\Client();
		return $this->api_client;
	}

	/**
	 * Execute API service
	 *
	 * @param \Newsman\Service\Context\Abstract\Context $context APi service context.
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
	 * @throws \Exception Throws error on invalid email address.
	 */
	public function validate_email( $email ) {
		$validator = new \Newsman\Validator\Email();
		if ( ! $validator->is_valid( $email ) ) {
			$e = new \Exception(
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
