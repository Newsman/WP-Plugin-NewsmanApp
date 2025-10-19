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

namespace Newsman;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman WP plugin config
 *
 * @class \Newsman\Config
 */
class Config {
	/**
	 * API URL
	 */
	public const API_URL = 'https://ssl.newsman.app/api/';

	/**
	 * Autoload all options from plugin
	 */
	public const AUTOLOAD_OPTIONS = true;

	/**
	 * API REST version
	 */
	public const API_VERSION = '1.2';

	/**
	 * Cached blog IDs
	 *
	 * @var array
	 */
	protected $cached_blog_ids;

	/**
	 * Get class instance
	 *
	 * @return self \Newsman\Config
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new \Newsman\Config();
		}

		return $instance;
	}

	/**
	 * Get API user ID
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_user_id( $blog_id = null ) {
		return $this->get_blog_option( $blog_id, 'newsman_userid', '' );
	}

	/**
	 * Get API segment ID
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_segment_id( $blog_id = null ) {
		return $this->get_blog_option( $blog_id, 'newsman_segments', '' );
	}

	/**
	 * Is API usage enabled
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function use_api( $blog_id = null ) {
		return 'on' === $this->get_blog_option( $blog_id, 'newsman_api', '' );
	}

	/**
	 * Is send user IP address
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_send_user_ip( $blog_id = null ) {
		return 'on' === $this->get_blog_option( $blog_id, 'newsman_senduserip', '' );
	}

	/**
	 * Get server IP address.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_server_ip( $blog_id = null ) {
		return $this->get_blog_option( $blog_id, 'newsman_serverip', '' );
	}

	/**
	 * Get API key
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_api_key( $blog_id = null ) {
		return $this->get_blog_option( $blog_id, 'newsman_apikey', '' );
	}

	/**
	 * Get API list ID
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_list_id( $blog_id = null ) {
		return $this->get_blog_option( $blog_id, 'newsman_list', '' );
	}

	/**
	 * Get API URL
	 *
	 * @return string
	 */
	public function get_api_url() {
		return self::API_URL;
	}

	/**
	 * Get API version
	 *
	 * @return string
	 */
	public function get_api_version() {
		return self::API_VERSION;
	}

	/**
	 * Get log level
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int
	 */
	public function get_log_severity( $blog_id = null ) {
		return (int) $this->get_blog_option( $blog_id, 'newsman_developerlogseverity', 500 );
	}

	/**
	 * Get API timeout in seconds
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int
	 */
	public function get_api_timeout( $blog_id = null ) {
		$timeout = (int) $this->get_blog_option( $blog_id, 'newsman_developerapitimeout', 5 );
		if ( $timeout <= 0 ) {
			$timeout = 1;
		}
		return $timeout;
	}

	/**
	 * Is send user IP address
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_developer_active_user_ip( $blog_id = null ) {
		return 'on' === $this->get_blog_option( $blog_id, 'newsman_developeractiveuserip', '' );
	}

	/**
	 * Get server IP address.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_developer_user_ip( $blog_id = null ) {
		if ( ! $this->is_developer_active_user_ip( $blog_id ) ) {
			return '';
		}
		return $this->get_blog_option( $blog_id, 'newsman_developeruserip', '' );
	}

	/**
	 * Get lazy load plugin priority.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_plugin_lazy_priority( $blog_id = null ) {
		$value = $this->get_blog_option( $blog_id, 'newsman_developerpluginlazypriority' );
		if ( null === $value || false === $value || '' === $value ) {
			return \WP_Newsman::PLUGIN_PRIORITY_LAZY_LOAD;
		}

		return (int) $value;
	}

	/**
	 * Get export request authorize header name
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_export_authorize_header_name( $blog_id = null ) {
		return (string) $this->get_blog_option( $blog_id, 'newsman_export_authorize_header_name' );
	}

	/**
	 * Get export request authorize header key
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_export_authorize_header_key( $blog_id = null ) {
		return (string) $this->get_blog_option( $blog_id, 'newsman_export_authorize_header_key' );
	}

	/**
	 * Is checkout subscribe checkbox
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_checkout_newsletter( $blog_id = null ) {
		return 'on' === $this->get_blog_option( $blog_id, 'newsman_checkoutnewsletter', '' );
	}

	/**
	 * Is subscribe to SMS in checkout
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_checkout_sms( $blog_id = null ) {
		return 'on' === $this->get_blog_option( $blog_id, 'newsman_checkoutsms', '' );
	}

	/**
	 * Is checkout subscribe checkbox checked by default
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_checkout_newsletter_checked( $blog_id = null ) {
		return 'on' === $this->get_blog_option( $blog_id, 'newsman_checkoutnewsletterdefault', '' );
	}

	/**
	 * Get checkout subscribe checkbox label
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_checkout_newsletter_label( $blog_id = null ) {
		return (string) $this->get_blog_option( $blog_id, 'newsman_checkoutnewslettermessage' );
	}

	/**
	 * Is checkout subscribe email to list double opt-in
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_checkout_newsletter_double_optin( $blog_id = null ) {
		if ( 'init' === $this->get_blog_option( $blog_id, 'newsman_checkoutnewslettertype', '' ) ) {
			return true;
		} elseif ( 'save' === $this->get_blog_option( $blog_id, 'newsman_checkoutnewslettertype', '' ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Get Newsman email confirmation form ID
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_newsman_form_id( $blog_id = null ) {
		return (string) $this->get_blog_option( $blog_id, 'newsman_form_id' );
	}

	/**
	 * Get all available blog IDs that are not spam, not deleted, not archived and nay if public or not.
	 * Cache the blog IDs in this class instance.
	 *
	 * @return array
	 */
	public function get_all_blog_ids() {
		if ( null !== $this->cached_blog_ids ) {
			return $this->cached_blog_ids;
		}

		$args = array(
			'spam'     => 0,
			'deleted'  => 0,
			'archived' => 0,
			'public'   => null,
		);

		$sites = array();
		if ( function_exists( 'get_sites' ) ) {
			$sites = get_sites( $args );
		} elseif ( function_exists( 'wp_get_sites' ) ) {
			// Keep compatibility with WordPress 3.7.
			// phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_get_sitesFound
			$sites = wp_get_sites( $args );
		}
		// @see wp_get_sites() for empty array return value.
		if ( empty( $sites ) ) {
			$this->cached_blog_ids = array( get_current_blog_id() );
			return $this->cached_blog_ids;
		}

		$this->cached_blog_ids = array_map(
			function ( $site ) {
				return $site['blog_id'];
			},
			$sites
		);

		return $this->cached_blog_ids;
	}

