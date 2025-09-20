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
 * Admin settings class
 *
 * @class Newsman_Admin_Settings
 */
class Newsman_Admin_Settings {
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
	 * Messages
	 *
	 * @var array
	 */
	public $message = array();

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Newsman_Config::init();
		$this->logger = Newsman_WC_Logger::init();
	}

	/**
	 * Is OAuth allow or redirect.
	 *
	 * @param bool $inside_oauth In OAuth process than redirect.
	 * @return void
	 */
	public function is_oauth( $inside_oauth = false ) {

		if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
			return;
		}

		$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		if ( $inside_oauth ) {
			if ( ! empty( get_option( 'newsman_userid' ) ) ) {
				wp_safe_redirect( 'https://' . $host . '/wp-admin/admin.php?page=NewsmanSettings' );
			}

			return;
		}

		if ( empty( get_option( 'newsman_userid' ) ) ) {
			wp_safe_redirect( 'https://' . $host . '/wp-admin/admin.php?page=NewsmanOauth' );
		}
	}

	/**
	 * Set message
	 *
	 * @param string $status The status of the message (the css class of the message).
	 * @param string $message The actual message.
	 * @return void
	 */
	public function set_message_backend( $status, $message ) {
		$this->message = array(
			'status'  => $status,
			'message' => $message,
		);
	}

	/**
	 * Returns the current message for the backend.
	 *
	 * @return array The message array
	 */
	public function get_backend_message() {
		return $this->message;
	}

	/**
	 * Call API get all lists by current API user ID
	 *
	 * @param null|int|string $user_id API user ID.
	 * @param null|string     $api_key API key.
	 * @return array|false
	 */
	public function retrieve_api_all_lists( $user_id = null, $api_key = null ) {
		try {
			if ( null === $user_id ) {
				$user_id = $this->config->get_user_id();
			}
			if ( null === $api_key ) {
				$api_key = $this->config->get_api_key();
			}

			$context = new Newsman_Service_Context_Configuration_User();
			$context->set_user_id( $user_id )
				->set_api_key( $api_key );
			$get_list_all = new Newsman_Service_Configuration_GetListAll();
			$lists_data   = $get_list_all->execute( $context );
			return $lists_data;
		} catch ( Exception $e ) {
			$this->logger->error( $e->getCode() . ' ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Call API get all segments by list ID
	 *
	 * @param string          $list_id API list ID.
	 * @param null|int|string $user_id API user ID.
	 * @param null|string     $api_key API key.
	 * @return array|false
	 */
	public function retrieve_api_all_segments( $list_id, $user_id = null, $api_key = null ) {
		try {
			if ( null === $user_id ) {
				$user_id = $this->config->get_user_id();
			}
			if ( null === $api_key ) {
				$api_key = $this->config->get_api_key();
			}

			$context = new Newsman_Service_Context_Configuration_List();
			$context->set_user_id( $user_id )
				->set_api_key( $api_key )
				->set_list_id( $list_id );
			$get_segment_all = new Newsman_Service_Configuration_GetSegmentAll();
			$segments_data   = $get_segment_all->execute( $context );
			return $segments_data;
		} catch ( Exception $e ) {
			$this->logger->error( $e->getCode() . ' ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Call API get all SMS lists by current API user ID
	 *
	 * @param null|int|string $user_id API user ID.
	 * @param null|string     $api_key API key.
	 * @return array|false
	 */
	public function retrieve_api_sms_all_lists( $user_id = null, $api_key = null ) {
		try {
			if ( null === $user_id ) {
				$user_id = $this->config->get_user_id();
			}
			if ( null === $api_key ) {
				$api_key = $this->config->get_api_key();
			}

			$context = new Newsman_Service_Context_Configuration_User();
			$context->set_user_id( $user_id )
				->set_api_key( $api_key );
			$get_sms_list_all = new Newsman_Service_Configuration_Sms_GetListAll();
			$lists_data       = $get_sms_list_all->execute( $context );
			return $lists_data;
		} catch ( Exception $e ) {
			$this->logger->error( $e->getCode() . ' ' . $e->getMessage() );
			return false;
		}
	}
}
