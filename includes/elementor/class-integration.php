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
			'elementor_pro/forms/process',
			array( $this->form_processor, 'process' ),
			10,
			2
		);

		$this->atomic_form_integration->init_hooks();
	}
}