	/**
	 * Get all user IDs from all blogs
	 *
	 * @return array
	 */
	public function get_all_user_ids() {
		$user_ids = array();
		foreach ( $this->get_all_blog_ids() as $blog_id ) {
			$user_ids[] = $this->get_user_id( $blog_id );
		}
		return array_unique( $user_ids );
	}

	/**
	 * Is features enabled with API
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_enabled_with_api( $blog_id = null ) {
		if ( ! $this->is_active( $blog_id ) ) {
			return false;
		}

		if ( ! $this->use_api( $blog_id ) ) {
			return false;
		}

		if ( ! $this->has_api_access( $blog_id ) ) {
			return false;
		}

		if ( empty( $this->get_list_id( $blog_id ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Is features enabled with API on any blog / site
	 *
	 * @return bool
	 */
	public function is_enabled_with_api_in_any() {
		foreach ( $this->get_all_blog_ids() as $blog_id ) {
			if ( $this->is_enabled_with_api( $blog_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Has user ID and API key
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function has_api_access( $blog_id = null ) {
		return ! empty( $this->get_user_id( $blog_id ) ) && ! empty( $this->get_api_key( $blog_id ) );
	}

	/**
	 * Is Newsman plugin active
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_active( $blog_id = null ) {
		$active_plugins = $this->get_blog_option( $blog_id, 'active_plugins' );
		foreach ( $active_plugins as $plugin ) {
			if ( stripos( $plugin, \WP_Newsman::NZ_PLUGIN_PATH ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get all blog IDs by list ID
	 *
	 * @param int $list_id List ID.
	 * @return array
	 */
	public function get_blog_ids_by_list_id( $list_id ) {
		if ( empty( $list_id ) ) {
			return array();
		}
		$blog_ids = array();
		foreach ( $this->get_all_blog_ids() as $blog_id ) {
			if ( $this->get_list_id( $blog_id ) === $list_id && $this->is_enabled_with_api( $blog_id ) ) {
				$blog_ids[] = $blog_id;
			}
		}
		return $blog_ids;
	}

	/**
	 * Get user IDs by blog IDs
	 *
	 * @param array $blog_ids WP blog IDs.
	 * @return array
	 */
	public function get_user_ids_by_blog_ids( $blog_ids ) {
		$user_ids = array();
		foreach ( $blog_ids as $blog_id ) {
			$user_ids[] = $this->get_user_id( $blog_id );
		}
		return array_unique( $user_ids );
	}

	/**
	 * Get all list IDs
	 *
	 * @return array
	 */
	public function get_all_list_ids() {
		$list_ids = array();
		foreach ( $this->get_all_blog_ids() as $blog_id ) {
			$list_ids[] = $this->get_list_id( $blog_id );
		}
		$list_ids = array_unique( $list_ids );

		$return = array();
		foreach ( $list_ids as $list_id ) {
			$blog_ids = $this->get_blog_ids_by_list_id( $list_id );
			if ( ! empty( $blog_ids ) ) {
				$return[] = $list_id;
			}
		}
		return $return;
	}

	/**
	 * Get blog IDs by API key
	 *
	 * @param string $api_key API key.
	 * @return array
	 */
	public function get_blog_ids_by_api_key( $api_key ) {
		$blog_ids = array();
		foreach ( $this->get_all_blog_ids() as $blog_id ) {
			if ( $this->get_api_key( $blog_id ) === $api_key && $this->is_enabled_with_api( $blog_id ) ) {
				$blog_ids[] = $blog_id;
			}
		}
		return $blog_ids;
	}

	/**
	 * Get blog option by blog ID
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param string $option_name Option name.
	 * @param mixed  $default_value Option default value..
	 *
	 * @return false|mixed|null
	 */
	public function get_blog_option( $blog_id, $option_name, $default_value = false ) {
		if ( function_exists( 'get_blog_option' ) ) {
			return get_blog_option( $blog_id, $option_name, $default_value );
		} else {
			return get_option( $option_name, $default_value );
		}
	}
}
