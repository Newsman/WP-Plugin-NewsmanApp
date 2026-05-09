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
 * Newsman Atomic Forms integration bootstrap.
 *
 * Wires the schema-injection, controls-injection, and submission-handling hooks for
 * the Atomic Forms architecture (Elementor 4.x). The legacy Form widget integration
 * lives in `\Newsman\Elementor\Integration` and is registered independently.
 *
 * Atomic Forms is gated behind two experiments (`e_atomic_elements` for core atomic
 * widgets and `e_pro_atomic_form` for Pro form widgets). Both default to ACTIVE in
 * Elementor 4.x but can be toggled off — `is_atomic_form_active()` checks both before
 * registering hooks.
 *
 * @class \Newsman\Elementor\AtomicFormIntegration
 */
class AtomicFormIntegration {
	/**
	 * Core Atomic Widgets experiment name.
	 */
	public const EXPERIMENT_ATOMIC_WIDGETS = 'e_atomic_elements';

	/**
	 * Pro Atomic Form experiment name.
	 */
	public const EXPERIMENT_ATOMIC_FORM = 'e_pro_atomic_form';

	/**
	 * Editor controls / schema injector.
	 *
	 * @var AtomicFormControls
	 */
	protected $controls;

	/**
	 * Submission processor.
	 *
	 * @var AtomicFormProcessor
	 */
	protected $processor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controls  = new AtomicFormControls();
		$this->processor = new AtomicFormProcessor();
	}

	/**
	 * Register all hooks for the Atomic Forms integration.
	 *
	 * The three filters/actions live under namespaces (`elementor/atomic-widgets/*` and
	 * `elementor_pro/atomic_forms/*`) that are only invoked by Elementor when its atomic
	 * subsystem is active — so registering them unconditionally is safe. The callbacks
	 * themselves do defensive `class_exists` checks before touching atomic-widget classes.
	 *
	 * We deliberately avoid gating registration on `class_exists` here: at the time this
	 * runs (`plugins_loaded` priority 20) Elementor's atomic-widget classes haven't been
	 * autoloaded yet, so any pre-flight check would falsely return "not loaded" and we'd
	 * skip registration even when Atomic Forms is fully active.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter(
			'elementor/atomic-widgets/props-schema',
			array( $this->controls, 'inject_props_schema' ),
			10,
			1
		);

		add_filter(
			'elementor/atomic-widgets/controls',
			array( $this->controls, 'inject_controls' ),
			10,
			2
		);

		add_action(
			'elementor_pro/atomic_forms/form_submitted',
			array( $this->processor, 'on_form_submitted' ),
			10,
			0
		);
	}

	/**
	 * Runtime check: whether Atomic Form is loaded, with both experiments active.
	 *
	 * NOT used to gate hook registration (see `init_hooks()`). Available to other code
	 * that needs to ask the question lazily — e.g., admin notices rendered on `admin_init`,
	 * by which time Elementor has fully bootstrapped and class autoloading is safe.
	 *
	 * @return bool
	 */
	public static function is_atomic_form_active() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Module' ) ) {
			return false;
		}
		if ( ! class_exists( '\ElementorPro\Modules\AtomicForm\Module' ) ) {
			return false;
		}

		$elementor = \Elementor\Plugin::instance();
		if ( ! isset( $elementor->experiments ) || ! is_object( $elementor->experiments ) ) {
			return false;
		}

		try {
			if ( ! $elementor->experiments->is_feature_active( self::EXPERIMENT_ATOMIC_WIDGETS ) ) {
				return false;
			}
			if ( ! $elementor->experiments->is_feature_active( self::EXPERIMENT_ATOMIC_FORM ) ) {
				return false;
			}
		} catch ( \Exception $e ) {
			return false;
		}

		return true;
	}
}
