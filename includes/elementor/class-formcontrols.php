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

use Newsman\Subscribe\Lists;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor controls injector for the Newsman Elementor Forms integration.
 *
 * @class \Newsman\Elementor\FormControls
 */
class FormControls {
	/**
	 * Add a top-level "Newsman" section to the Form widget Content tab.
	 *
	 * Hooked on `elementor/element/form/section_form_fields/after_section_end`.
	 *
	 * @param \Elementor\Controls_Stack $element Elementor element being rendered.
	 * @param array                     $args    Section args (unused).
	 * @return void
	 */
	public function add_newsman_section( $element, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) ) {
			return;
		}

		/**
		 * Filter the slug used to register the Newsman section on the Form widget.
		 *
		 * @param string $slug    Default section slug.
		 * @param object $element Elementor element being rendered.
		 */
		$section_id = (string) apply_filters( 'newsman_form_section_id', 'section_newsman', $element );

		/**
		 * Filter the args passed to `start_controls_section()` for the Newsman section.
		 *
		 * @param array  $args    Section args (`label`, `tab`, ...).
		 * @param object $element Elementor element.
		 */
		$section_args = apply_filters(
			'newsman_form_section_args',
			array(
				'label' => esc_html__( 'Newsman', 'newsman' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			),
			$element
		);
		if ( ! is_array( $section_args ) ) {
			$section_args = array(
				'label' => esc_html__( 'Newsman', 'newsman' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			);
		}

		$element->start_controls_section( $section_id, $section_args );

		/**
		 * Filter the args passed to `add_control()` for the form-level `newsman_enable` switcher.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Elementor element.
		 */
		$enable_args = apply_filters(
			'newsman_form_enable_control_args',
			array(
				'label'        => esc_html__( 'Send to Newsman', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'newsman' ),
				'label_off'    => esc_html__( 'Off', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'When enabled, form submissions will subscribe the email field to the selected Newsman list with the marked fields as subscriber properties.', 'newsman' ),
			),
			$element
		);
		if ( is_array( $enable_args ) ) {
			$element->add_control( 'newsman_enable', $enable_args );
		}

		/**
		 * Filter the args passed to `add_control()` for the form-level `newsman_list_id` SELECT2.
		 *
		 * @param array  $args    Control args (note: `options` is already populated).
		 * @param object $element Elementor element.
		 */
		$list_args = apply_filters(
			'newsman_form_list_control_args',
			array(
				'label'       => esc_html__( 'Newsman List', 'newsman' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $this->get_lists_for_select(),
				'label_block' => true,
				'multiple'    => false,
				'condition'   => array(
					'newsman_enable' => 'yes',
				),
				'description' => esc_html__( 'Required when Newsman is enabled. Lists are fetched from your Newsman account and cached for 10 minutes.', 'newsman' ),
			),
			$element
		);
		if ( is_array( $list_args ) ) {
			$element->add_control( 'newsman_list_id', $list_args );
		}

		/**
		 * Filter the args passed to `add_control()` for the form-level `newsman_optin_mode` SELECT.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Elementor element.
		 */
		$optin_args = apply_filters(
			'newsman_form_optin_mode_control_args',
			array(
				'label'       => esc_html__( 'Opt-in mode', 'newsman' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array(
					'single' => esc_html__( 'Single opt-in', 'newsman' ),
					'double' => esc_html__( 'Double opt-in', 'newsman' ),
				),
				'default'     => 'single',
				'label_block' => true,
				'condition'   => array(
					'newsman_enable' => 'yes',
				),
				'description' => esc_html__( 'Single opt-in subscribes the user immediately. Double opt-in sends a confirmation email; the subscription is only completed once the user clicks the link.', 'newsman' ),
			),
			$element
		);
		if ( is_array( $optin_args ) ) {
			$element->add_control( 'newsman_optin_mode', $optin_args );
		}

		/**
		 * Fires inside the Newsman section between the built-in controls and `end_controls_section()`.
		 *
		 * Use this to append additional form-level Newsman controls (e.g. a property-mapping
		 * field). Call `$element->add_control(...)` inside the callback.
		 *
		 * @param object $element    Elementor element being rendered.
		 * @param string $section_id Section slug used in `start_controls_section()`.
		 */
		do_action( 'newsman_form_section_controls', $element, $section_id );

		$element->end_controls_section();

		/**
		 * Fires after the Newsman section has been closed.
		 *
		 * @param object $element    Elementor element.
		 * @param string $section_id Section slug used in `start_controls_section()`.
		 */
		do_action( 'newsman_form_section_after', $element, $section_id );
	}

	/**
	 * Inject per-field controls into the Form widget's `form_fields` repeater.
	 *
	 * Hooked on `elementor/element/form/section_form_fields/before_section_end`.
	 * Adds two switchers to each field: "Send to Newsman" and "Is email field".
	 *
	 * @param \Elementor\Controls_Stack $element Form widget element.
	 * @param array                     $args    Section args (unused).
	 * @return void
	 */
	public function inject_field_controls( $element, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\Repeater' ) ) {
			return;
		}

		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		$control_data     = $controls_manager->get_control_from_stack( $element->get_name(), 'form_fields' );
		if ( is_wp_error( $control_data ) ) {
			return;
		}

		$repeater = new \Elementor\Repeater();

		/**
		 * Filter the args passed to the repeater's `add_control()` for `newsman_send_field`.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Form widget element.
		 */
		$send_args = apply_filters(
			'newsman_form_field_send_control_args',
			array(
				'label'        => esc_html__( 'Send to Newsman', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'newsman' ),
				'label_off'    => esc_html__( 'Off', 'newsman' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( "Push this field's value as a subscriber property to Newsman (using the field's ID as the property key).", 'newsman' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_advanced_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			),
			$element
		);
		if ( is_array( $send_args ) ) {
			$repeater->add_control( 'newsman_send_field', $send_args );
		}

		/**
		 * Filter the args passed to the repeater's `add_control()` for `newsman_is_email`.
		 *
		 * @param array  $args    Control args (note: `conditions.terms` restricts visibility to email/text fields).
		 * @param object $element Form widget element.
		 */
		$is_email_args = apply_filters(
			'newsman_form_field_is_email_control_args',
			array(
				'label'        => esc_html__( 'Use as Newsman email', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'newsman' ),
				'label_off'    => esc_html__( 'No', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Mark this field as the email used to subscribe the user to the Newsman list.', 'newsman' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_advanced_tab',
				'tabs_wrapper' => 'form_fields_tabs',
				'conditions'   => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => 'in',
							'value'    => array( 'email', 'text' ),
						),
					),
				),
			),
			$element
		);
		if ( is_array( $is_email_args ) ) {
			$repeater->add_control( 'newsman_is_email', $is_email_args );
		}

		/**
		 * Fires after Newsman's built-in repeater controls have been added.
		 *
		 * Use this to append further per-field Newsman controls (e.g. a custom property
		 * key override). Call `$repeater->add_control(...)` inside the callback.
		 *
		 * @param \Elementor\Repeater $repeater Form-fields repeater being built up.
		 * @param object              $element  Form widget element.
		 */
		do_action( 'newsman_form_field_repeater_controls', $repeater, $element );

		$new_controls = $repeater->get_controls();

		/**
		 * Filter the per-field controls that will be spliced into the `form_fields` repeater.
		 *
		 * Keyed by control name. Remove or add entries here.
		 *
		 * @param array  $new_controls Per-field controls to splice in.
		 * @param object $element      Form widget element.
		 * @param array  $control_data Existing `form_fields` control data.
		 */
		$new_controls = apply_filters( 'newsman_form_field_new_controls', $new_controls, $element, $control_data );
		if ( ! is_array( $new_controls ) ) {
			$new_controls = array();
		}

		/**
		 * Filter the name of the existing repeater field after which the Newsman controls
		 * are spliced in. Defaults to `custom_id`.
		 *
		 * @param string $anchor  Field name to splice after.
		 * @param object $element Form widget element.
		 */
		$anchor = (string) apply_filters( 'newsman_form_field_splice_anchor', 'custom_id', $element );

		// Splice the new controls into the existing repeater fields, after the anchor.
		$new_order = array();
		foreach ( $control_data['fields'] as $key => $field ) {
			$new_order[ $key ] = $field;
			if ( isset( $field['name'] ) && $anchor === $field['name'] ) {
				foreach ( $new_controls as $control_name => $control_def ) {
					$new_order[ $control_name ] = $control_def;
				}
			}
		}

		/**
		 * Filter the final ordered `form_fields` array after Newsman's controls have been spliced in.
		 *
		 * @param array  $new_order    Final ordered repeater fields.
		 * @param array  $new_controls Newsman controls that were spliced in.
		 * @param object $element      Form widget element.
		 */
		$new_order = apply_filters( 'newsman_form_field_order', $new_order, $new_controls, $element );
		if ( ! is_array( $new_order ) ) {
			$new_order = $control_data['fields'];
		}

		$control_data['fields'] = $new_order;
		$element->update_control( 'form_fields', $control_data );

		/**
		 * Fires after the Form widget's `form_fields` repeater has been updated with Newsman controls.
		 *
		 * @param object $element      Form widget element.
		 * @param array  $control_data Updated control data (with `fields` already containing Newsman entries).
		 * @param array  $new_controls Newsman controls that were spliced in.
		 */
		do_action( 'newsman_form_field_controls_injected', $element, $control_data, $new_controls );
	}

	/**
	 * Build the Newsman list dropdown options as `[ id => name ]`.
	 *
	 * Thin pass-through to the shared `\Newsman\Subscribe\Lists::get_for_select()` helper
	 * so all form-source integrations share the same transient cache and filter surface.
	 *
	 * @return array
	 */
	public function get_lists_for_select() {
		return Lists::get_for_select( get_current_blog_id() );
	}
}
