<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman WPForms integration bootstrap.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\WPForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman WPForms integration bootstrap.
 *
 * Wires the form-builder Settings panel section and the front-end submission
 * processor. Assumes WPForms (Lite or Pro) is active — the caller must gate on
 * `defined( 'WPFORMS_VERSION' )` (via `\Newsman\Util\WPFormsExist::exist()`)
 * before instantiating.
 *
 * @class \Newsman\WPForms\Integration
 */
class Integration {
	/**
	 * Settings panel injector (form-builder side).
	 *
	 * @var FormPanel
	 */
	protected $form_panel;

	/**
	 * Submission processor (front-end side).
	 *
	 * @var FormProcessor
	 */
	protected $form_processor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->form_panel     = new FormPanel();
		$this->form_processor = new FormProcessor();
	}

	/**
	 * Register all hooks for the WPForms integration.
	 *
	 * Three integration points: register the "Newsman" sidebar section, render
	 * its content when the panel is being painted, and process submissions on
	 * `wpforms_process_complete`.
	 *
	 * @return void
	 */
	public function init_hooks() {
		/**
		 * Filter whether the WPForms integration should register its hooks.
		 *
		 * Return false to disable Newsman's WPForms integration entirely (builder
		 * controls + submission processing) without uninstalling the plugin.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'newsman_wpforms_integration_enabled', true ) ) {
			return;
		}

		add_filter(
			'wpforms_builder_settings_sections',
			array( $this->form_panel, 'add_section' ),
			10,
			2
		);

		add_action(
			'wpforms_form_settings_panel_content',
			array( $this->form_panel, 'render' ),
			10,
			1
		);

		add_action(
			'wpforms_process_complete',
			array( $this->form_processor, 'process' ),
			10,
			4
		);

		// Invalidate the Settings page form-dropdown cache whenever a WPForms form is
		// saved in the builder. Without this, freshly-flagged newsletter forms only
		// appear after the 5-minute transient expires or after Newsman settings save.
		add_action( 'wpforms_save_form', array( $this, 'invalidate_form_dropdown_cache' ) );
	}

	/**
	 * Delete the cached newsletter-forms list for the current blog.
	 *
	 * Bound to `wpforms_save_form` so the Settings page rescans
	 * the `wpforms` post-type JSON on the next render.
	 *
	 * @return void
	 */
	public function invalidate_form_dropdown_cache() {
		delete_transient( 'newsman_wpforms_newsletter_forms_' . get_current_blog_id() );
	}
}
