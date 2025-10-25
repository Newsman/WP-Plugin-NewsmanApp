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

namespace Newsman\Admin;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Util\WooCommerceExist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings class
 *
 * @class \Newsman\Admin\Settings
 */
class Settings {
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
	 *  Woo Commerce Exists
	 *
	 * @var WooCommerceExist
	 */
	protected $woo_commerce_exists;

	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings';

	/**
	 * New nonce for the page
	 *
	 * @var string
	 */
	public $new_nonce = '';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = '';

	/**
	 * Form fields
	 *
	 * @var array
	 */
	public $form_fields = array();

	/**
	 * Form values
	 *
	 * @var array
	 */
	public $form_values = array();

	/**
	 * Is valid credentials
	 *
	 * @var bool
	 */
	public $valid_credentials = true;

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
		$this->config              = Config::init();
		$this->logger              = Logger::init();
		$this->woo_commerce_exists = new WooCommerceExist();
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

			$context = new \Newsman\Service\Context\Configuration\User();
			$context->set_user_id( $user_id )
				->set_api_key( $api_key );
			$get_list_all = new \Newsman\Service\Configuration\GetListAll();
			$lists_data   = $get_list_all->execute( $context );
			return $lists_data;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
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

			$context = new \Newsman\Service\Context\Configuration\EmailList();
			$context->set_user_id( $user_id )
				->set_api_key( $api_key )
				->set_list_id( $list_id );
			$get_segment_all = new \Newsman\Service\Configuration\GetSegmentAll();
			$segments_data   = $get_segment_all->execute( $context );
			return $segments_data;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
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

