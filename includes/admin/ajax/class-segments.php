<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman admin-AJAX endpoint for lazy-loading per-list segments.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Admin\Ajax;

use Newsman\Subscribe\Segments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-AJAX endpoint that returns the Newsman segments for a single Newsman
 * list id.
 *
 * Used by the form-builder integrations (Contact Form 7, WPForms, Elementor
 * legacy Form widget, Gravity Forms) to avoid pre-loading segments for every
 * list at editor render time. Eagerly loading all lists' segments would trip
 * Newsman's `segment.all` 10-calls-per-minute rate limit on accounts with more
 * than ~10 lists (see the `wp_segment_all_rate_limit` memory note for the full
 * story).
 *
 * Routing: registered as `wp_ajax_newsman_load_segments`. JS callers POST
 * `{ list_id, _ajax_nonce }` and receive `{ list_id, segments: [...] }` or an
 * error envelope. Capability gated to `manage_options` so only admins can
 * trigger the underlying Newsman API call.
 *
 * @class \Newsman\Admin\Ajax\Segments
 */
class Segments_Endpoint {
	/**
	 * Nonce action — also used by form panels when localising the AJAX URL +
	 * nonce into JS via `wp_localize_script` / inline script.
	 */
	public const NONCE_ACTION = 'newsman_load_segments';

	/**
	 * Register the wp_ajax_* hook.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_newsman_load_segments', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Build a nonce that the form-panel JS can include in its AJAX request.
	 *
	 * @return string
	 */
	public static function create_nonce() {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Handle the AJAX request. Validates capability + nonce, then delegates
	 * to `Segments::get_for_list()` (the read-through lazy-load method with
	 * stale-on-error semantics).
	 *
	 * Responses are sent via `wp_send_json_*` and `exit` — typical admin-AJAX.
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => 'forbidden' ),
				403
			);
		}

		check_ajax_referer( self::NONCE_ACTION );

		$list_id = isset( $_POST['list_id'] ) ? sanitize_text_field( wp_unslash( $_POST['list_id'] ) ) : '';
		$blog_id = (int) get_current_blog_id();

		if ( '' === $list_id ) {
			wp_send_json_success(
				array(
					'list_id'  => '',
					'segments' => array(),
				)
			);
		}

		$segments = Segments::get_for_list( $blog_id, $list_id );

		wp_send_json_success(
			array(
				'list_id'  => $list_id,
				'segments' => is_array( $segments ) ? $segments : array(),
			)
		);
	}
}
