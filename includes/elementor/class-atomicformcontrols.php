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
 * Editor controls and props-schema injector for Newsman on Atomic Forms.
 *
 * Atomic Forms (Elementor 4.x) does not allow registering custom form actions due to a
 * hardcoded whitelist in `Action_Type::is_valid()`. To integrate with the same UX as
 * legacy Forms, this class instead:
 *
 *   1. Injects 4 props (newsman_enable, newsman_list_id, newsman_send_field, newsman_is_email)
 *      into the global atomic-widgets schema via `elementor/atomic-widgets/props-schema`.
 *   2. Adds a "Newsman" section to the editor panel of `e-form` (the Atomic_Form widget)
 *      and to each input widget (`e-form-input`, `e-form-textarea`, `e-form-checkbox`)
 *      via `elementor/atomic-widgets/controls`.
 *
 * Submission handling lives in `\Newsman\Elementor\AtomicFormProcessor`.
 *
 * @class \Newsman\Elementor\AtomicFormControls
 */
class AtomicFormControls {
	/**
	 * The Atomic_Form widget element type.
	 */
	public const FORM_TYPE = 'e-form';

	/**
	 * Atomic input widgets that may carry per-field Newsman flags.
	 *
	 * @var string[]
	 */
	public const INPUT_TYPES = array( 'e-form-input', 'e-form-textarea', 'e-form-checkbox' );

	/**
	 * Inject Newsman props into the global atomic-widgets schema.
	 *
	 * The atomic widgets schema is global; every atomic widget will technically expose
	 * these props, but only the form/input widgets render UI for them.
	 *
	 * @param array $schema Existing global atomic-widgets schema.
	 * @return array
	 */
	public function inject_props_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type' )
			|| ! class_exists( '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type' ) ) {
			return $schema;
		}

		$boolean = '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type';
		$string  = '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type';

		if ( ! isset( $schema['newsman_enable'] ) ) {
			$schema['newsman_enable'] = $boolean::make()->default( false );
		}
		if ( ! isset( $schema['newsman_list_id'] ) ) {
			$schema['newsman_list_id'] = $string::make()->default( '' );
		}
		if ( ! isset( $schema['newsman_send_field'] ) ) {
			$schema['newsman_send_field'] = $boolean::make()->default( true );
		}
		if ( ! isset( $schema['newsman_is_email'] ) ) {
			$schema['newsman_is_email'] = $boolean::make()->default( false );
		}

		return $schema;
	}

	/**
	 * Inject the Newsman editor section into atomic widgets.
	 *
	 * Hooked on `elementor/atomic-widgets/controls` (priority 10, args 2). Scopes injection
	 * by the widget's element type.
	 *
	 * @param array  $controls Existing controls (array of Section objects).
	 * @param object $widget   The atomic widget instance.
	 * @return array
	 */
	public function inject_controls( $controls, $widget ) {
		if ( ! is_array( $controls ) || ! is_object( $widget ) ) {
			return $controls;
		}

		if ( ! method_exists( $widget, 'get_element_type' ) ) {
			return $controls;
		}

		$type    = (string) $widget::get_element_type();
		$section = null;

		if ( self::FORM_TYPE === $type ) {
			$section = $this->build_form_section();
		} elseif ( in_array( $type, self::INPUT_TYPES, true ) ) {
			$section = $this->build_field_section( $type );
		}

		if ( null !== $section ) {
			$controls[] = $section;
		}

		return $controls;
	}

	/**
	 * Build the per-form Newsman section (switcher + list dropdown).
	 *
	 * @return object|null
	 */
	protected function build_form_section() {
		if ( ! $this->are_control_classes_available() ) {
			return null;
		}

		$section_class = '\Elementor\Modules\AtomicWidgets\Controls\Section';
		$switch_class  = '\Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control';
		$select_class  = '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control';

		$enable_control = $switch_class::bind_to( 'newsman_enable' )
			->set_label( esc_html__( 'Send to Newsman', 'newsman' ) );

		$list_options = $this->build_list_options();
		$list_control = $select_class::bind_to( 'newsman_list_id' )
			->set_label( esc_html__( 'Newsman List', 'newsman' ) )
			->set_options( $list_options );

		return $section_class::make()
			->set_label( esc_html__( 'Newsman', 'newsman' ) )
			->set_items(
				array(
					$enable_control,
					$list_control,
				)
			);
	}

	/**
	 * Build the per-field Newsman section (send + is-email switchers).
	 *
	 * @param string $widget_type Atomic widget element type.
	 * @return object|null
	 */
	protected function build_field_section( $widget_type ) {
		if ( ! $this->are_control_classes_available() ) {
			return null;
		}

		$section_class = '\Elementor\Modules\AtomicWidgets\Controls\Section';
		$switch_class  = '\Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control';

		$send_control = $switch_class::bind_to( 'newsman_send_field' )
			->set_label( esc_html__( 'Send to Newsman', 'newsman' ) );

		$items = array( $send_control );

		// Email-marker only makes sense for text-like inputs, not checkboxes.
		if ( 'e-form-checkbox' !== $widget_type ) {
			$items[] = $switch_class::bind_to( 'newsman_is_email' )
				->set_label( esc_html__( 'Use as Newsman email', 'newsman' ) );
		}

		return $section_class::make()
			->set_label( esc_html__( 'Newsman', 'newsman' ) )
			->set_items( $items );
	}

	/**
	 * Build the SELECT options list for the Newsman List dropdown.
	 *
	 * Atomic Select_Control expects `[ [ 'label' => ..., 'value' => ... ], ... ]`,
	 * which differs from the SELECT2 shape used by legacy forms.
	 *
	 * @return array
	 */
	protected function build_list_options() {
		$options = array(
			array(
				'label' => esc_html__( '-- select a list --', 'newsman' ),
				'value' => '',
			),
		);

		$lists = ( new FormControls() )->get_lists_for_select();
		if ( is_array( $lists ) ) {
			foreach ( $lists as $id => $name ) {
				$options[] = array(
					'label' => (string) $name,
					'value' => (string) $id,
				);
			}
		}

		return $options;
	}

	/**
	 * Check that all atomic-widgets control classes we depend on are loaded.
	 *
	 * @return bool
	 */
	protected function are_control_classes_available() {
		return class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Section' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control' );
	}
}
