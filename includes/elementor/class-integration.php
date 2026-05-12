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

namespace Newsman\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman Elementor Forms integration bootstrap.
 *
 * @class \Newsman\Elementor\Integration
 */
class Integration {
	/**
	 * Form controls injector (editor side).
	 *
	 * @var FormControls
	 */
	protected $form_controls;

	/**
	 * Form submission processor (front-end side).
	 *
	 * @var FormProcessor
	 */
	protected $form_processor;

	/**
	 * Atomic Forms (Elementor 4.x) sub-integration.
	 *
	 * @var AtomicFormIntegration
	 */
	protected $atomic_form_integration;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->form_controls           = new FormControls();
		$this->form_processor          = new FormProcessor();
		$this->atomic_form_integration = new AtomicFormIntegration();
	}

	/**
	 * Register all hooks for the Elementor Forms integration.
	 *
	 * Editor-side hooks add the per-form 'Newsman' section and the per-field switchers
	 * to the legacy Form widget. Front-end hook handles its submission. Atomic Forms
	 * (Elementor 4.x) is wired up separately via `AtomicFormIntegration::init_hooks()`,
	 * which is a no-op when the Atomic Form experiment is not active.
	 *
	 * @return void
	 */
	public function init_hooks() {
		/**
		 * Filter whether the legacy Elementor Form integration should register its hooks.
		 *
		 * Return false to disable Newsman's Form widget integration entirely (editor controls
		 * + submission processing). The Atomic Forms sub-integration is gated separately via
		 * `newsman_atomic_form_integration_enabled`.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( apply_filters( 'newsman_elementor_integration_enabled', true ) ) {
			add_action(
				'elementor/element/form/section_form_fields/after_section_end',
				array( $this->form_controls, 'add_newsman_section' ),
				10,
				2
			);

			add_action(
				'elementor/element/form/section_form_fields/before_section_end',
				array( $this->form_controls, 'inject_field_controls' ),
				100,
				2
			);

			add_action(
				'elementor/editor/footer',
				array( $this->form_controls, 'print_editor_segment_filter_script' )
			);

			add_action(
				'elementor_pro/forms/process',
				array( $this->form_processor, 'process' ),
				10,
				2
			);
		}

		$this->atomic_form_integration->init_hooks();

		// Invalidate the Settings page form-dropdown cache whenever an Elementor document
		// is saved — covers legacy Form and Atomic Form widgets in the same hook. Without
		// this, freshly-flagged newsletter forms only appear after the 5-minute transient
		// expires or after the Newsman settings page is saved.
		add_action( 'elementor/document/after_save', array( $this, 'invalidate_form_dropdown_cache' ) );
	}

	/**
	 * Delete the cached newsletter-forms list for the current blog.
	 *
	 * Bound to `elementor/document/after_save` so the Settings page rescans
	 * `_elementor_data` on the next render and picks up any newly-flagged form.
	 *
	 * @return void
	 */
	public function invalidate_form_dropdown_cache() {
		delete_transient( 'newsman_elementor_newsletter_forms_' . get_current_blog_id() );
	}
}
