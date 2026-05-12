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
use Newsman\Subscribe\Segments;

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
	 * Default list — use `get_input_types()` (which applies `newsman_atomic_form_input_types`)
	 * for every runtime decision so integrators can extend coverage to custom widgets.
	 *
	 * @var string[]
	 */
	public const INPUT_TYPES = array( 'e-form-input', 'e-form-textarea', 'e-form-checkbox' );

	/**
	 * Resolve the atomic input widget types that carry per-field Newsman flags.
	 *
	 * @return string[]
	 */
	public static function get_input_types() {
		/**
		 * Filter the list of atomic input widget types that may carry Newsman flags.
		 *
		 * Add custom widget types here to make Newsman emit per-field controls and pick
		 * up `newsman_send_field` / `newsman_is_email` from their submitted values.
		 *
		 * @param string[] $types Default input widget types.
		 */
		$types = apply_filters( 'newsman_atomic_form_input_types', self::INPUT_TYPES );
		return is_array( $types ) ? array_values( array_filter( array_map( 'strval', $types ) ) ) : self::INPUT_TYPES;
	}

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

		// Mirror legacy Form widget visibility for Newsman List + Newsman Segment.
		// Atomic widget dependencies with `effect: hide` express the visibility
		// *condition*: the prop is visible WHEN the boolean over the terms matches.
		// Using AND-relation with two terms means: visible when Send to Newsman is ON
		// AND Newsletter form is OFF — i.e. hidden whenever either toggle disagrees.
		$list_segment_dependencies = null;
		if ( class_exists( '\Elementor\Modules\AtomicWidgets\PropDependencies\Manager' ) ) {
			$dep_manager = '\Elementor\Modules\AtomicWidgets\PropDependencies\Manager';

			$list_segment_dependencies = $dep_manager::make( $dep_manager::RELATION_AND )
				->where(
					array(
						'operator' => 'eq',
						'path'     => array( 'newsman_enable' ),
						'value'    => true,
						'effect'   => 'hide',
					)
				)
				->where(
					array(
						'operator' => 'ne',
						'path'     => array( 'newsman_newsletter_form' ),
						'value'    => true,
						'effect'   => 'hide',
					)
				)
				->get();
		}

		$list_prop    = $string::make()->default( '' );
		$segment_prop = $string::make()->default( '' );
		$optin_prop   = $string::make()->default( 'single' );
		if ( null !== $list_segment_dependencies ) {
			$list_prop    = $list_prop->set_dependencies( $list_segment_dependencies );
			$segment_prop = $segment_prop->set_dependencies( $list_segment_dependencies );
		}

		/**
		 * Filter the Newsman atomic prop definitions before they are merged into the schema.
		 *
		 * Keyed by prop name; each value is the Prop_Type instance Elementor expects.
		 * Set a key to null/false to skip injecting that prop.
		 *
		 * @param array $props  Newsman prop definitions.
		 * @param array $schema Existing atomic widgets schema (pre-merge).
		 */
		$newsman_props = apply_filters(
			'newsman_atomic_form_props',
			array(
				'newsman_enable'          => $boolean::make()->default( false ),
				'newsman_newsletter_form' => $boolean::make()->default( false ),
				'newsman_list_id'         => $list_prop,
				'newsman_segment_id'      => $segment_prop,
				'newsman_optin_mode'      => $optin_prop,
				'newsman_send_field'      => $boolean::make()->default( true ),
				'newsman_is_email'        => $boolean::make()->default( false ),
				'newsman_is_firstname'    => $boolean::make()->default( false ),
				'newsman_is_lastname'     => $boolean::make()->default( false ),
				'newsman_is_phone'        => $boolean::make()->default( false ),
			),
			$schema
		);

		if ( is_array( $newsman_props ) ) {
			foreach ( $newsman_props as $prop_name => $prop_type ) {
				if ( ! is_string( $prop_name ) || '' === $prop_name || empty( $prop_type ) ) {
					continue;
				}
				if ( ! isset( $schema[ $prop_name ] ) ) {
					$schema[ $prop_name ] = $prop_type;
				}
			}
		}

		/**
		 * Filter the atomic-widgets props schema after Newsman's props have been injected.
		 *
		 * Use this to register additional Newsman-related atomic props (e.g. a per-field
		 * property-key override) that your custom controls or processor extensions consume.
		 *
		 * @param array $schema Atomic widgets schema with Newsman props already merged in.
		 */
		$schema = apply_filters( 'newsman_atomic_form_props_schema', $schema );

		/**
		 * Fires after the Newsman atomic props have been merged into the schema.
		 *
		 * @param array $schema        Atomic widgets schema (post-merge).
		 * @param array $newsman_props Newsman prop definitions that were considered.
		 */
		do_action( 'newsman_atomic_form_props_schema_injected', $schema, $newsman_props );

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

		$type = (string) $widget::get_element_type();

		/**
		 * Filter the resolved element type used to decide which Newsman section to build.
		 *
		 * Override the detected type to coerce a custom atomic widget into being treated
		 * as a form or a form input.
		 *
		 * @param string $type     Resolved element type from `$widget::get_element_type()`.
		 * @param object $widget   Atomic widget instance.
		 * @param array  $controls Existing controls array.
		 */
		$type = (string) apply_filters( 'newsman_atomic_form_controls_widget_type', $type, $widget, $controls );

		$section = null;

		if ( self::FORM_TYPE === $type ) {
			$section = $this->build_form_section();
		} elseif ( in_array( $type, self::get_input_types(), true ) ) {
			$section = $this->build_field_section( $type );
		}

		/**
		 * Filter the Newsman section object built for this widget. Return `null` to skip
		 * appending any Newsman section to this widget's controls.
		 *
		 * @param object|null $section  Section instance, or null when no built-in section applies.
		 * @param string      $type     Resolved widget type.
		 * @param object      $widget   Atomic widget instance.
		 * @param array       $controls Existing controls array.
		 */
		$section = apply_filters( 'newsman_atomic_form_controls_section', $section, $type, $widget, $controls );

		if ( null !== $section ) {
			$controls[] = $section;

			/**
			 * Fires after the Newsman section has been appended to an atomic widget's controls.
			 *
			 * @param object $section  Section instance that was appended.
			 * @param string $type     Resolved widget type.
			 * @param object $widget   Atomic widget instance.
			 * @param array  $controls Controls array (already containing the appended section).
			 */
			do_action( 'newsman_atomic_form_controls_appended', $section, $type, $widget, $controls );
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

		/**
		 * Filter the form-level "Send to Newsman" Switch_Control.
		 *
		 * @param object $enable_control Switch_Control bound to `newsman_enable`.
		 */
		$enable_control = apply_filters( 'newsman_atomic_form_enable_control', $enable_control );

		$newsletter_form_control = $switch_class::bind_to( 'newsman_newsletter_form' )
			->set_label( esc_html__( 'Newsletter form', 'newsman' ) )
			->set_description( esc_html__( 'When on, the form uses the list and segment configured in Newsman - Sync; the per-form Newsman List and Newsman Segment values below are stored but ignored.', 'newsman' ) );

		/**
		 * Filter the form-level "Newsletter form" Switch_Control.
		 *
		 * @param object $newsletter_form_control Switch_Control bound to `newsman_newsletter_form`.
		 */
		$newsletter_form_control = apply_filters( 'newsman_atomic_form_newsletter_form_control', $newsletter_form_control );

		$list_options = $this->build_list_options();
		$list_control = $select_class::bind_to( 'newsman_list_id' )
			->set_label( esc_html__( 'Newsman List', 'newsman' ) )
			->set_description( esc_html__( 'List for this campaign-specific form. Ignored when "Newsletter form" is on (the Sync section list is used instead).', 'newsman' ) )
			->set_options( $list_options );

		/**
		 * Filter the form-level Newsman list Select_Control.
		 *
		 * @param object $list_control Select_Control bound to `newsman_list_id`.
		 * @param array  $list_options Options array passed to `set_options()`.
		 */
		$list_control = apply_filters( 'newsman_atomic_form_list_control', $list_control, $list_options );

		$segment_options = $this->build_segment_options();
		$segment_control = $select_class::bind_to( 'newsman_segment_id' )
			->set_label( esc_html__( 'Newsman Segment', 'newsman' ) )
			->set_description( esc_html__( 'Optional. Segments are list-scoped — each option is labeled "List name — Segment name". If the selected segment does not belong to the saved list, it is dropped at submit time.', 'newsman' ) )
			->set_options( $segment_options );

		/**
		 * Filter the form-level Newsman segment Select_Control.
		 *
		 * @param object $segment_control Select_Control bound to `newsman_segment_id`.
		 * @param array  $segment_options Options array passed to `set_options()`.
		 */
		$segment_control = apply_filters( 'newsman_atomic_form_segment_control', $segment_control, $segment_options );

		$optin_options = array(
			array(
				'label' => esc_html__( 'Single opt-in', 'newsman' ),
				'value' => 'single',
			),
			array(
				'label' => esc_html__( 'Double opt-in', 'newsman' ),
				'value' => 'double',
			),
		);
		$optin_control = $select_class::bind_to( 'newsman_optin_mode' )
			->set_label( esc_html__( 'Opt-in mode', 'newsman' ) )
			->set_options( $optin_options );

		/**
		 * Filter the form-level Newsman opt-in mode Select_Control.
		 *
		 * @param object $optin_control Select_Control bound to `newsman_optin_mode`.
		 * @param array  $optin_options Options array passed to `set_options()`.
		 */
		$optin_control = apply_filters( 'newsman_atomic_form_optin_mode_control', $optin_control, $optin_options );

		/**
		 * Filter the array of items composing the form-level Newsman section.
		 *
		 * Append or replace items here (e.g. inject a custom Newsman property-mapping control).
		 *
		 * @param array $items Controls to be passed to `Section::set_items()`.
		 */
		$items = apply_filters(
			'newsman_atomic_form_section_items',
			array(
				$enable_control,
				$newsletter_form_control,
				$list_control,
				$segment_control,
				$optin_control,
			)
		);
		if ( ! is_array( $items ) ) {
			$items = array( $enable_control, $newsletter_form_control, $list_control, $segment_control, $optin_control );
		}

		$section = $section_class::make()
			->set_label( esc_html__( 'Newsman', 'newsman' ) )
			->set_items( $items );

		/**
		 * Filter the built form-level Newsman section before it is returned to `inject_controls()`.
		 *
		 * @param object $section Section instance.
		 * @param array  $items   Items the section was built with.
		 */
		return apply_filters( 'newsman_atomic_form_section', $section, $items );
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

		/**
		 * Filter the per-field "Send to Newsman" Switch_Control.
		 *
		 * @param object $send_control Switch_Control bound to `newsman_send_field`.
		 * @param string $widget_type  Atomic input widget type the section is being built for.
		 */
		$send_control = apply_filters( 'newsman_atomic_form_send_field_control', $send_control, $widget_type );

		$items = array( $send_control );

		// Email/firstname/lastname markers only make sense for text-like inputs, not checkboxes.
		if ( 'e-form-checkbox' !== $widget_type ) {
			$is_email_control = $switch_class::bind_to( 'newsman_is_email' )
				->set_label( esc_html__( 'Use as Newsman email', 'newsman' ) );

			/**
			 * Filter the per-field "Use as Newsman email" Switch_Control.
			 *
			 * @param object $is_email_control Switch_Control bound to `newsman_is_email`.
			 * @param string $widget_type      Atomic input widget type.
			 */
			$is_email_control = apply_filters( 'newsman_atomic_form_is_email_control', $is_email_control, $widget_type );
			$items[]          = $is_email_control;

			$is_firstname_control = $switch_class::bind_to( 'newsman_is_firstname' )
				->set_label( esc_html__( 'Use as Newsman firstname', 'newsman' ) );

			/**
			 * Filter the per-field "Use as Newsman firstname" Switch_Control.
			 *
			 * @param object $is_firstname_control Switch_Control bound to `newsman_is_firstname`.
			 * @param string $widget_type          Atomic input widget type.
			 */
			$is_firstname_control = apply_filters( 'newsman_atomic_form_is_firstname_control', $is_firstname_control, $widget_type );
			$items[]              = $is_firstname_control;

			$is_lastname_control = $switch_class::bind_to( 'newsman_is_lastname' )
				->set_label( esc_html__( 'Use as Newsman lastname', 'newsman' ) );

			/**
			 * Filter the per-field "Use as Newsman lastname" Switch_Control.
			 *
			 * @param object $is_lastname_control Switch_Control bound to `newsman_is_lastname`.
			 * @param string $widget_type         Atomic input widget type.
			 */
			$is_lastname_control = apply_filters( 'newsman_atomic_form_is_lastname_control', $is_lastname_control, $widget_type );
			$items[]             = $is_lastname_control;

			$is_phone_control = $switch_class::bind_to( 'newsman_is_phone' )
				->set_label( esc_html__( 'Use as Newsman phone', 'newsman' ) );

			/**
			 * Filter the per-field "Use as Newsman phone" Switch_Control.
			 *
			 * @param object $is_phone_control Switch_Control bound to `newsman_is_phone`.
			 * @param string $widget_type      Atomic input widget type.
			 */
			$is_phone_control = apply_filters( 'newsman_atomic_form_is_phone_control', $is_phone_control, $widget_type );
			$items[]          = $is_phone_control;
		}

		/**
		 * Filter the array of items composing the per-field Newsman section.
		 *
		 * @param array  $items       Controls to be passed to `Section::set_items()`.
		 * @param string $widget_type Atomic input widget type.
		 */
		$items = apply_filters( 'newsman_atomic_form_field_section_items', $items, $widget_type );
		if ( ! is_array( $items ) ) {
			$items = array( $send_control );
		}

		$section = $section_class::make()
			->set_label( esc_html__( 'Newsman', 'newsman' ) )
			->set_items( $items );

		/**
		 * Filter the built per-field Newsman section before it is returned to `inject_controls()`.
		 *
		 * @param object $section     Section instance.
		 * @param string $widget_type Atomic input widget type.
		 * @param array  $items       Items the section was built with.
		 */
		return apply_filters( 'newsman_atomic_form_field_section', $section, $widget_type, $items );
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

		$lists = Lists::get_for_select( get_current_blog_id() );
		if ( is_array( $lists ) ) {
			foreach ( $lists as $id => $name ) {
				$options[] = array(
					'label' => (string) $name,
					'value' => (string) $id,
				);
			}
		}

		/**
		 * Filter the Newsman List Select_Control options array.
		 *
		 * Atomic Select_Control expects `[ [ 'label' => ..., 'value' => ... ], ... ]`.
		 * Reorder, prepend, or replace entries here.
		 *
		 * @param array $options Options array as built by this method.
		 * @param array $lists   Raw `[ id => name ]` map fetched from `Lists::get_for_select()`.
		 */
		$options = apply_filters( 'newsman_atomic_form_list_options', $options, $lists );

		return is_array( $options ) ? $options : array();
	}

	/**
	 * Build the SELECT options list for the Newsman Segment dropdown.
	 *
	 * Segments are list-scoped on the Newsman API side. Atomic Select_Control has no
	 * conditional-option support and no editor-side dynamic refetch, so we render a
	 * flat list of every segment across every list, with the list name prefixed in
	 * the label. The processor drops a segment that doesn't belong to the saved
	 * list at submit time (see `AtomicFormProcessor` + `Segments::belongs_to_list`).
	 *
	 * @return array
	 */
	protected function build_segment_options() {
		$options = array(
			array(
				'label' => esc_html__( '— none —', 'newsman' ),
				'value' => '',
			),
		);

		$lists      = Lists::get_for_select( get_current_blog_id() );
		$by_list    = Segments::get_by_list( get_current_blog_id() );
		$list_names = is_array( $lists ) ? $lists : array();

		foreach ( $by_list as $list_id => $segments_for_list ) {
			$list_label = isset( $list_names[ $list_id ] ) ? (string) $list_names[ $list_id ] : (string) $list_id;
			foreach ( $segments_for_list as $segment_id => $segment_name ) {
				$options[] = array(
					'label' => sprintf( '%1$s — %2$s', $list_label, (string) $segment_name ),
					'value' => (string) $segment_id,
				);
			}
		}

		/**
		 * Filter the Newsman Segment Select_Control options array.
		 *
		 * @param array $options Options array as built by this method.
		 * @param array $by_list Raw `[ list_id => [ segment_id => segment_name ] ]` map.
		 */
		$options = apply_filters( 'newsman_atomic_form_segment_options', $options, $by_list );

		return is_array( $options ) ? $options : array();
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
