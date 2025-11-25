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

namespace Newsman\Config;

use Newsman\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman WP plugin config SMS
 *
 * @class \Newsman\Config\Sms
 */
class Sms {
	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self \Newsman\Config\Sms
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Sms();
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

	/**
	 * Get order SMS active by configuration name
	 *
	 * @param string   $name Config name.
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_order_sms_active_by_name( $name, $blog_id = null ) {
		if ( ! in_array( $name, $this->config->get_order_status_to_name(), true ) ) {
			return false;
		}
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_sms' . $name . 'activate', '' );
	}

	/**
	 * Get order SMS active by configuration name
	 *
	 * @param string   $name Config name.
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_order_sms_text_by_name( $name, $blog_id = null ) {
		if ( ! in_array( $name, $this->config->get_order_status_to_name(), true ) ) {
			return false;
		}
		return (string) $this->config->get_blog_option( $blog_id, 'newsman_sms' . $name . 'text', '' );
	}

	/**
	 * Is valid SMS message by order status
	 *
	 * @param string $status Order status.
	 * @return bool
	 */
	public function is_valid_sms_by_order_status( $status ) {
		$config_name = $this->config->get_order_config_name_by_status( $status );
		if ( false === $config_name ) {
			return false;
		}

		if ( ! $this->is_order_sms_active_by_name( $config_name ) ) {
			return false;
		}

		$message = $this->get_order_sms_text_by_name( $config_name );
		if ( empty( $message ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Is SMS send Cargus AWB
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function is_sms_send_cargus_awb( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_sms_send_cargus_awb', '' );
	}

	/**
	 * Get SMS Cargus AWB Message
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_sms_cargus_awb_message( $blog_id = null ) {
		return (string) $this->config->get_blog_option( $blog_id, 'newsman_sms_cargus_awb_message', '' );
	}

	/**
	 * Is SMS send SamedayCourier AWB
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function is_sms_send_sameday_awb( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_sms_send_sameday_awb', '' );
	}

	/**
	 * Get SMS SamedayCourier AWB Message
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_sms_sameday_awb_message( $blog_id = null ) {
		return (string) $this->config->get_blog_option( $blog_id, 'newsman_sms_sameday_awb_message', '' );
	}

	/**
	 * Is SMS send FAN Courier AWB
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function is_sms_send_fancourier_awb( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_sms_send_fancourier_awb', '' );
	}

	/**
	 * Get SMS FAN Courier AWB Message
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_sms_fancourier_awb_message( $blog_id = null ) {
		return (string) $this->config->get_blog_option( $blog_id, 'newsman_sms_fancourier_awb_message', '' );
	}
}
