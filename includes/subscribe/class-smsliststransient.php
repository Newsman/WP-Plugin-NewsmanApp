<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman per-blog SMS Lists transient cache.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Subscribe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-blog transient cache for the Newsman SMS list dropdown.
 *
 * Stores `[ user_id => raw_sms_lists_response ]` keyed by Newsman User ID
 * inside a per-blog WordPress transient. SMS lists come from the dedicated
 * `Newsman\Service\Configuration\Sms\GetListAll` service (separate from the
 * regular email-lists endpoint) and the raw response shape is preserved so
 * existing callers don't need to change the rendering loop in `backend-sync.php`
 * or `backend-sms.php`.
 *
 * TTL is 15 minutes. Same OAuth bypass + stale-on-error semantics as
 * `Lists_Transient` — see that class's docblock for the rationale.
 *
 * @class \Newsman\Subscribe\SmsLists_Transient
 */
class SmsLists_Transient {
	/**
	 * Fresh-tier cache lifetime — 1 hour. Mirrors `Lists_Transient::TTL`
	 * since SMS lists are a sibling endpoint and rarely change.
	 */
	public const TTL = HOUR_IN_SECONDS;

	/**
	 * Per-blog transient key prefix. The full key is `<prefix><blog_id>`.
	 */
	public const TRANSIENT_KEY_PREFIX = 'newsman_sms_lists_cache_';

	/**
	 * Per-blog wp_option key prefix for the persistent stale-fallback tier.
	 */
	public const FALLBACK_OPTION_PREFIX = 'newsman_sms_lists_fallback_';

	/**
	 * OAuth bypass flag — when true, all reads return null and writes are no-ops.
	 *
	 * @var bool
	 */
	protected static $skip = false;

	/**
	 * Enable / disable the OAuth bypass for the rest of the current PHP request.
	 *
	 * @param bool $skip Default true.
	 * @return void
	 */
	public static function set_skip( $skip = true ) {
		self::$skip = (bool) $skip;
	}

	/**
	 * Whether the cache is currently bypassed.
	 *
	 * @return bool
	 */
	public static function is_skipped() {
		return self::$skip;
	}

	/**
	 * Read cached SMS lists for a `(blog_id, user_id)` pair.
	 *
	 * @param int $blog_id WP blog ID.
	 * @param int $user_id Newsman User ID.
	 * @return array|null Raw SMS lists payload on hit, null on miss / bypass.
	 */
	public static function get( $blog_id, $user_id ) {
		if ( self::$skip ) {
			return null;
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return null;
		}
		$cache = get_transient( self::transient_key( $blog_id ) );
		if ( ! is_array( $cache ) ) {
			return null;
		}
		return isset( $cache[ $user_id ] ) && is_array( $cache[ $user_id ] ) ? $cache[ $user_id ] : null;
	}

	/**
	 * Persist the raw SMS lists payload for a `(blog_id, user_id)` pair.
	 *
	 * Other user_id entries already in the row are preserved.
	 *
	 * @param int   $blog_id   WP blog ID.
	 * @param int   $user_id   Newsman User ID.
	 * @param array $sms_lists Raw SMS lists payload (array of rows).
	 * @return void
	 */
	public static function save( $blog_id, $user_id, $sms_lists ) {
		if ( self::$skip ) {
			return;
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! is_array( $sms_lists ) ) {
			return;
		}
		$key   = self::transient_key( $blog_id );
		$cache = get_transient( $key );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		$cache[ $user_id ] = $sms_lists;
		set_transient( $key, $cache, self::TTL );

		// Persistent fallback tier (no TTL).
		$fallback = get_option( self::fallback_option_key( $blog_id ), array() );
		if ( ! is_array( $fallback ) ) {
			$fallback = array();
		}
		$fallback[ $user_id ] = $sms_lists;
		update_option( self::fallback_option_key( $blog_id ), $fallback, false );
	}

	/**
	 * Read the persistent stale-fallback value for `(blog_id, user_id)`.
	 *
	 * @param int $blog_id WP blog ID.
	 * @param int $user_id Newsman User ID.
	 * @return array|null Raw SMS lists payload, or null when no fallback exists.
	 */
	public static function get_stale( $blog_id, $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return null;
		}
		$fallback = get_option( self::fallback_option_key( $blog_id ), null );
		if ( ! is_array( $fallback ) ) {
			return null;
		}
		return isset( $fallback[ $user_id ] ) && is_array( $fallback[ $user_id ] ) ? $fallback[ $user_id ] : null;
	}

	/**
	 * Return the full per-blog cache.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array `[ user_id => raw_sms_lists ]`. Empty when the row is missing.
	 */
	public static function get_all( $blog_id ) {
		$cache = get_transient( self::transient_key( $blog_id ) );
		return is_array( $cache ) ? $cache : array();
	}

	/**
	 * Drop the entire per-blog cache row (or one user_id entry if specified).
	 *
	 * @param int      $blog_id WP blog ID.
	 * @param int|null $user_id Optional. When set, deletes only this user's entry.
	 * @return void
	 */
	public static function invalidate( $blog_id, $user_id = null, $fresh_only = false ) {
		$key = self::transient_key( $blog_id );
		if ( null === $user_id ) {
			delete_transient( $key );
			if ( ! $fresh_only ) {
				delete_option( self::fallback_option_key( $blog_id ) );
			}
			return;
		}
		$cache = get_transient( $key );
		if ( is_array( $cache ) ) {
			unset( $cache[ (int) $user_id ] );
			if ( empty( $cache ) ) {
				delete_transient( $key );
			} else {
				set_transient( $key, $cache, self::TTL );
			}
		}
		if ( ! $fresh_only ) {
			$fallback = get_option( self::fallback_option_key( $blog_id ), null );
			if ( is_array( $fallback ) ) {
				unset( $fallback[ (int) $user_id ] );
				if ( empty( $fallback ) ) {
					delete_option( self::fallback_option_key( $blog_id ) );
				} else {
					update_option( self::fallback_option_key( $blog_id ), $fallback, false );
				}
			}
		}
	}

	/**
	 * Compose the per-blog transient key.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return string
	 */
	protected static function transient_key( $blog_id ) {
		return self::TRANSIENT_KEY_PREFIX . (int) $blog_id;
	}

	/**
	 * Compose the per-blog persistent-fallback option key.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return string
	 */
	protected static function fallback_option_key( $blog_id ) {
		return self::FALLBACK_OPTION_PREFIX . (int) $blog_id;
	}
}
