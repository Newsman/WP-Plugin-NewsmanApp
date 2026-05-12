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
 * Newsman segments are list-scoped: each segment belongs to exactly one list, and the
 * `segment.all` API endpoint returns segments only for a given list_id. To support the
 * "all segments, JS-filtered on list change" UX requested for the form-builder panels,
 * this helper fetches segments for every list returned by `Lists::get_for_select()` and
 * returns them keyed by list_id.
 *
 * @class \Newsman\Subscribe\Segments
 */
class Segments {
	/**
	 * Build the per-list segment dropdown options.
	 *
	 * Cache-read-only — does NOT iterate the API. Returns whatever is already
	 * cached for every list (fresh transient hits + persistent stale-fallback
	 * tier). Used by editor panels that want a starting snapshot at render
	 * time; live updates flow through the AJAX endpoint
	 * `wp_ajax_newsman_load_segments` which calls `Segments::get_for_list()`.
	 *
	 * Historical note: this method used to eagerly call `segment.all` once per
	 * list, which trips Newsman's 10/min rate limit on accounts with more than
	 * 10 lists. The current cache-read-only behavior is the lazy-load mitigation
	 * (see wp_segment_all_rate_limit memory note).
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array<string,array<string,string>> Map of `[ list_id => [ segment_id => segment_name ] ]`.
	 */
	public static function get_by_list( $blog_id ) {
		$config  = Config::init();
		$user_id = (int) $config->get_user_id( $blog_id );

		if ( $user_id <= 0 ) {
			/**
			 * Filter the Newsman segment options shown in the editor segment dropdown.
			 *
			 * @param array $options Map of `[ list_id => [ segment_id => segment_name ] ]`.
			 * @param int   $blog_id Current WP blog ID.
			 */
			return apply_filters( 'newsman_segments_by_list', array(), $blog_id );
		}

		// Fresh tier — whatever the read-through reads have populated so far.
		$fresh = Segments_Transient::get_all( $blog_id );
		// Persistent fallback tier — last-known-good for every list. We merge so
		// that fresh hits win over stale, but lists not yet visited still have
		// something to show.
		$stale_key = 'newsman_segments_fallback_' . (int) $blog_id;
		$stale     = get_option( $stale_key, array() );
		$merged    = is_array( $stale ) ? $stale : array();
		if ( is_array( $fresh ) ) {
			foreach ( $fresh as $list_id => $segments ) {
				$merged[ (string) $list_id ] = $segments;
			}
		}

		// Drop empty entries so the JS filter does not render a meaningless option group.
		$options = array();
		foreach ( $merged as $list_id => $segments ) {
			if ( is_array( $segments ) && ! empty( $segments ) ) {
				$options[ (string) $list_id ] = $segments;
			}
		}

		/** This filter is documented in this file */
		return apply_filters( 'newsman_segments_by_list', $options, $blog_id );
	}

	/**
	 * Lazy-load segments for a single Newsman list (the lazy-AJAX read-through path).
	 *
	 * Used by `wp_ajax_newsman_load_segments` to fetch segments on-demand when
	 * an admin switches the list dropdown in a form-builder panel. Avoids the
	 * eager all-lists pattern in `get_by_list()` that hit the `segment.all`
	 * 10/min rate limit on accounts with many lists.
	 *
	 * Read-through with stale-on-error: cache hit returns immediately; cache
	 * miss tries the API; API failure falls back to the persistent stale value
	 * if any.
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param string $list_id Newsman List ID.
	 * @return array `[ segment_id => segment_name ]`. Empty when no list_id,
	 *               no credentials, never cached, and API call fails.
	 */
	public static function get_for_list( $blog_id, $list_id ) {
		$list_id = (string) $list_id;
		if ( '' === $list_id ) {
			return array();
		}

		$config  = Config::init();
		$user_id = (int) $config->get_user_id( $blog_id );
		$api_key = (string) $config->get_api_key( $blog_id );

		if ( $user_id <= 0 || '' === $api_key ) {
			return array();
		}

		$cached = Segments_Transient::get( $blog_id, $list_id );
		if ( is_array( $cached ) ) {
			/**
			 * Filter the single-list segments returned by the lazy-load read-through.
			 *
			 * @param array  $segments `[ segment_id => name ]`.
			 * @param int    $blog_id  WP blog ID.
			 * @param string $list_id  Newsman list id.
			 */
			return apply_filters( 'newsman_segments_for_list', $cached, $blog_id, $list_id );
		}

		try {
			$segments = self::fetch_from_api( $blog_id, $user_id, $api_key, $list_id );
		} catch ( \Exception $e ) {
			Logger::init()->log_exception( $e );
			$stale = Segments_Transient::get_stale( $blog_id, $list_id );
			if ( is_array( $stale ) ) {
				/** This filter is documented in this method */
				return apply_filters( 'newsman_segments_for_list', $stale, $blog_id, $list_id );
			}
			/** This filter is documented in this method */
			return apply_filters( 'newsman_segments_for_list', array(), $blog_id, $list_id );
		}

		Segments_Transient::save( $blog_id, $list_id, $segments );
		/** This filter is documented in this method */
		return apply_filters( 'newsman_segments_for_list', $segments, $blog_id, $list_id );
	}

	/**
	 * Force-fetch the segments for one list from the Newsman API, bypassing the cache.
	 *
	 * Used by `Segments::get_by_list()` on a miss and by the Sync page Refresh
	 * button. Throws on API failure so the caller can decide whether to surface
	 * the error or fall back to the existing (stale) transient row.
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param int    $user_id Newsman User ID.
	 * @param string $api_key Newsman API key.
	 * @param string $list_id Newsman List ID.
	 * @return array `[ segment_id => segment_name ]`.
	 * @throws \Exception When the underlying GetSegmentAll service raises.
	 */
	public static function fetch_from_api( $blog_id, $user_id, $api_key, $list_id ) {
		$context = new EmailList();
		$context->set_user_id( $user_id )
			->set_api_key( $api_key )
			->set_list_id( (string) $list_id );

		$service = new GetSegmentAll();
		$service->set_blog_id( $blog_id );
		$result = $service->execute( $context );

		$segments = array();
		if ( is_array( $result ) ) {
			foreach ( $result as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = '';
				if ( isset( $row['segment_id'] ) ) {
					$id = (string) $row['segment_id'];
				} elseif ( isset( $row['id'] ) ) {
					$id = (string) $row['id'];
				}
				$name = $id;
				if ( isset( $row['segment_name'] ) ) {
					$name = (string) $row['segment_name'];
				} elseif ( isset( $row['name'] ) ) {
					$name = (string) $row['name'];
				}
				if ( '' !== $id ) {
					$segments[ $id ] = $name;
				}
			}
		}
		return $segments;
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
}
