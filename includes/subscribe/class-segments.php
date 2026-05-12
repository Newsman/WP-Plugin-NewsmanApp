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
	 * Transient TTL for the segment dropdown cache (10 minutes).
	 */
	public const CACHE_TTL = 600;

	/**
	 * Transient key prefix for the cached segment dropdown options.
	 */
	public const CACHE_KEY_PREFIX = 'newsman_segments_';

	/**
	 * Build the per-list segment dropdown options.
	 *
	 * @param int $blog_id WP blog ID.
	 * @return array<string,array<string,string>> Map of `[ list_id => [ segment_id => segment_name ] ]`.
	 */
	public static function get_by_list( $blog_id ) {
		$transient_key = self::CACHE_KEY_PREFIX . $blog_id;

		$cached = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			/**
			 * Filter the Newsman segment options shown in the editor segment dropdown.
			 *
			 * @param array $options Map of `[ list_id => [ segment_id => segment_name ] ]`.
			 * @param int   $blog_id Current WP blog ID.
			 */
			return apply_filters( 'newsman_segments_by_list', $cached, $blog_id );
		}

		$config  = Config::init();
		$user_id = $config->get_user_id( $blog_id );
		$api_key = $config->get_api_key( $blog_id );

		if ( empty( $user_id ) || empty( $api_key ) ) {
			/** This filter is documented in this file */
			return apply_filters( 'newsman_segments_by_list', array(), $blog_id );
		}

		$lists = Lists::get_for_select( $blog_id );
		if ( empty( $lists ) ) {
			/** This filter is documented in this file */
			return apply_filters( 'newsman_segments_by_list', array(), $blog_id );
		}

		$options = array();
		foreach ( array_keys( $lists ) as $list_id ) {
			$list_id = (string) $list_id;
			if ( '' === $list_id ) {
				continue;
			}
			try {
				$context = new EmailList();
				$context->set_user_id( $user_id )
					->set_api_key( $api_key )
					->set_list_id( $list_id );

				$service = new GetSegmentAll();
				$service->set_blog_id( $blog_id );
				$result = $service->execute( $context );

				if ( ! is_array( $result ) ) {
					continue;
				}

				$segments_for_list = array();
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
						$segments_for_list[ $id ] = $name;
					}
				}

				if ( ! empty( $segments_for_list ) ) {
					$options[ $list_id ] = $segments_for_list;
				}
			} catch ( \Exception $e ) {
				Logger::init()->log_exception( $e );
				continue;
			}
		}

		/**
		 * Filter the segment dropdown cache TTL (in seconds).
		 *
		 * @param int $ttl     Cache TTL in seconds.
		 * @param int $blog_id Current WP blog ID.
		 */
		$ttl = (int) apply_filters( 'newsman_segments_cache_ttl', self::CACHE_TTL, $blog_id );
		set_transient( $transient_key, $options, $ttl );

		/** This filter is documented in this file */
		return apply_filters( 'newsman_segments_by_list', $options, $blog_id );
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
