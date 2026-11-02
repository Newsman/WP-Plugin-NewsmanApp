<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman last export per type.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remembers when NewsMAN last read each type of data from the store.
 * One option per blog, keyed by export type: [ type => [ 'time' => int, 'code' => string ] ].
 *
 * @class \Newsman\Export\LastRun
 */
class LastRun {
	/**
	 * Option that stores the last export times.
	 */
	public const OPTION_NAME = 'newsman_export_last_runs';

	/**
	 * Seconds between two writes for the same type. NewsMAN reads in pages,
	 * so this keeps one export from writing the option once per page.
	 */
	public const MIN_INTERVAL = 60;

	/**
	 * Export types and the retriever codes that belong to each.
	 *
	 * @var array
	 */
	protected $type_codes = array(
		'customers'   => array( 'customers' ),
		'subscribers' => array( 'subscribers', 'subscribers-wordpress-feed', 'subscribers-woocommerce-feed' ),
		'products'    => array( 'products-feed', 'products' ),
		'orders'      => array( 'orders' ),
	);

	/**
	 * Export types that exist only with WooCommerce.
	 *
	 * @var array
	 */
	protected $woocommerce_types = array( 'customers', 'products', 'orders' );

	/**
	 * Get the export types and their retriever codes.
	 *
	 * @return array
	 */
	public function get_type_codes() {
		return apply_filters( 'newsman_export_last_run_type_codes', $this->type_codes );
	}

	/**
	 * Get the export types available on this site.
	 *
	 * @param bool $has_woocommerce Whether WooCommerce is active.
	 * @return array
	 */
	public function get_types( $has_woocommerce ) {
		$types = array();
		foreach ( array_keys( $this->get_type_codes() ) as $type ) {
			if ( ! $has_woocommerce && in_array( $type, $this->woocommerce_types, true ) ) {
				continue;
			}
			$types[] = $type;
		}
		return $types;
	}

	/**
	 * Get the export type of a retriever code.
	 *
	 * @param string $code Retriever code.
	 * @return string|false
	 */
	public function get_type_by_code( $code ) {
		$code = strtolower( (string) $code );
		foreach ( $this->get_type_codes() as $type => $codes ) {
			if ( in_array( $code, (array) $codes, true ) ) {
				return $type;
			}
		}
		return false;
	}

	/**
	 * Record a successful export.
	 *
	 * @param string   $code    Retriever code.
	 * @param null|int $blog_id WP blog ID.
	 * @param null|int $time    Unix time, now by default.
	 * @return bool True when the option was written.
	 */
	public function record( $code, $blog_id = null, $time = null ) {
		$type = $this->get_type_by_code( $code );
		if ( false === $type ) {
			return false;
		}

		$time = ( null === $time ) ? time() : (int) $time;
		$runs = $this->get_all( $blog_id );
		if ( isset( $runs[ $type ]['time'] ) ) {
			$elapsed = $time - (int) $runs[ $type ]['time'];
			if ( $elapsed >= 0 && $elapsed < self::MIN_INTERVAL ) {
				return false;
			}
		}

		$runs[ $type ] = array(
			'time' => $time,
			'code' => strtolower( (string) $code ),
		);
		$this->save_all( $runs, $blog_id );
		return true;
	}

	/**
	 * Get every recorded export.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function get_all( $blog_id = null ) {
		if ( $this->is_other_blog( $blog_id ) ) {
			$runs = get_blog_option( (int) $blog_id, self::OPTION_NAME, array() );
		} else {
			$runs = get_option( self::OPTION_NAME, array() );
		}
		return is_array( $runs ) ? $runs : array();
	}

	/**
	 * Get the last export time of a type.
	 *
	 * @param string   $type    Export type.
	 * @param null|int $blog_id WP blog ID.
	 * @return int|null Unix time, null when never recorded.
	 */
	public function get_time( $type, $blog_id = null ) {
		$runs = $this->get_all( $blog_id );
		if ( empty( $runs[ $type ]['time'] ) ) {
			return null;
		}
		return (int) $runs[ $type ]['time'];
	}

	/**
	 * Store the recorded exports. The option is not autoloaded.
	 *
	 * @param array    $runs    Recorded exports.
	 * @param null|int $blog_id WP blog ID.
	 * @return void
	 */
	protected function save_all( $runs, $blog_id = null ) {
		$switched = false;
		if ( $this->is_other_blog( $blog_id ) ) {
			switch_to_blog( (int) $blog_id );
			$switched = true;
		}

		update_option( self::OPTION_NAME, $runs, false );

		if ( $switched ) {
			restore_current_blog();
		}
	}

	/**
	 * Whether the blog ID points to another site of a multisite network.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	protected function is_other_blog( $blog_id ) {
		return ! empty( $blog_id ) &&
			function_exists( 'is_multisite' ) && is_multisite() &&
			function_exists( 'switch_to_blog' ) &&
			(int) get_current_blog_id() !== (int) $blog_id;
	}
}
