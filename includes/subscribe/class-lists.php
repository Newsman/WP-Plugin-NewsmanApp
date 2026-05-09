<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman shared list dropdown fetcher.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Subscribe;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Service\Configuration\GetListAll;
use Newsman\Service\Context\Configuration\User as ConfigurationUser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared Newsman list-dropdown fetcher used by every form-source integration's editor UI.
 *
 * Caches the resolved options in a per-blog transient so editor saves do not hammer the
 * Newsman API.
 *
 * @class \Newsman\Subscribe\Lists
 */
class Lists {
	/**
	 * Transient TTL for the list dropdown cache (10 minutes).
	 */
	public const CACHE_TTL = 600;

	/**
	 * Transient key prefix for the cached list dropdown options.
	 */
	public const CACHE_KEY_PREFIX = 'newsman_lists_';

	/**
	 * Build the Newsman list dropdown options as `[ id => name ]`.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array
	 */
	public static function get_for_select( $blog_id ) {
		$transient_key = self::CACHE_KEY_PREFIX . $blog_id;

		$cached = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			/**
			 * Filter the Newsman list options shown in the editor list dropdown.
			 *
			 * Fired both on cache hit and after a fresh fetch — integrators can append/remove
			 * options or rebuild the list without affecting the cached payload.
			 *
			 * @param array $options Map of `[ list_id => list_name ]`.
			 * @param int   $blog_id Current WP blog ID.
			 */
			return apply_filters( 'newsman_lists_for_select', $cached, $blog_id );
		}

		$config  = Config::init();
		$user_id = $config->get_user_id( $blog_id );
		$api_key = $config->get_api_key( $blog_id );

		if ( empty( $user_id ) || empty( $api_key ) ) {
			/** This filter is documented in this file */
			return apply_filters( 'newsman_lists_for_select', array(), $blog_id );
		}

		$options = array();
		try {
			$context = new ConfigurationUser();
			$context->set_blog_id( $blog_id )
				->set_user_id( $user_id )
				->set_api_key( $api_key );

			$service = new GetListAll();
			$service->set_blog_id( $blog_id );
			$result = $service->execute( $context );

			if ( is_array( $result ) ) {
				foreach ( $result as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$id = '';
					if ( isset( $row['list_id'] ) ) {
						$id = (string) $row['list_id'];
					} elseif ( isset( $row['id'] ) ) {
						$id = (string) $row['id'];
					}
					$name = $id;
					if ( isset( $row['list_name'] ) ) {
						$name = (string) $row['list_name'];
					} elseif ( isset( $row['name'] ) ) {
						$name = (string) $row['name'];
					}
					if ( '' !== $id ) {
						$options[ $id ] = $name;
					}
				}
			}
		} catch ( \Exception $e ) {
			Logger::init()->log_exception( $e );
			/** This filter is documented in this file */
			return apply_filters( 'newsman_lists_for_select', array(), $blog_id );
		}

		/**
		 * Filter the list dropdown cache TTL (in seconds).
		 *
		 * Default is 600s (10 minutes). Lower it to make editors reflect Newsman list changes
		 * faster, or raise it to reduce API pressure for large multi-author teams.
		 *
		 * @param int $ttl     Cache TTL in seconds.
		 * @param int $blog_id Current WP blog ID.
		 */
		$ttl = (int) apply_filters( 'newsman_lists_cache_ttl', self::CACHE_TTL, $blog_id );
		set_transient( $transient_key, $options, $ttl );

		/** This filter is documented in this file */
		return apply_filters( 'newsman_lists_for_select', $options, $blog_id );
	}
}
