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
 * Read-through cache wrapper around the Newsman GetListAll service. The cache
 * lives in `Lists_Transient` (15-minute TTL, per-blog, keyed by current Newsman
 * User ID); see that class for the storage shape + OAuth bypass semantics.
 *
 * The static `fetch_from_api()` helper is also used by the Sync page Refresh
 * button to force a cache refresh without going through the read-through path.
 *
 * @class \Newsman\Subscribe\Lists
 */
class Lists {
	/**
	 * Build the Newsman list dropdown options as `[ id => name ]`.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array
	 */
	public static function get_for_select( $blog_id ) {
		$config  = Config::init();
		$user_id = (int) $config->get_user_id( $blog_id );
		$api_key = (string) $config->get_api_key( $blog_id );

		if ( $user_id <= 0 || '' === $api_key ) {
			/**
			 * Filter the Newsman list options shown in the editor list dropdown.
			 *
			 * Fired both on cache hit and after a fresh fetch — integrators can append/remove
			 * options or rebuild the list without affecting the cached payload.
			 *
			 * @param array $options Map of `[ list_id => list_name ]`.
			 * @param int   $blog_id Current WP blog ID.
			 */
			return apply_filters( 'newsman_lists_for_select', array(), $blog_id );
		}

		// Read-through cache. The cache is bypassed during OAuth (see
		// `Lists_Transient::set_skip()` in the OAuth handler) so partial /
		// not-yet-persisted credentials never poison the row.
		$cached = Lists_Transient::get( $blog_id, $user_id );
		if ( is_array( $cached ) ) {
			/** This filter is documented in this file */
			return apply_filters( 'newsman_lists_for_select', $cached, $blog_id );
		}

		try {
			$options = self::fetch_from_api( $blog_id, $user_id, $api_key );
		} catch ( \Exception $e ) {
			Logger::init()->log_exception( $e );
			// Stale-on-error: the fresh transient was empty AND the API call
			// failed (network blip, expired creds, ...). Fall back to the
			// persistent last-known-good value if one exists, so the admin
			// sees stale data instead of an empty dropdown.
			$stale = Lists_Transient::get_stale( $blog_id, $user_id );
			if ( is_array( $stale ) ) {
				/** This filter is documented in this file */
				return apply_filters( 'newsman_lists_for_select', $stale, $blog_id );
			}
			/** This filter is documented in this file */
			return apply_filters( 'newsman_lists_for_select', array(), $blog_id );
		}

		Lists_Transient::save( $blog_id, $user_id, $options );

		/** This filter is documented in this file */
		return apply_filters( 'newsman_lists_for_select', $options, $blog_id );
	}

	/**
	 * Force-fetch the list catalogue from the Newsman API, bypassing the cache.
	 *
	 * Used by `Lists::get_for_select()` on a miss and by the Sync page Refresh
	 * button. Throws on API failure so the caller can decide whether to surface
	 * the error or fall back to the existing (stale) transient row.
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param int    $user_id Newsman User ID.
	 * @param string $api_key Newsman API key.
	 * @return array `[ list_id => list_name ]`.
	 * @throws \Exception When the underlying GetListAll service raises.
	 */
	public static function fetch_from_api( $blog_id, $user_id, $api_key ) {
		$context = new ConfigurationUser();
		$context->set_blog_id( $blog_id )
			->set_user_id( $user_id )
			->set_api_key( $api_key );

		$service = new GetListAll();
		$service->set_blog_id( $blog_id );
		$result = $service->execute( $context );

		$options = array();
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
		return $options;
	}
}
