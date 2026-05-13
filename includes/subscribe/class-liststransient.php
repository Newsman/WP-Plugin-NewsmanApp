<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman per-blog Lists transient cache.
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
 * Per-blog transient cache for the Newsman list dropdown.
 *
 * Stores `[ user_id => [ list_id => list_name ] ]` keyed by Newsman User ID
 * inside a per-blog WordPress transient. Keying by user_id inside the per-blog
 * row scopes the cache by current credentials — if the admin reconfigures and
 * the User ID changes, the old user's entry stays untouched (and harmless)
 * while a fresh entry is built for the new credentials.
 *
 * TTL is 15 minutes. The cache is read-through: callers (`Lists::get_for_select`,
 * the Sync page Refresh button, etc.) ask `get()` first and only hit the Newsman
 * API on miss, then write the result with `save()`. On API failure, callers
 * are expected to leave the existing entry alone — `get()` will still return the
 * stale value until TTL expiry, which is the "stale-on-error" property the Sync
 * page Refresh button relies on.
 *
 * OAuth flow bypass: the OAuth handler calls `set_skip(true)` at the start of
 * its request, which makes `get()` always return null and `save()` a no-op for
 * the rest of the request. This prevents OAuth's partial / not-yet-persisted
 * credentials from poisoning the cache.
 *
 * @class \Newsman\Subscribe\Lists_Transient
 */
class Lists_Transient {
	/**
	 * Fresh-tier cache lifetime — 1 hour. Lists rarely change on the Newsman
	 * side (admins don't create/delete lists hourly) and the `list.all` endpoint
	 * is rate-limited, so a longer TTL absorbs almost all normal navigation.
	 * The Sync page Refresh button bypasses the TTL when an admin wants Newsman
	 * changes pulled immediately.
	 */
	public const TTL = HOUR_IN_SECONDS;

	/**
	 * Per-blog transient key prefix. The full key is `<prefix><blog_id>`.
	 */
	public const TRANSIENT_KEY_PREFIX = 'newsman_lists_cache_';

	/**
	 * Per-blog wp_option key prefix for the persistent stale-fallback tier.
	 * Never expires explicitly — overwritten on every successful save, so it
	 * always holds the last-known-good value for this `(blog_id, user_id)`.
	 */
	public const FALLBACK_OPTION_PREFIX = 'newsman_lists_fallback_';

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
	 * Read cached lists for a `(blog_id, user_id)` pair.
	 *
	 * @param int $blog_id WP blog ID.
	 * @param int $user_id Newsman User ID.
	 * @return array|null `[ list_id => list_name ]` on hit, null on miss / bypass.
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
	 * Persist lists for a `(blog_id, user_id)` pair, refreshing the per-blog row's TTL.
	 *
	 * Other user_id entries already in the row are preserved. The persistent
	 * stale-fallback tier (`FALLBACK_OPTION_PREFIX`) is written too so that on
	 * a future API failure we can serve the last-known-good value instead of
	 * returning empty.
	 *
	 * @param int   $blog_id WP blog ID.
	 * @param int   $user_id Newsman User ID.
	 * @param array $lists   `[ list_id => list_name ]`.
	 * @return void
	 */
	public static function save( $blog_id, $user_id, $lists ) {
		if ( self::$skip ) {
			return;
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! is_array( $lists ) ) {
			return;
		}
		$key   = self::transient_key( $blog_id );
		$cache = get_transient( $key );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		$cache[ $user_id ] = $lists;
		set_transient( $key, $cache, self::TTL );

		// Persistent fallback tier (no TTL). Mirrors the latest known value so
		// that a future API failure on a cache miss can still serve something.
		$fallback = get_option( self::fallback_option_key( $blog_id ), array() );
		if ( ! is_array( $fallback ) ) {
			$fallback = array();
		}
		$fallback[ $user_id ] = $lists;
		update_option( self::fallback_option_key( $blog_id ), $fallback, false );
	}

	/**
	 * Read the persistent stale-fallback value for `(blog_id, user_id)`.
	 *
	 * Used by callers when the fresh API call fails on a cache miss — better
	 * to return slightly old data with a warning than to return empty.
	 *
	 * @param int $blog_id WP blog ID.
	 * @param int $user_id Newsman User ID.
	 * @return array|null `[ list_id => list_name ]` or null when no fallback exists.
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
	 * Return the full per-blog cache. Useful for diagnostics + the Refresh button.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array `[ user_id => [ list_id => list_name ] ]`. Empty when the row is missing.
	 */
	public static function get_all( $blog_id ) {
		$cache = get_transient( self::transient_key( $blog_id ) );
		return is_array( $cache ) ? $cache : array();
	}

	/**
	 * Drop the entire per-blog cache row (or one user_id entry if specified).
	 *
	 * Clears BOTH the fresh transient tier and the persistent stale-fallback
	 * tier — pass `$fresh_only = true` to keep the fallback alive when you
	 * just want the next read to re-fetch from the API.
	 *
	 * @param int      $blog_id    WP blog ID.
	 * @param int|null $user_id    Optional. When set, deletes only this user's entry.
	 * @param bool     $fresh_only Optional. When true, only clears the fresh tier.
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
