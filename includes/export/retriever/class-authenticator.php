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

namespace Newsman\Export\Retriever;

use Newsman\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Factory
 *
 * @class \Newsman\Export\Retriever\Authenticator
 */
class Authenticator {
	/**
	 * API key request parameter
	 */
	public const API_KEY_PARAM = 'nzmhash';

	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Authenticate incoming Newsman export request
	 *
	 * @param string   $api_key Newsman API key.
	 * @param null|int $blog_id WP blog ID.
	 * @return true
	 * @throws \OutOfBoundsException Invalid API key.
	 */
	public function authenticate( $api_key, $blog_id = null ) {
		if ( empty( $api_key ) ) {
			throw new \OutOfBoundsException( esc_html__( 'Empty API key provided.', 'newsman' ) );
		}

		$config_api_key = $this->config->get_api_key( $blog_id );

		$alternate_name = $this->config->get_export_authorize_header_name( $blog_id );
		$alternate_key  = $this->config->get_export_authorize_header_key( $blog_id );
		$is_alternate   = false;
		if ( ! empty( $alternate_name ) && ! empty( $alternate_key ) ) {
			$is_alternate = true;
		}

		$is_authenticated = false;
		if ( $config_api_key === $api_key ) {
			$is_authenticated = true;
		}
		if ( $is_alternate && ( $alternate_key === $api_key ) ) {
			$is_authenticated = true;
		}

		if ( ! $is_authenticated ) {
			throw new \OutOfBoundsException(
				/* translators: 1: WordPress blog ID */
				esc_html( sprintf( __( 'Invalid API key for blog ID %d', 'newsman' ), $blog_id ) )
			);
		}

		return true;
	}
}
