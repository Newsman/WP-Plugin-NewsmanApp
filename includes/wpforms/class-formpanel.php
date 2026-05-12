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
use Newsman\Subscribe\Segments;

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

		/**
		 * Filter the slug used to register the Newsman section in the WPForms Settings panel sidebar.
		 *
		 * @param string $slug      Default section slug.
		 * @param array  $sections  Existing sections.
		 * @param array  $form_data Current form data.
		 */
		$slug = apply_filters( 'newsman_wpforms_section_slug', self::SECTION, $sections, $form_data );

		/**
		 * Filter the human-readable label of the Newsman section in the WPForms Settings panel sidebar.
		 *
		 * @param string $label     Default section label.
		 * @param array  $sections  Existing sections.
		 * @param array  $form_data Current form data.
		 */
		$label = apply_filters(
			'newsman_wpforms_section_label',
			esc_html__( 'Newsman', 'newsman' ),
			$sections,
			$form_data
		);

		$sections[ (string) $slug ] = (string) $label;
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

		/**
		 * Filter the resolved Newsman settings array used to render the WPForms panel.
		 *
		 * @param array  $prop      Resolved settings (`newsman_enable`, `newsman_list_id`, ...).
		 * @param object $instance  `WPForms_Builder_Panel_Settings` instance.
		 * @param array  $form_data Current form data.
		 * @param array  $choices   Scanned field choices.
		 * @param array  $lists     Newsman list dropdown options.
		 */
		$prop = apply_filters( 'newsman_wpforms_panel_prop', $prop, $instance, $form_data, $choices, $lists );
		if ( ! is_array( $prop ) ) {
			$prop = self::resolve_settings( array() );
		}

		/**
		 * Filter the Newsman list dropdown options (`[ '' => '— select a list —', '<id>' => '<name>', ... ]`).
		 *
		 * @param array  $list_select Dropdown options keyed by list id.
		 * @param array  $lists       Raw list map from `Lists::get_for_select()`.
		 * @param object $instance    `WPForms_Builder_Panel_Settings` instance.
		 * @param array  $form_data   Current form data.
		 * @param array  $prop        Resolved settings.
		 */
		$list_select = apply_filters( 'newsman_wpforms_panel_list_select', $list_select, $lists, $instance, $form_data, $prop );
		if ( ! is_array( $list_select ) ) {
			$list_select = array( '' => esc_html__( '— select a list —', 'newsman' ) );
		}

		/**
		 * Fires before the Newsman WPForms Settings section renders.
		 *
		 * Third-party code may echo a fully custom section here, then short-circuit the
		 * default markup via the `newsman_wpforms_panel_skip_default` filter.
		 * Do NOT collect HTML — echo directly inside the callback.
		 *
		 * @param object $instance    `WPForms_Builder_Panel_Settings` instance.
		 * @param array  $form_data   Current form data.
		 * @param array  $prop        Resolved settings.
		 * @param array  $choices     Field choices.
		 * @param array  $list_select Newsman list dropdown options.
		 */
		do_action( 'newsman_wpforms_panel_render', $instance, $form_data, $prop, $choices, $list_select );

		/**
		 * Suppress the default WPForms Newsman section HTML.
		 *
		 * @param bool   $skip        Default false.
		 * @param object $instance    `WPForms_Builder_Panel_Settings` instance.
		 * @param array  $form_data   Current form data.
		 * @param array  $prop        Resolved settings.
		 * @param array  $choices     Field choices.
		 * @param array  $list_select Newsman list dropdown options.
		 */
		if ( apply_filters( 'newsman_wpforms_panel_skip_default', false, $instance, $form_data, $prop, $choices, $list_select ) ) {
			return;
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

		wpforms_panel_field(
			'toggle',
			'settings',
			'newsman_newsletter_form',
			$form_data,
			esc_html__( 'Newsletter form', 'newsman' ),
			array(
				'tooltip' => esc_html__( 'When on, submissions go to the list and segment configured in Newsman - Sync (the per-form list and segment below are hidden because they no longer apply).', 'newsman' ),
			)
		);

		// Wrap list + segment rows so the inline JS below can toggle their visibility
		// when "Newsletter form" is on.
		echo '<div class="newsman-list-segment-wrap">';

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
					'tooltip' => esc_html__( 'List for this campaign-specific form. Ignored when "Newsletter form" is on (the configured Sync list is used instead).', 'newsman' ) . ' ' . esc_html__( 'Do not pick the same list as the one configured in Newsman - Sync unless you intend submissions to land in your global newsletter list. To send to that list, turn on "Newsletter form" instead.', 'newsman' ),
				)
			);

			// Segments — lazy-load only the currently-saved list's segments at
			// render time, then swap them via AJAX when the admin changes the
			// list dropdown. This avoids the eager all-lists fetch that tripped
			// Newsman's 10-calls-per-minute `segment.all` rate limit on accounts
			// with many lists.
			$current_list_id    = isset( $prop['newsman_list_id'] ) ? (string) $prop['newsman_list_id'] : '';
			$current_segment_id = isset( $prop['newsman_segment_id'] ) ? (string) $prop['newsman_segment_id'] : '';
			$segment_select     = array( '' => esc_html__( '— none —', 'newsman' ) );
			if ( '' !== $current_list_id ) {
				$segments_for_list = Segments::get_for_list( get_current_blog_id(), $current_list_id );
				foreach ( $segments_for_list as $segment_id => $segment_name ) {
					$segment_select[ (string) $segment_id ] = (string) $segment_name;
				}
			}

			wpforms_panel_field(
				'select',
				'settings',
				'newsman_segment_id',
				$form_data,
				esc_html__( 'Newsman segment', 'newsman' ),
				array(
					'options' => $segment_select,
					'tooltip' => esc_html__( 'Optional. Segments are list-scoped — only segments belonging to the selected list are shown. Switching the list refreshes this dropdown.', 'newsman' ),
				)
			);

			$ajax_url   = esc_url_raw( admin_url( 'admin-ajax.php' ) );
			$ajax_nonce = \Newsman\Admin\Ajax\Segments_Endpoint::create_nonce();
			$none_label = esc_html__( '— none —', 'newsman' );

			echo '<script>(function($){' .
				'var ajaxUrl = ' . wp_json_encode( $ajax_url ) . ';' .
				'var ajaxNonce = ' . wp_json_encode( $ajax_nonce ) . ';' .
				'var noneLabel = ' . wp_json_encode( $none_label ) . ';' .
				'var currentSaved = ' . wp_json_encode( $current_segment_id ) . ';' .
				'function reloadSegments(){' .
				'  var $list = $("#wpforms-panel-field-settings-newsman_list_id");' .
				'  var $seg  = $("#wpforms-panel-field-settings-newsman_segment_id");' .
				'  if ( ! $list.length || ! $seg.length ) return;' .
				'  var currentList = String($list.val() || "");' .
				'  if ( "" === currentList ) {' .
				'    $seg.empty().append($("<option/>").val("").text(noneLabel)).val("").trigger("change");' .
				'    return;' .
				'  }' .
				'  $seg.prop("disabled", true);' .
				'  $.post(ajaxUrl, { action: "newsman_load_segments", list_id: currentList, _ajax_nonce: ajaxNonce })' .
				'    .always(function(){ $seg.prop("disabled", false); })' .
				'    .done(function(resp){' .
				'      if ( ! resp || ! resp.success ) return;' .
				'      var segments = resp.data && resp.data.segments ? resp.data.segments : {};' .
				'      var selectedVal = String($seg.val() || "");' .
				'      $seg.empty().append($("<option/>").val("").text(noneLabel));' .
				'      Object.keys(segments).forEach(function(id){' .
				'        $seg.append($("<option/>").val(id).text(segments[id]));' .
				'      });' .
				'      var keep = (segments.hasOwnProperty(selectedVal)) ? selectedVal : ((segments.hasOwnProperty(currentSaved)) ? currentSaved : "");' .
				'      $seg.val(keep).trigger("change");' .
				'    });' .
				'}' .
				'function toggleNewsletterMode(){' .
				'  var $news = $("#wpforms-panel-field-settings-newsman_newsletter_form");' .
				'  var $wrap = $(".newsman-list-segment-wrap");' .
				'  if ( ! $news.length ) return;' .
				'  $wrap.toggle( ! $news.is(":checked") );' .
				'}' .
				'$(function(){' .
				'  $(document).on("change", "#wpforms-panel-field-settings-newsman_list_id", reloadSegments);' .
				'  $(document).on("change", "#wpforms-panel-field-settings-newsman_newsletter_form", toggleNewsletterMode);' .
				'  toggleNewsletterMode();' .
				'});' .
				'})(jQuery);</script>';
		}

		echo '</div>';

		wpforms_panel_field(
			'select',
			'settings',
			'newsman_optin_mode',
			$form_data,
			esc_html__( 'Opt-in mode', 'newsman' ),
			array(
				'options' => array(
					'single' => esc_html__( 'Single opt-in', 'newsman' ),
					'double' => esc_html__( 'Double opt-in', 'newsman' ),
				),
				'default' => 'single',
				'tooltip' => esc_html__( 'Single opt-in subscribes the user immediately. Double opt-in sends a confirmation email; the subscription is only completed once the user clicks the link.', 'newsman' ),
			)
		);

		if ( empty( $choices ) ) {
			echo '<p class="newsman-empty-fields"><em>';
			echo esc_html__( 'Add a field to the form to enable Newsman.', 'newsman' );
			echo '</em></p>';
			echo '</div>';

			/**
			 * Fires after the WPForms Newsman section finishes rendering its default HTML.
			 *
			 * Use this to append additional rows or notices below the standard section
			 * without replacing it. Echo directly inside the callback.
			 *
			 * @param object $instance    `WPForms_Builder_Panel_Settings` instance.
			 * @param array  $form_data   Current form data.
			 * @param array  $prop        Resolved settings.
			 * @param array  $choices     Field choices.
			 * @param array  $list_select Newsman list dropdown options.
			 */
			do_action( 'newsman_wpforms_panel_after_render', $instance, $form_data, $prop, $choices, $list_select );
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

		// Firstname/Lastname dropdowns: same option list as email plus a "— none —" entry.
		$name_options = array( '' => esc_html__( '— none —', 'newsman' ) );
		foreach ( $email_options as $field_id => $label ) {
			$name_options[ (string) $field_id ] = $label;
		}

		wpforms_panel_field(
			'select',
			'settings',
			'newsman_firstname_field',
			$form_data,
			esc_html__( 'Firstname field', 'newsman' ),
			array(
				'options' => $name_options,
				'tooltip' => esc_html__( 'Optional. When set, the field value is sent as the subscriber\'s firstname instead of being included in the subscriber properties.', 'newsman' ),
			)
		);

		wpforms_panel_field(
			'select',
			'settings',
			'newsman_lastname_field',
			$form_data,
			esc_html__( 'Lastname field', 'newsman' ),
			array(
				'options' => $name_options,
				'tooltip' => esc_html__( 'Optional. When set, the field value is sent as the subscriber\'s lastname instead of being included in the subscriber properties.', 'newsman' ),
			)
		);

		wpforms_panel_field(
			'select',
			'settings',
			'newsman_phone_field',
			$form_data,
			esc_html__( 'Phone field', 'newsman' ),
			array(
				'options' => $name_options,
				'tooltip' => esc_html__( 'Optional. When set, the field value is sent as the subscriber\'s phone under the `phone` property key.', 'newsman' ),
			)
		);

		// Custom HTML for the per-field "send as property" checkbox list. The
		// inputs save under settings[newsman_send_fields][<id>] = "1" so the
		// final form_data shape is `[ field_id => '1', ... ]`.
		$send_fields        = isset( $prop['newsman_send_fields'] ) && is_array( $prop['newsman_send_fields'] )
			? $prop['newsman_send_fields']
			: array();
		$has_existing       = ! empty( $send_fields );
		$selected_email     = isset( $prop['newsman_email_field'] ) ? (string) $prop['newsman_email_field'] : '';
		$selected_firstname = isset( $prop['newsman_firstname_field'] ) ? (string) $prop['newsman_firstname_field'] : '';
		$selected_lastname  = isset( $prop['newsman_lastname_field'] ) ? (string) $prop['newsman_lastname_field'] : '';
		$selected_phone     = isset( $prop['newsman_phone_field'] ) ? (string) $prop['newsman_phone_field'] : '';

		echo '<div class="wpforms-panel-field wpforms-panel-field-checkbox">';
		echo '<label>' . esc_html__( 'Send as properties', 'newsman' ) . '</label>';
		echo '<ul style="margin:0;padding:0;list-style:none;">';
		foreach ( $choices as $field_id => $choice ) {
			$is_email_field     = ( (string) $field_id === $selected_email );
			$is_firstname_field = ( '' !== $selected_firstname && (string) $field_id === $selected_firstname );
			$is_lastname_field  = ( '' !== $selected_lastname && (string) $field_id === $selected_lastname );
			$is_phone_field     = ( '' !== $selected_phone && (string) $field_id === $selected_phone );
			$is_reserved        = ( $is_email_field || $is_firstname_field || $is_lastname_field || $is_phone_field );
			$default_on         = $has_existing
				? ! empty( $send_fields[ (string) $field_id ] )
				: ! $is_reserved;
			$name               = sprintf( 'settings[newsman_send_fields][%s]', esc_attr( (string) $field_id ) );
			$id                 = sprintf( 'wpforms-panel-field-newsman-send-%s', esc_attr( (string) $field_id ) );
			echo '<li>';
			echo '<label for="' . esc_attr( $id ) . '">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"';
			if ( $default_on ) {
				echo ' checked';
			}
			if ( $is_reserved ) {
				echo ' disabled';
			}
			echo ' /> ';
			echo esc_html( self::format_choice_label( $choice ) );
			if ( $is_email_field ) {
				echo ' <em>(' . esc_html__( 'used as email', 'newsman' ) . ')</em>';
			} elseif ( $is_firstname_field ) {
				echo ' <em>(' . esc_html__( 'used as firstname', 'newsman' ) . ')</em>';
			} elseif ( $is_lastname_field ) {
				echo ' <em>(' . esc_html__( 'used as lastname', 'newsman' ) . ')</em>';
			} elseif ( $is_phone_field ) {
				echo ' <em>(' . esc_html__( 'used as phone', 'newsman' ) . ')</em>';
			}
			echo '</label>';
			echo '</li>';
		}
		echo '</ul>';
		echo '<p class="note">' . esc_html__( 'Each checked field is sent to Newsman as a subscriber property keyed by the field label (or ID when no label is set).', 'newsman' ) . '</p>';
		echo '</div>';

		echo '</div>';

		/** This action is documented in the early-return branch above. */
		do_action( 'newsman_wpforms_panel_after_render', $instance, $form_data, $prop, $choices, $list_select );
	}

	/**
	 * Read the persisted Newsman settings for this form, with defaults.
	 *
	 * @param array $form_data Current form data.
	 * @return array
	 */
	public static function resolve_settings( $form_data ) {
		$settings   = isset( $form_data['settings'] ) && is_array( $form_data['settings'] )
			? $form_data['settings']
			: array();
		$optin_mode = isset( $settings['newsman_optin_mode'] ) ? (string) $settings['newsman_optin_mode'] : 'single';
		if ( 'double' !== $optin_mode ) {
			$optin_mode = 'single';
		}

		return wp_parse_args(
			array(
				'newsman_enable'          => isset( $settings['newsman_enable'] ) ? $settings['newsman_enable'] : '',
				'newsman_newsletter_form' => isset( $settings['newsman_newsletter_form'] ) ? $settings['newsman_newsletter_form'] : '',
				'newsman_list_id'         => isset( $settings['newsman_list_id'] ) ? $settings['newsman_list_id'] : '',
				'newsman_segment_id'      => isset( $settings['newsman_segment_id'] ) ? $settings['newsman_segment_id'] : '',
				'newsman_optin_mode'      => $optin_mode,
				'newsman_email_field'     => isset( $settings['newsman_email_field'] ) ? $settings['newsman_email_field'] : '',
				'newsman_firstname_field' => isset( $settings['newsman_firstname_field'] ) ? $settings['newsman_firstname_field'] : '',
				'newsman_lastname_field'  => isset( $settings['newsman_lastname_field'] ) ? $settings['newsman_lastname_field'] : '',
				'newsman_phone_field'     => isset( $settings['newsman_phone_field'] ) ? $settings['newsman_phone_field'] : '',
				'newsman_send_fields'     => isset( $settings['newsman_send_fields'] ) && is_array( $settings['newsman_send_fields'] )
					? $settings['newsman_send_fields']
					: array(),
			),
			array(
				'newsman_enable'          => '',
				'newsman_newsletter_form' => '',
				'newsman_list_id'         => '',
				'newsman_segment_id'      => '',
				'newsman_optin_mode'      => 'single',
				'newsman_email_field'     => '',
				'newsman_firstname_field' => '',
				'newsman_lastname_field'  => '',
				'newsman_phone_field'     => '',
				'newsman_send_fields'     => array(),
			)
		);
	}

	/**
	 * Build the field-id - metadata map shown in the panel.
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
