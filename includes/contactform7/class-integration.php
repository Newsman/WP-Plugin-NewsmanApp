<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman Contact Form 7 integration bootstrap.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\ContactForm7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman Contact Form 7 integration bootstrap.
 *
 * Wires the editor panel (per-form list selection + per-field email/property markers) and
 * the front-end submission processor. Assumes Contact Form 7 is active — the caller must
 * gate on `class_exists( 'WPCF7_ContactForm' )` before instantiating.
 *
 * @class \Newsman\ContactForm7\Integration
 */
class Integration {
	/**
	 * Editor panel injector.
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
	 * Register all hooks for the Contact Form 7 integration.
	 *
	 * Three integration points: register the `newsman` property (so `set_properties()`
	 * persists it), inject the editor panel + save handler, and process submissions on
	 * `wpcf7_before_send_mail`.
	 *
	 * @return void
	 */
	public function init_hooks() {
		/**
		 * Filter whether the Contact Form 7 integration should register its hooks.
		 *
		 * Return false to disable Newsman's CF7 integration entirely (editor panel +
		 * submission processing) without uninstalling the plugin.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'newsman_cf7_integration_enabled', true ) ) {
			return;
		}

		add_filter(
			'wpcf7_pre_construct_contact_form_properties',
			array( $this->form_panel, 'register_property' ),
			10,
			2
		);

		add_filter(
			'wpcf7_editor_panels',
			array( $this->form_panel, 'add_panel' ),
			10,
			1
		);

		add_action(
			'wpcf7_save_contact_form',
			array( $this->form_panel, 'save' ),
			10,
			3
		);

		add_action(
			'wpcf7_before_send_mail',
			array( $this->form_processor, 'process' ),
			10,
			3
		);
	}
}
