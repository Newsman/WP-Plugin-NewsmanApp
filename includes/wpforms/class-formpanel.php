<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman WPForms Settings panel.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\WPForms;

use Newsman\Subscribe\Lists;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings panel injector for the Newsman WPForms integration.
 *
 * Adds a "Newsman" section in the form-builder Settings panel with: enable
 * checkbox, list dropdown, email-field dropdown (any data field type), and a
 * checkbox list of every form field for properties.
 *
 * Settings are persisted under `$form_data['settings']` via WPForms' standard
 * save handler, which deserializes every input named `settings[*]` into that
 * array.
 *
 * @class \Newsman\WPForms\FormPanel
 */
class FormPanel {
	/**
	 * Section slug rendered inside the Settings panel.
	 */
	public const SECTION = 'newsman';

	/**
	 * Field types that never carry user-entered values; excluded from both the
	 * email-field dropdown and the property checkboxes.
	 *
	 * @var string[]
	 */
	protected const NON_DATA_TYPES = array(
		'divider',
		'pagebreak',
		'html',
		'content',
		'internal-information',
		'entry-preview',
		'captcha',
		'hcaptcha',
		'turnstile',
		'page-break',
	);

	/**
	 * Data field types eligible to act as the subscriber email.
	 *
	 * Any field that yields a single textual value works (email is the canonical
	 * choice but text/tel/url/number forms with custom validation are equally
	 * valid).
	 *
	 * @var string[]
	 */
	protected const EMAIL_CANDIDATE_TYPES = array(
		'email',
		'text',
		'tel',
		'phone',
		'url',
		'number',
		'number-slider',
		'hidden',
	);

