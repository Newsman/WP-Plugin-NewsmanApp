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
 * Stores `[ list_id => [ segment_id => segment_name ] ]` for every list in a
 * per-blog WordPress transient. Populated atomically from a single
 * `segment.all?list_id=all` API call (see `Segments::fetch_all_from_api`).
 *
 * Supports the same OAuth bypass + stale-on-error semantics as `Lists_Transient`
 * — see that class's docblock for the rationale.
 *
 * @class \Newsman\Subscribe\Segments_Transient
 */
class Segments_Transient {
	/**
	 * Fresh-tier cache lifetime — 1 hour. Mirrors `Lists_Transient::TTL` since
	 * segments and lists are fetched together (one bulk API call each) on the
	 * Sync page Refresh.
	 */
	public const TTL = HOUR_IN_SECONDS;

	/**
	 * Per-blog transient key prefix. The full key is `<prefix><blog_id>`.
	 */
	public const TRANSIENT_KEY_PREFIX = 'newsman_segments_cache_';

	/**
	 * Per-blog wp_option key prefix for the persistent stale-fallback tier.
	 * Never expires explicitly — overwritten on every successful save, so it
	 * always holds the last-known-good value for this blog.
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
	 * Persist the complete per-blog segment map in one transient + option write.
	 *
	 * Authoritative: replaces both the fresh transient row and the persistent
	 * fallback option with `$by_list`. Empty / non-array list entries are dropped.
	 *
	 * @param int   $blog_id WP blog ID.
	 * @param array $by_list `[ list_id => [ segment_id => segment_name ] ]`.
	 * @return void
	 */
	public static function save_all( $blog_id, $by_list ) {
		if ( self::$skip ) {
			return;
		}
		if ( ! is_array( $by_list ) ) {
			return;
		}
		$clean = array();
		foreach ( $by_list as $list_id => $segments ) {
			$list_id = (string) $list_id;
			if ( '' === $list_id || ! is_array( $segments ) ) {
				continue;
			}
			$clean[ $list_id ] = $segments;
		}
		set_transient( self::transient_key( $blog_id ), $clean, self::TTL );
		update_option( self::fallback_option_key( $blog_id ), $clean, false );
	}

	/**
	 * Return the full per-blog cache. Empty array when the row is missing.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array `[ list_id => [ segment_id => segment_name ] ]`.
	 */
	public static function get_all( $blog_id ) {
		if ( self::$skip ) {
			return array();
		}
		$cache = get_transient( self::transient_key( $blog_id ) );
		return is_array( $cache ) ? $cache : array();
	}

	/**
	 * Drop the entire per-blog cache row (and its persistent fallback).
	 *
	 * @param int  $blog_id     WP blog ID.
	 * @param bool $fresh_only  When true, the persistent fallback is preserved.
	 * @return void
	 */
	public static function invalidate( $blog_id, $fresh_only = false ) {
		delete_transient( self::transient_key( $blog_id ) );
		if ( ! $fresh_only ) {
			delete_option( self::fallback_option_key( $blog_id ) );
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
