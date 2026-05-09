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

		$element->start_controls_section(
			'section_newsman',
			array(
				'label' => esc_html__( 'Newsman', 'newsman' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$element->add_control(
			'newsman_enable',
			array(
				'label'        => esc_html__( 'Send to Newsman', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'newsman' ),
				'label_off'    => esc_html__( 'Off', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'When enabled, form submissions will subscribe the email field to the selected Newsman list with the marked fields as subscriber properties.', 'newsman' ),
			)
		);

		$element->add_control(
			'newsman_list_id',
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
			)
		);

		$element->end_controls_section();
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

		$repeater->add_control(
			'newsman_send_field',
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
			)
		);

		$repeater->add_control(
			'newsman_is_email',
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
			)
		);

		$new_controls = $repeater->get_controls();

		// Splice the new controls into the existing repeater fields, after `custom_id`.
		$new_order = array();
		foreach ( $control_data['fields'] as $key => $field ) {
			$new_order[ $key ] = $field;
			if ( isset( $field['name'] ) && 'custom_id' === $field['name'] ) {
				foreach ( $new_controls as $control_name => $control_def ) {
					$new_order[ $control_name ] = $control_def;
				}
			}
		}

		$control_data['fields'] = $new_order;
		$element->update_control( 'form_fields', $control_data );
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
