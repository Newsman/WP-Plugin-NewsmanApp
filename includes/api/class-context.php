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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class API Context
 *
 * @class \Newsman\Api\Context
 */
class Context implements ContextInterface {
	/**
	 * Newsman config instance
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * WP blog ID
	 *
	 * @var int|string
	 */
	protected $blog_id;

	/**
	 * API user ID
	 *
	 * @var string
	 */
	protected $user_id;

	/**
	 * API segment ID
	 *
	 * @var string
	 */
	protected $segment_id;

	/**
	 * API key
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * API REST endpoint
	 *
	 * @var string
	 */
	protected $endpoint;

	/**
	 * API list ID
	 *
	 * @var int
	 */
	protected $list_id;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Get API user ID
	 *
	 * @return string
	 */
	public function get_user_id() {
		if ( null !== $this->user_id ) {
			return $this->user_id;
		}
		return $this->config->get_user_id( $this->get_blog_id() );
	}

	/**
	 * Get API segment ID
	 *
	 * @return string
	 */
	public function get_segment_id() {
		if ( null !== $this->segment_id ) {
			return $this->segment_id;
		}
		return $this->config->get_segment_id( $this->get_blog_id() );
	}

	/**
	 * Get API key
	 *
	 * @return string
	 */
	public function get_api_key() {
		if ( null !== $this->api_key ) {
			return $this->api_key;
		}
		return $this->config->get_api_key( $this->get_blog_id() );
	}

	/**
	 * Set blog ID
	 *
	 * @param int|string $blog_id WP blog ID.
	 *
	 * @return ContextInterface
	 */
	public function set_blog_id( $blog_id ) {
		$this->blog_id = $blog_id;
		return $this;
	}

	/**
	 * Get WP blog ID
	 *
	 * @return int|string
	 */
	public function get_blog_id() {
		if ( null === $this->blog_id ) {
			$this->blog_id = get_current_blog_id();
		}
		return $this->blog_id;
	}

	/**
	 * Set API user ID
	 *
	 * @param int|string $user_id API user ID.
	 *
	 * @return ContextInterface
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = $user_id;
		return $this;
	}

	/**
	 * Set API segment ID
	 *
	 * @param string $segment_id Segment ID.
	 *
	 * @return ContextInterface
	 */
	public function set_segment_id( $segment_id ) {
		$this->segment_id = $segment_id;
		return $this;
	}

	/**
	 * Set API key
	 *
	 * @param string $api_key API key.
	 *
	 * @return ContextInterface
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = $api_key;
		return $this;
	}

	/**
	 * API REST HTTP endpoint
	 *
	 * @param string $endpoint API REST endpoint.
	 *
	 * @return ContextInterface
	 */
	public function set_endpoint( $endpoint ) {
		$this->endpoint = $endpoint;
		return $this;
	}

	/**
	 * Get API REST endpoint
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return $this->endpoint;
	}

	/**
	 * Set API list ID
	 *
	 * @param int $list_id API list ID.
	 *
	 * @return ContextInterface
	 */
	public function set_list_id( $list_id ) {
		$this->list_id = $list_id;
		return $this;
	}

	/**
	 * Get API list ID
	 *
	 * @return int
	 */
	public function get_list_id() {
		if ( null !== $this->list_id ) {
			return $this->list_id;
		}
		return $this->config->get_list_id( $this->get_blog_id() );
	}
}
