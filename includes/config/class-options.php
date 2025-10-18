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
	 * Get option value
	 *
	 * @param string   $name Option name.
	 * @param null|int $blog_id WP blog ID.
	 * @return string|null
	 */
	public function get( $name, $blog_id = null ) {
		$blog_id;
		if ( isset( self::$options[ $name ] ) ) {
			return self::$options[ $name ];
		}
		return null;
	}
}
