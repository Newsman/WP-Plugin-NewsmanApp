<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman remarketing class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribe to Newsman newsletter form widget shortcode.
 * Add Newsman JS script that renders a form in page.
 * The form is identified by a hash generated in Newsman.app application interface.
 * Then the form is added in WordPress admin as a shortcode.
 * Example: [newsman_subscribe_widget formid="example-hash"]
 *
 * @class \Newsman\Form\Widget
 */
class Widget {
	/**
	 * Get class instance
	 *
	 * @return self Widget
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Widget();
		}

		return $instance;
	}

	/**
	 * Generate widget.
	 *
	 * @param array $attributes Attributes array.
	 * @return string
	 */
	public function generate( $attributes ) {

		if ( empty( $attributes ) || ! is_array( $attributes ) || ! array_key_exists( 'formid', $attributes ) ) {
			return '';
		}
		$attributes['formid'] = sanitize_text_field( $attributes['formid'] );
		$c                    = substr_count( $attributes['formid'], '-' );

		// Backwards compatible.
		if ( 2 === $c || '2' === $c ) {
			$html = '<div id="' . esc_attr( $attributes['formid'] ) . '"></div>';
			return apply_filters( 'newsman_subscribe_widget_html2', $html, array( 'attributes' => $attributes ) );
		} else {
			$attributes['formid'] = str_replace( 'nzm-container-', '', $attributes['formid'] );

			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			$html = '<script async src="https://retargeting.newsmanapp.com/js/embed-form.js" data-nzmform="' .
				esc_attr( $attributes['formid'] ) . '"></script>';
			return apply_filters( 'newsman_subscribe_widget_html', $html, array( 'attributes' => $attributes ) );
		}
	}
}
