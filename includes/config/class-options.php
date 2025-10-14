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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman WP plugin config options
 *
 * @class \Newsman\Config\Options
 */
class Options {
	/**
	 * All options loaded.
	 *
	 * @var array
	 */
	protected static $options;

	/**
	 * Get class instance
	 *
	 * @return self \Newsman\Config\Sms
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Options();
			self::load_all_options();
		}

		return $instance;
	}

	/**
	 * Load all options from database.
	 *
	 * @return array
	 */
	private static function load_all_options() {
		$options = \Newsman\Options::get_instance();
		if ( null === self::$options ) {
			self::$options = $options->get_all_options( true );
		}
		return self::$options;
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
}