			$context = new \Newsman\Service\Context\Configuration\User();
			$context->set_user_id( $user_id )
				->set_api_key( $api_key );
			$get_sms_list_all = new \Newsman\Service\Configuration\Sms\GetListAll();
			$lists_data       = $get_sms_list_all->execute( $context );
			return $lists_data;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
	}

	/**
	 * Call API set feed on list
	 *
	 * @param string $list_id List ID.
	 * @param string $url URL of feed.
	 * @param string $website Website URL.
	 * @param string $type Type of the feed.
	 * @param bool   $return_id Is return the ID of the feed.
	 * @return array|false
	 */
	public function set_feed_on_list( $list_id, $url, $website, $type = 'fixed', $return_id = false ) {
		try {
			if ( null === $list_id ) {
				$list_id = $this->get_config()->get_list_id();
			}

			$context = new \Newsman\Service\Context\Configuration\SetFeedOnList();
			$context->set_list_id( $list_id )
				->set_url( $url )
				->set_website( $website )
				->set_type( $type )
				->set_return_id( $return_id );
			$set_feed = new \Newsman\Service\Configuration\SetFeedOnList();
			$result   = $set_feed->execute( $context );
			return $result;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
	}

	/**
	 * Call API update feed and set authorize header name and secret
	 *
	 * @param string $list_id List ID.
	 * @param string $feed_id Feed ID.
	 * @param string $auth_name Authorize header name.
	 * @param string $auth_value Authorize header value.
	 * @return false|string|array
	 */
	protected function update_feed_authorize( $list_id, $feed_id, $auth_name, $auth_value ) {
		try {
			if ( null === $list_id ) {
				$list_id = $this->get_config()->get_list_id();
			}

			$properties = array(
				'auth_header_name'  => $auth_name,
				'auth_header_value' => $auth_value,
			);

			$context = new \Newsman\Service\Context\Configuration\UpdateFeed();
			$context->set_list_id( $list_id )
				->set_feed_id( $feed_id )
				->set_properties( $properties );
			$set_feed = new \Newsman\Service\Configuration\UpdateFeed();
			return $set_feed->execute( $context );
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
	}

	/**
	 * Update export authorize header options
	 *
	 * @param string $auth_name Authorize header name.
	 * @param string $auth_value Authorize header value.
	 * @return void
	 */
	protected function update_export_authorize_header( $auth_name, $auth_value ) {
		update_option( 'newsman_export_authorize_header_name', $auth_name, Config::AUTOLOAD_OPTIONS );
		update_option( 'newsman_export_authorize_header_key', $auth_value, Config::AUTOLOAD_OPTIONS );
	}

	/**
	 * Generates a random string containing lowercase letters (a-z) and hyphens (-).
	 * Suitable for use as an HTTP header name.
	 *
	 * @param int $length The length of the random string to generate. Default is 16.
	 * @param int $recursion_depth Tracks recursion depth to prevent infinite loops. Don't set manually.
	 * @return string The randomly generated string.
	 */
	protected function generate_random_header_name( $length = 16, $recursion_depth = 0 ) {
		// Prevent infinite recursion - limit to 3 levels.
		if ( $recursion_depth > 3 ) {
			$characters = 'abcdefghijklmnopqrstuvwxyz';
			return substr( str_shuffle( $characters ), 0, $length );
		}

		$characters        = 'abcdefghijklmnopqrstuvwxyz-';
		$characters_length = strlen( $characters );
		$random_string     = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $characters[ wp_rand( 0, $characters_length - 1 ) ];
		}

		// Ensure the string doesn't start or end with a hyphen, and doesn't have consecutive hyphens.
		$random_string = ltrim( $random_string, '-' );
		$random_string = rtrim( $random_string, '-' );
		$random_string = preg_replace( '/-{2,}/', '-', $random_string );

		// If after cleanup the string is too short, append some random letters.
		if ( strlen( $random_string ) < $length / 2 ) {
			$additional     = $this->generate_random_header_name(
				$length - strlen( $random_string ),
				$recursion_depth + 1
			);
			$random_string .= $additional;
		}

		return $random_string;
	}

	/**
	 * Generates a random password consisting of uppercase letters, lowercase letters, and numbers.
	 *
	 * @param int $length The length of the password to generate. Default is 16.
	 * @return string The randomly generated password.
	 */
	protected function generate_random_password( $length = 16 ) {
		$lowercase = 'abcdefghijklmnopqrstuvwxyz';
		$uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$numbers   = '0123456789';

		// Combine all characters.
		$all_chars    = $lowercase . $uppercase . $numbers;
		$chars_length = strlen( $all_chars );

		$password = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$password .= $all_chars[ wp_rand( 0, $chars_length - 1 ) ];
		}

		// Ensure password has at least one character from each required set.
		$has_lowercase = preg_match( '/[a-z]/', $password );
		$has_uppercase = preg_match( '/[A-Z]/', $password );
		$has_number    = preg_match( '/[0-9]/', $password );

		// Replace characters if any required type is missing.
		if ( ! $has_lowercase ) {
			$password[ wp_rand( 0, $length - 1 ) ] = $lowercase[ wp_rand( 0, strlen( $lowercase ) - 1 ) ];
		}

		if ( ! $has_uppercase ) {
			$password[ wp_rand( 0, $length - 1 ) ] = $uppercase[ wp_rand( 0, strlen( $uppercase ) - 1 ) ];
		}

		if ( ! $has_number ) {
			$password[ wp_rand( 0, $length - 1 ) ] = $numbers[ wp_rand( 0, strlen( $numbers ) - 1 ) ];
		}

		return $password;
	}

	/**
	 * Is valid API credentials
	 *
	 * @param null|int|string $user_id API user ID.
	 * @param null|string     $api_key API key.
	 *
	 * @return bool
	 */
	public function is_valid_credentials( $user_id = null, $api_key = null ) {
		return ( false !== $this->retrieve_api_all_lists( $user_id, $api_key ) );
	}

	/**
	 * Get page's current nonce
	 *
	 * @return string
	 */
	public function get_current_nonce() {
		$nonce = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}
		return $nonce;
	}

	/**
	 * Validate current nonce on page
	 *
	 * @param array $post_parameters List of not empty POST parameters that triggers the nonce validation.
	 * @return bool
	 */
	public function validate_nonce( $post_parameters = array() ) {
		$has_parameters = false;
		foreach ( $post_parameters as $key => $value ) {
			if ( isset( $_POST[ $key ] ) ) {
				$has_parameters = true;
				break;
			}
		}

		$nonce = $this->get_current_nonce();
		if ( ! empty( $nonce ) || $has_parameters ) {
			return wp_verify_nonce( $nonce, $this->nonce_action ) ? true : false;
		}
		return true;
	}

	/**
	 * Create a new nonce for page (current rendered page)
	 *
	 * @return string
	 */
	public function create_nonce() {
		$this->new_nonce = wp_create_nonce( $this->nonce_action );
		wp_nonce_field( $this->nonce_action, '_wpnonce', false );
		return $this->new_nonce;
	}

	/**
	 * Initialize form values from POST variable
	 *
	 * @return void
	 */
	public function init_form_values_from_post() {
		foreach ( $this->form_fields as $name ) {
			$this->form_values[ $name ] = '';

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $name ] ) && ! empty( $_POST[ $name ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( is_array( $_POST[ $name ] ) ) {
					$this->form_values[ $name ] = array();
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					foreach ( $_POST[ $name ] as $key => $value ) {
						$this->form_values[ $name ][ $key ] = sanitize_text_field( wp_unslash( $value ) );
					}
				} else {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$this->form_values[ $name ] = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
				}
			}
		}
	}

	/**
	 * Initialize form values from configuration options
	 *
	 * @return void
	 */
	public function init_form_values_from_option() {
		foreach ( $this->form_fields as $name ) {
			$this->form_values[ $name ] = get_option( $name );
		}
	}

	/**
	 * Save admin configuration from form fields
	 *
	 * @return array
	 */
	public function save_form_values() {
		foreach ( $this->form_fields as $name ) {
			if ( isset( $this->form_values[ $name ] ) ) {
				update_option( $name, $this->form_values[ $name ], Config::AUTOLOAD_OPTIONS );
			}
		}

		return $this->form_values;
	}

	/**
	 * Get \Newsman\Config
	 *
	 * @return Config|null Newsman config.
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Woo Commerce exists
	 *
	 * @return bool
	 */
	public function is_woo_commerce_exists() {
		return $this->woo_commerce_exists->exist();
	}
}
