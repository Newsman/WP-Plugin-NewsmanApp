<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman per-blog Segments transient cache.
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
 * Per-blog transient cache for the Newsman segment dropdowns.
 *
 * Stores `[ list_id => [ segment_id => segment_name ] ]` keyed by Newsman List
 * ID inside a per-blog WordPress transient. Segments are list-scoped on the
 * Newsman side (a segment id is only meaningful in the context of its parent
 * list), so the inner key is the list_id with no need for user_id scoping.
 *
 * TTL is 15 minutes. The cache is read-through and supports the same OAuth
 * bypass + stale-on-error semantics as `Lists_Transient` — see that class's
 * docblock for the rationale.
 *
 * @class \Newsman\Subscribe\Segments_Transient
 */
class Segments_Transient {
	/**
	 * Fresh-tier cache lifetime — 6 hours. Segments are list-scoped and
	 * `segment.all` is the rate-limited endpoint on the Newsman side (10 calls
	 * per minute). Bulk refreshing a non-trivial account therefore takes minutes
	 * of throttled work, so the longer TTL is critical to avoid hitting the
	 * wall during normal admin navigation. Combined with lazy per-list AJAX
	 * loading and the persistent stale-fallback tier (see `save_fallback()` /
	 * `get_stale()`), this keeps the segment dropdowns warm without pressure.
	 */
	public const TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Per-blog transient key prefix. The full key is `<prefix><blog_id>`.
	 */
	public const TRANSIENT_KEY_PREFIX = 'newsman_segments_cache_';

	/**
	 * Per-blog wp_option key prefix for the persistent stale-fallback tier.
	 * Never expires explicitly — overwritten on every successful save, so it
	 * always holds the last-known-good value for this `(blog_id, list_id)`.
	 */
	public const FALLBACK_OPTION_PREFIX = 'newsman_segments_fallback_';

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
	 * Read cached segments for a `(blog_id, list_id)` pair.
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param string $list_id Newsman List ID.
	 * @return array|null `[ segment_id => segment_name ]` on hit, null on miss / bypass.
	 */
	public static function get( $blog_id, $list_id ) {
		if ( self::$skip ) {
			return null;
		}
		$list_id = (string) $list_id;
		if ( '' === $list_id ) {
			return null;
		}
		$cache = get_transient( self::transient_key( $blog_id ) );
		if ( ! is_array( $cache ) ) {
			return null;
		}
		return isset( $cache[ $list_id ] ) && is_array( $cache[ $list_id ] ) ? $cache[ $list_id ] : null;
	}

	/**
	 * Persist segments for a `(blog_id, list_id)` pair, refreshing the per-blog row's TTL.
	 *
	 * Other list_id entries already in the row are preserved.
	 *
	 * @param int    $blog_id  WP blog ID.
	 * @param string $list_id  Newsman List ID.
	 * @param array  $segments `[ segment_id => segment_name ]`.
	 * @return void
	 */
	public static function save( $blog_id, $list_id, $segments ) {
		if ( self::$skip ) {
			return;
		}
		$list_id = (string) $list_id;
		if ( '' === $list_id || ! is_array( $segments ) ) {
			return;
		}
		$key   = self::transient_key( $blog_id );
		$cache = get_transient( $key );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		$cache[ $list_id ] = $segments;
		set_transient( $key, $cache, self::TTL );

		// Persistent fallback tier (no TTL). Mirrors the latest known value so
		// that a future API failure on a cache miss can still serve something.
		$fallback = get_option( self::fallback_option_key( $blog_id ), array() );
		if ( ! is_array( $fallback ) ) {
			$fallback = array();
		}
		$fallback[ $list_id ] = $segments;
		update_option( self::fallback_option_key( $blog_id ), $fallback, false );
	}

	/**
	 * Read the persistent stale-fallback value for `(blog_id, list_id)`.
	 *
	 * Used by callers when the fresh API call fails on a cache miss — better
	 * to return slightly old data with a warning than to return empty.
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param string $list_id Newsman List ID.
	 * @return array|null `[ segment_id => segment_name ]` or null when no fallback exists.
	 */
	public static function get_stale( $blog_id, $list_id ) {
		$list_id = (string) $list_id;
		if ( '' === $list_id ) {
			return null;
		}
		$fallback = get_option( self::fallback_option_key( $blog_id ), null );
		if ( ! is_array( $fallback ) ) {
			return null;
		}
		return isset( $fallback[ $list_id ] ) && is_array( $fallback[ $list_id ] ) ? $fallback[ $list_id ] : null;
	}

	/**
	 * Return the full per-blog cache. Useful for diagnostics + the Refresh button.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array `[ list_id => [ segment_id => segment_name ] ]`. Empty when the row is missing.
	 */
	public static function get_all( $blog_id ) {
		$cache = get_transient( self::transient_key( $blog_id ) );
		return is_array( $cache ) ? $cache : array();
	}

	/**
	 * Drop the entire per-blog cache row (or one list_id entry if specified).
	 *
	 * @param int         $blog_id WP blog ID.
	 * @param string|null $list_id Optional. When set, deletes only this list's
	 *                             entry (the row stays in place with remaining lists).
	 * @return void
	 */
	public static function invalidate( $blog_id, $list_id = null, $fresh_only = false ) {
		$key = self::transient_key( $blog_id );
		if ( null === $list_id ) {
			delete_transient( $key );
			if ( ! $fresh_only ) {
				delete_option( self::fallback_option_key( $blog_id ) );
			}
			return;
		}
		$cache = get_transient( $key );
		if ( is_array( $cache ) ) {
			unset( $cache[ (string) $list_id ] );
			if ( empty( $cache ) ) {
				delete_transient( $key );
			} else {
				set_transient( $key, $cache, self::TTL );
			}
		}
		if ( ! $fresh_only ) {
			$fallback = get_option( self::fallback_option_key( $blog_id ), null );
			if ( is_array( $fallback ) ) {
				unset( $fallback[ (string) $list_id ] );
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