	/**
	 * Inject the "Newsman" section into the Settings panel sidebar.
	 *
	 * Hooked on `wpforms_builder_settings_sections`.
	 *
	 * @param array $sections  Existing sections, keyed by slug.
	 * @param array $form_data Current form data (unused).
	 * @return array
	 */
	public function add_section( $sections, $form_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_array( $sections ) ) {
			return $sections;
		}
		$sections[ self::SECTION ] = esc_html__( 'Newsman', 'newsman' );
		return $sections;
	}

	/**
	 * Render the Newsman section content.
	 *
	 * Hooked on `wpforms_form_settings_panel_content`. The action fires once
	 * per panel render and is shared by every section, so each integration
	 * scopes its output to its own `wpforms-panel-content-section-<slug>` div.
	 *
	 * @param object $instance `WPForms_Builder_Panel_Settings` instance.
	 * @return void
	 */
	public function render( $instance ) {
		if ( ! is_object( $instance ) || ! isset( $instance->form_data ) ) {
			return;
		}
		$form_data = is_array( $instance->form_data ) ? $instance->form_data : array();

		$prop    = self::resolve_settings( $form_data );
		$choices = self::scan_field_choices( $form_data );

		$lists       = Lists::get_for_select( get_current_blog_id() );
		$list_select = array( '' => esc_html__( '— select a list —', 'newsman' ) );
		if ( is_array( $lists ) ) {
			foreach ( $lists as $list_id => $list_name ) {
				$list_select[ (string) $list_id ] = (string) $list_name;
			}
		}

		echo '<div class="wpforms-panel-content-section wpforms-panel-content-section-' . esc_attr( self::SECTION ) . '">';
		echo '<div class="wpforms-panel-content-section-title">';
		echo esc_html__( 'Newsman', 'newsman' );
		echo '</div>';

		wpforms_panel_field(
			'toggle',
			'settings',
			'newsman_enable',
			$form_data,
			esc_html__( 'Send to Newsman', 'newsman' ),
			array(
				'tooltip' => esc_html__( 'When enabled, every successful submission of this form is subscribed to the selected Newsman list.', 'newsman' ),
			)
		);

		if ( count( $list_select ) === 1 ) {
			echo '<p class="newsman-empty-lists"><em>';
			echo esc_html__( 'No Newsman lists are available. Configure your Newsman API key on the Newsman settings page first.', 'newsman' );
			echo '</em></p>';
		} else {
			wpforms_panel_field(
				'select',
				'settings',
				'newsman_list_id',
				$form_data,
				esc_html__( 'Newsman list', 'newsman' ),
				array(
					'options' => $list_select,
				)
			);
		}

		if ( empty( $choices ) ) {
			echo '<p class="newsman-empty-fields"><em>';
			echo esc_html__( 'Add a field to the form to enable Newsman.', 'newsman' );
			echo '</em></p>';
			echo '</div>';
			return;
		}

		$email_options = array();
		foreach ( $choices as $field_id => $choice ) {
			$email_options[ (string) $field_id ] = self::format_choice_label( $choice );
		}

		wpforms_panel_field(
			'select',
			'settings',
			'newsman_email_field',
			$form_data,
			esc_html__( 'Email field', 'newsman' ),
			array(
				'options' => $email_options,
				'tooltip' => esc_html__( 'The selected field provides the subscriber email. Any field type may be used (text, tel, url, email, …).', 'newsman' ),
			)
		);

		// Custom HTML for the per-field "send as property" checkbox list. The
		// inputs save under settings[newsman_send_fields][<id>] = "1" so the
		// final form_data shape is `[ field_id => '1', ... ]`.
		$send_fields    = isset( $prop['newsman_send_fields'] ) && is_array( $prop['newsman_send_fields'] )
			? $prop['newsman_send_fields']
			: array();
		$has_existing   = ! empty( $send_fields );
		$selected_email = isset( $prop['newsman_email_field'] ) ? (string) $prop['newsman_email_field'] : '';

		echo '<div class="wpforms-panel-field wpforms-panel-field-checkbox">';
		echo '<label>' . esc_html__( 'Send as properties', 'newsman' ) . '</label>';
		echo '<ul style="margin:0;padding:0;list-style:none;">';
		foreach ( $choices as $field_id => $choice ) {
			$is_email_field = ( (string) $field_id === $selected_email );
			$default_on     = $has_existing
				? ! empty( $send_fields[ (string) $field_id ] )
				: ! $is_email_field;
			$name           = sprintf( 'settings[newsman_send_fields][%s]', esc_attr( (string) $field_id ) );
			$id             = sprintf( 'wpforms-panel-field-newsman-send-%s', esc_attr( (string) $field_id ) );
			echo '<li>';
			echo '<label for="' . esc_attr( $id ) . '">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"';
			if ( $default_on ) {
				echo ' checked';
			}
			if ( $is_email_field ) {
				echo ' disabled';
			}
			echo ' /> ';
			echo esc_html( self::format_choice_label( $choice ) );
			if ( $is_email_field ) {
				echo ' <em>(' . esc_html__( 'used as email', 'newsman' ) . ')</em>';
			}
			echo '</label>';
			echo '</li>';
		}
		echo '</ul>';
		echo '<p class="note">' . esc_html__( 'Each checked field is sent to Newsman as a subscriber property keyed by the field label (or ID when no label is set).', 'newsman' ) . '</p>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Read the persisted Newsman settings for this form, with defaults.
	 *
	 * @param array $form_data Current form data.
	 * @return array
	 */
	public static function resolve_settings( $form_data ) {
		$settings = isset( $form_data['settings'] ) && is_array( $form_data['settings'] )
			? $form_data['settings']
			: array();
		return wp_parse_args(
			array(
				'newsman_enable'      => isset( $settings['newsman_enable'] ) ? $settings['newsman_enable'] : '',
				'newsman_list_id'     => isset( $settings['newsman_list_id'] ) ? $settings['newsman_list_id'] : '',
				'newsman_email_field' => isset( $settings['newsman_email_field'] ) ? $settings['newsman_email_field'] : '',
				'newsman_send_fields' => isset( $settings['newsman_send_fields'] ) && is_array( $settings['newsman_send_fields'] )
					? $settings['newsman_send_fields']
					: array(),
			),
			array(
				'newsman_enable'      => '',
				'newsman_list_id'     => '',
				'newsman_email_field' => '',
				'newsman_send_fields' => array(),
			)
		);
	}

	/**
	 * Build the field-id → metadata map shown in the panel.
	 *
	 * Excludes non-data field types (divider, pagebreak, html, etc.) and fields
	 * without an id. Result is keyed by field id (matching `$form_data['fields']`).
	 *
	 * @param array $form_data Current form data.
	 * @return array<int|string,array{id:string,type:string,label:string}>
	 */
	public static function scan_field_choices( $form_data ) {
		$choices = array();
		if ( empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
			return $choices;
		}

		foreach ( $form_data['fields'] as $field_id => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( in_array( $type, self::NON_DATA_TYPES, true ) ) {
				continue;
			}
			$id             = isset( $field['id'] ) ? (string) $field['id'] : (string) $field_id;
			$label          = isset( $field['label'] ) ? (string) $field['label'] : '';
			$choices[ $id ] = array(
				'id'    => $id,
				'type'  => $type,
				'label' => $label,
			);
		}

		/**
		 * Filter the list of field choices shown in the Newsman editor panel.
		 *
		 * @param array $choices   Each entry: `[ 'id' => string, 'type' => string, 'label' => string ]` keyed by id.
		 * @param array $form_data Current form data.
		 */
		$choices = apply_filters( 'newsman_wpforms_field_choices', $choices, $form_data );

		return is_array( $choices ) ? $choices : array();
	}

	/**
	 * Field types that look like an email holder, in priority order, used to
	 * pick the default selection on first render. The processor falls back to
	 * the first scanned field if none of these are present.
	 *
	 * @return string[]
	 */
	public static function email_candidate_types() {
		return self::EMAIL_CANDIDATE_TYPES;
	}

	/**
	 * Render a "label (type)" string for the field choice.
	 *
	 * @param array $choice Field choice.
	 * @return string
	 */
	protected static function format_choice_label( $choice ) {
		$label = isset( $choice['label'] ) ? trim( (string) $choice['label'] ) : '';
		$type  = isset( $choice['type'] ) ? (string) $choice['type'] : '';
		$id    = isset( $choice['id'] ) ? (string) $choice['id'] : '';
		if ( '' === $label ) {
			$label = sprintf(
				/* translators: %s: WPForms field id. */
				esc_html__( 'Field #%s', 'newsman' ),
				$id
			);
		}
		if ( '' === $type ) {
			return $label;
		}
		return sprintf( '%1$s (%2$s)', $label, $type );
	}
}
