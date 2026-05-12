<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman Gravity Forms integration bootstrap.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman Gravity Forms integration bootstrap.
 *
 * Wires the Form Settings sub-page and the front-end submission processor.
 * Assumes Gravity Forms is active — the caller must gate on
 * `defined( 'GF_MIN_WP_VERSION' )` (via `\Newsman\Util\GravityFormsExist::exist()`)
 * before instantiating.
 *
 * @class \Newsman\GravityForms\Integration
 */
class Integration {
	/**
	 * Form Settings sub-page renderer.
	 *
	 * @var FormPanel
	 */
	protected $form_panel;

	/**
	 * Submission processor.
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
	 * Register all hooks for the Gravity Forms integration.
	 *
	 * Three integration points: add the "Newsman" item to the Form Settings
	 * sidebar, render the sub-page when the subview matches, and process
	 * submissions on `gform_after_submission`. A fourth hook invalidates the
	 * Settings page dropdown cache whenever a form is saved in the builder.
	 *
	 * @return void
	 */
	public function init_hooks() {
		/**
		 * Filter whether the Gravity Forms integration should register its hooks.
		 *
		 * Return false to disable Newsman's Gravity Forms integration entirely
		 * (Form Settings page + submission processing) without uninstalling.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'newsman_gravity_forms_integration_enabled', true ) ) {
			return;
		}

		add_filter(
			'gform_form_settings_menu',
			array( $this->form_panel, 'add_menu_item' ),
			10,
			2
		);

		add_action(
			'gform_form_settings_page_' . FormPanel::SUBVIEW,
			array( $this->form_panel, 'render' )
		);

		add_action(
			'gform_after_submission',
			array( $this->form_processor, 'process' ),
			10,
			2
		);

		// Invalidate the Settings page form-dropdown cache when a form is saved in
		// the builder. The FormPanel save path also clears the transient on its
		// own POST handler — `gform_after_save_form` covers other save channels
		// (Gravity import, third-party automation, GFAPI calls).
		add_action( 'gform_after_save_form', array( $this, 'invalidate_form_dropdown_cache' ) );
	}

	/**
	 * Delete the cached newsletter-forms list for the current blog.
	 *
	 * @return void
	 */
	public function invalidate_form_dropdown_cache() {
		delete_transient( 'newsman_gravity_forms_newsletter_forms_' . get_current_blog_id() );
	}
}
