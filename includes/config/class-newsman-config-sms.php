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
 * Newsman WP plugin config SMS
 *
 * @class Newsman_Config_Sms
 */
class Newsman_Config_Sms {
	/**
	 * Newsman config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Newsman_Config::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self Newsman_Config_Sms
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Newsman_Config_Sms();
		}

		return $instance;
	}

	/**
	 * Get API list ID
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_list_id( $blog_id = null ) {
		return $this->config->get_blog_option( $blog_id, 'newsman_smslist', '' );
	}

	/**
	 * Is API SMS usage enabled
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function use_api( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_usesms', '' );
	}

	/**
	 * Is SMS features enabled with API
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_enabled_with_api( $blog_id = null ) {
		if ( ! $this->config->is_active( $blog_id ) ) {
			return false;
		}

		if ( ! $this->use_api( $blog_id ) ) {
			return false;
		}

		if ( ! $this->config->has_api_access( $blog_id ) ) {
			return false;
		}

		if ( empty( $this->get_list_id( $blog_id ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Is API send SMS in test mode active
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function is_test_mode( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_smstest', '' );
	}

	/**
	 * Get SMS test number
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_test_phone_number( $blog_id = null ) {
		return (string) $this->config->get_blog_option( $blog_id, 'newsman_smstestnr', '' );
	}
}
