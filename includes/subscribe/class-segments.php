<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman shared segment dropdown fetcher.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Subscribe;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Service\Configuration\GetSegmentAll;
use Newsman\Service\Context\Configuration\EmailList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared Newsman segment fetcher used by every form-source integration's editor UI.
 *
 * Newsman segments are list-scoped: each segment belongs to exactly one list. The
 * `segment.all` API endpoint supports `?list_id=all` which returns every segment
 * across every list in a single response (each row carries its own `list_id`).
 * This helper makes that one bulk call, caches the result by blog, and exposes a
 * single read-through accessor for the form-builder panels.
 *
 * @class \Newsman\Subscribe\Segments
 */
class Segments {
	/**
	 * Token sent to `segment.all` to request segments for every list in one call.
	 *
	 * @see https://kb.newsman.com/api/1.2/segment.all
	 */
	public const FETCH_ALL_TOKEN = 'all';

	/**
	 * Read-through accessor used by every form-builder panel.
	 *
	 * Fresh cache → return; cache miss → one `segment.all?list_id=all` call,
	 * cache the result, return; API failure → persistent stale fallback.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array<string,array<string,string>> Map of `[ list_id => [ segment_id => segment_name ] ]`.
	 */
	public static function get_by_list( $blog_id ) {
		$config  = Config::init();
		$user_id = (int) $config->get_user_id( $blog_id );
		$api_key = (string) $config->get_api_key( $blog_id );

		if ( $user_id <= 0 ) {
			/**
			 * Filter the Newsman segment options shown in the editor segment dropdown.
			 *
			 * @param array $options Map of `[ list_id => [ segment_id => segment_name ] ]`.
			 * @param int   $blog_id Current WP blog ID.
			 */
			return apply_filters( 'newsman_segments_by_list', array(), $blog_id );
		}

		$fresh = Segments_Transient::get_all( $blog_id );
		if ( ! empty( $fresh ) ) {
			/** This filter is documented in this file */
			return apply_filters( 'newsman_segments_by_list', self::drop_empty_lists( $fresh ), $blog_id );
		}

		if ( '' !== $api_key && ! Segments_Transient::is_skipped() ) {
			try {
				$by_list = self::fetch_all_from_api( $blog_id, $user_id, $api_key );
				Segments_Transient::save_all( $blog_id, $by_list );
				/** This filter is documented in this file */
				return apply_filters( 'newsman_segments_by_list', self::drop_empty_lists( $by_list ), $blog_id );
			} catch ( \Exception $e ) {
				Logger::init()->log_exception( $e );
				// Fall through to the persistent stale tier.
			}
		}

		$stale = get_option( 'newsman_segments_fallback_' . (int) $blog_id, array() );
		$stale = is_array( $stale ) ? self::drop_empty_lists( $stale ) : array();

		/** This filter is documented in this file */
		return apply_filters( 'newsman_segments_by_list', $stale, $blog_id );
	}

	/**
	 * Bulk-fetch every list's segments from the Newsman API in a single call.
	 *
	 * Used by `get_by_list()` on a cache miss and by the Sync page Refresh button.
	 * Throws on API failure so the caller can decide whether to surface the error
	 * or fall back to the existing (stale) cached value.
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param int    $user_id Newsman User ID.
	 * @param string $api_key Newsman API key.
	 * @return array<string,array<string,string>> `[ list_id => [ segment_id => segment_name ] ]`.
	 * @throws \Exception When the underlying GetSegmentAll service raises.
	 */
	public static function fetch_all_from_api( $blog_id, $user_id, $api_key ) {
		$context = new EmailList();
		$context->set_user_id( $user_id )
			->set_api_key( $api_key )
			->set_list_id( self::FETCH_ALL_TOKEN );

		$service = new GetSegmentAll();
		$service->set_blog_id( $blog_id );
		$result = $service->execute( $context );

		$by_list = array();
		if ( ! is_array( $result ) ) {
			return $by_list;
		}
		foreach ( $result as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$list_id = isset( $row['list_id'] ) ? (string) $row['list_id'] : '';
			if ( '' === $list_id ) {
				continue;
			}
			$seg_id = '';
			if ( isset( $row['segment_id'] ) ) {
				$seg_id = (string) $row['segment_id'];
			} elseif ( isset( $row['id'] ) ) {
				$seg_id = (string) $row['id'];
			}
			if ( '' === $seg_id ) {
				continue;
			}
			$seg_name = $seg_id;
			if ( isset( $row['segment_name'] ) ) {
				$seg_name = (string) $row['segment_name'];
			} elseif ( isset( $row['name'] ) ) {
				$seg_name = (string) $row['name'];
			}
			if ( ! isset( $by_list[ $list_id ] ) ) {
				$by_list[ $list_id ] = array();
			}
			$by_list[ $list_id ][ $seg_id ] = $seg_name;
		}
		return $by_list;
	}

	/**
	 * Whether a segment belongs to a list (used for server-side validation).
	 *
	 * @param int    $blog_id    WP blog ID.
	 * @param string $list_id    Newsman list ID.
	 * @param string $segment_id Newsman segment ID.
	 * @return bool
	 */
	public static function belongs_to_list( $blog_id, $list_id, $segment_id ) {
		$list_id    = (string) $list_id;
		$segment_id = (string) $segment_id;
		if ( '' === $list_id || '' === $segment_id ) {
			return false;
		}
		$by_list = self::get_by_list( $blog_id );
		return isset( $by_list[ $list_id ][ $segment_id ] );
	}

	/**
	 * Strip lists with no segments so the JS filter doesn't render meaningless option groups.
	 *
	 * @param array $by_list `[ list_id => [ segment_id => segment_name ] ]`.
	 * @return array
	 */
	protected static function drop_empty_lists( $by_list ) {
		$out = array();
		foreach ( $by_list as $list_id => $segments ) {
			if ( is_array( $segments ) && ! empty( $segments ) ) {
				$out[ (string) $list_id ] = $segments;
			}
		}
		return $out;
	}
}
