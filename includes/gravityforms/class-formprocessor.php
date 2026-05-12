<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman Gravity Forms submission processor.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\GravityForms;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Subscribe\Helper as SubscribeHelper;
use Newsman\User\IpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side processor for Newsman-enabled Gravity Forms submissions.
 *
 * Hooked on `gform_after_submission` (fires after Gravity persists the entry
 * to `wp_gf_entry` / `wp_gf_entry_meta`). Reads the form's persisted Newsman
 * settings, pulls the email + property values out of `$entry`, and calls
 * `\Newsman\Subscribe\Helper`. The processor never blocks Gravity's own flow —
 * a Newsman API failure is logged but does not affect the form's confirmation,
 * notifications, or entry storage.
 *
 * @class \Newsman\GravityForms\FormProcessor
 */
class FormProcessor {
	/**
	 * Handle a Gravity Forms submission.
	 *
	 * @param array $entry Saved entry, keyed by field id (or dotted sub-input
	 *                     id for compound fields like Name/Address).
	 * @param array $form  Full form definition (carries the `newsman_*` keys).
	 * @return void
	 */
	public function process( $entry, $form ) {
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return;
		}

		$prop = FormPanel::resolve_settings( $form );
		if ( '1' !== (string) $prop['newsman_enable'] ) {
			return;
		}

		/**
		 * Filter whether to process this Newsman-enabled Gravity Forms submission.
		 *
		 * Return false to skip Newsman processing (e.g. honeypot/spam, conditional
		 * logic). The form's other actions (confirmations, notifications) still run.
		 *
		 * @param bool  $should Default true.
		 * @param array $entry  Saved entry.
		 * @param array $form   Form definition.
		 */
		if ( ! apply_filters( 'newsman_gravity_forms_should_process', true, $entry, $form ) ) {
			return;
		}

		$logger = Logger::init();
		$config = Config::init();

		$is_newsletter_form = '1' === (string) $prop['newsman_newsletter_form'];
		if ( $is_newsletter_form ) {
			$list_id    = trim( (string) $config->get_list_id( get_current_blog_id() ) );
			$segment_id = trim( (string) $config->get_segment_id( get_current_blog_id() ) );
			if ( '' === $list_id ) {
				$logger->error( esc_html__( 'Newsman: Gravity Forms form is marked as newsletter form but no list is configured in Newsman - Sync.', 'newsman' ) );
				return;
			}
		} else {
			$list_id    = isset( $prop['newsman_list_id'] ) ? trim( (string) $prop['newsman_list_id'] ) : '';
			$segment_id = isset( $prop['newsman_segment_id'] ) ? trim( (string) $prop['newsman_segment_id'] ) : '';
			if ( '' === $list_id ) {
				$logger->error( esc_html__( 'Newsman: Gravity Forms form is enabled but no list is selected.', 'newsman' ) );
				return;
			}
			if ( '' !== $segment_id && ! \Newsman\Subscribe\Segments::belongs_to_list( get_current_blog_id(), $list_id, $segment_id ) ) {
				$segment_id = '';
			}
		}

		$email_field_id = isset( $prop['newsman_email_field'] ) ? trim( (string) $prop['newsman_email_field'] ) : '';
		if ( '' === $email_field_id ) {
			$logger->error( esc_html__( 'Newsman: Gravity Forms form has no email field configured.', 'newsman' ) );
			return;
		}

		$email = self::flatten_value( self::entry_value( $entry, $email_field_id ) );
		$email = trim( (string) $email );

		/**
		 * Filter the email extracted from the Gravity Forms submission.
		 *
		 * @param string $email Resolved email value (post-trim).
		 * @param array  $entry Saved entry.
		 * @param array  $form  Form definition.
		 */
		$email = (string) apply_filters( 'newsman_gravity_forms_email', $email, $entry, $form );

		if ( '' === $email ) {
			$logger->error( esc_html__( 'Newsman: Gravity Forms email field is empty.', 'newsman' ) );
			return;
		}

		$firstname_field_id = isset( $prop['newsman_firstname_field'] ) ? trim( (string) $prop['newsman_firstname_field'] ) : '';
		$lastname_field_id  = isset( $prop['newsman_lastname_field'] ) ? trim( (string) $prop['newsman_lastname_field'] ) : '';
		$phone_field_id     = isset( $prop['newsman_phone_field'] ) ? trim( (string) $prop['newsman_phone_field'] ) : '';

		$firstname = '' !== $firstname_field_id ? trim( (string) self::flatten_value( self::entry_value( $entry, $firstname_field_id ) ) ) : '';
		$lastname  = '' !== $lastname_field_id ? trim( (string) self::flatten_value( self::entry_value( $entry, $lastname_field_id ) ) ) : '';
		$phone     = '' !== $phone_field_id ? trim( (string) self::flatten_value( self::entry_value( $entry, $phone_field_id ) ) ) : '';

		$send_fields = isset( $prop['newsman_send_fields'] ) && is_array( $prop['newsman_send_fields'] )
			? array_keys( $prop['newsman_send_fields'] )
			: array();

		$properties = array();
		foreach ( $send_fields as $field_id ) {
			$field_id = (string) $field_id;
			if ( '' === $field_id ) {
				continue;
			}
			// The four reserved fields go through their dedicated channels.
			if ( $field_id === $email_field_id
				|| ( '' !== $firstname_field_id && $field_id === $firstname_field_id )
				|| ( '' !== $lastname_field_id && $field_id === $lastname_field_id )
				|| ( '' !== $phone_field_id && $field_id === $phone_field_id )
			) {
				continue;
			}
			$value = self::entry_value( $entry, $field_id );
			if ( null === $value || '' === $value ) {
				continue;
			}
			$key                = self::property_key( $form, $field_id );
			$properties[ $key ] = self::format_value( $value );
		}

		if ( '' !== $phone ) {
			$properties['phone'] = $phone;
		}

		/**
		 * Filter the subscriber properties for the Gravity Forms submission.
		 *
		 * @param array  $properties Properties built from `send_fields`.
		 * @param array  $entry      Saved entry.
		 * @param array  $form       Form definition.
		 * @param string $email      Resolved email.
		 */
		$properties = apply_filters( 'newsman_gravity_forms_properties', $properties, $entry, $form, $email );

		$optin_mode = isset( $prop['newsman_optin_mode'] ) && 'double' === $prop['newsman_optin_mode'] ? 'double' : 'single';

		try {
			SubscribeHelper::subscribe_with_props(
				get_current_blog_id(),
				$list_id,
				$email,
				$properties,
				IpAddress::init()->get_ip(),
				$optin_mode,
				$firstname,
				$lastname,
				$segment_id
			);

			/**
			 * Fires after a Gravity Forms submission was successfully processed by Newsman.
			 *
			 * @param string $list_id    Newsman list ID.
			 * @param string $email      Subscribed email.
			 * @param array  $properties Properties pushed.
			 * @param array  $entry      Saved entry.
			 * @param array  $form       Form definition.
			 */
			do_action( 'newsman_gravity_forms_processed', $list_id, $email, $properties, $entry, $form );
		} catch ( \Exception $e ) {
			$logger->log_exception( $e );

			/**
			 * Fires when a Gravity Forms submission failed Newsman processing.
			 *
			 * @param \Exception $e          Caught exception.
			 * @param string     $list_id    Newsman list ID.
			 * @param string     $email      Resolved email.
			 * @param array      $properties Properties that would have been pushed.
			 * @param array      $entry      Saved entry.
			 * @param array      $form       Form definition.
			 */
			do_action( 'newsman_gravity_forms_process_failed', $e, $list_id, $email, $properties, $entry, $form );
		}
	}

	/**
	 * Pull a field's submitted value out of the entry array.
	 *
	 * Gravity Forms keys entries by the field id as a string (e.g. `'2'`) or a
	 * dotted sub-input id (e.g. `'1.3'`).
	 *
	 * @param array  $entry    Saved entry.
	 * @param string $field_id Field id (or sub-input id).
	 * @return mixed Returns null when the field is absent.
	 */
	protected static function entry_value( $entry, $field_id ) {
		if ( isset( $entry[ $field_id ] ) ) {
			return $entry[ $field_id ];
		}
		return null;
	}

	/**
	 * Resolve a property key for a given field id.
	 *
	 * Prefers the field's label (sanitized via `sanitize_key`) so Newsman
	 * properties stay readable; falls back to the field id when no label is set.
	 *
	 * @param array  $form     Form definition.
	 * @param string $field_id Field id (top-level or dotted sub-input).
	 * @return string
	 */
	protected static function property_key( $form, $field_id ) {
		$label    = '';
		$top_id   = (string) ( (int) $field_id ); // 1.3 -> '1', 2 -> '2'
		$fields   = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
		$parent   = null;
		$sub_id   = (string) $field_id;
		foreach ( $fields as $f ) {
			$f = (array) $f;
			if ( isset( $f['id'] ) && (string) $f['id'] === $top_id ) {
				$parent = $f;
				break;
			}
		}
		if ( $parent && (string) $field_id !== $top_id && ! empty( $parent['inputs'] ) ) {
			foreach ( (array) $parent['inputs'] as $input ) {
				$input = (array) $input;
				if ( isset( $input['id'] ) && (string) $input['id'] === $sub_id ) {
					$parent_label = isset( $parent['label'] ) ? (string) $parent['label'] : '';
					$input_label  = isset( $input['label'] ) ? (string) $input['label'] : '';
					$label        = trim( $parent_label . ' ' . $input_label );
					break;
				}
			}
		} elseif ( $parent ) {
			$label = isset( $parent['label'] ) ? (string) $parent['label'] : '';
		}

		$key = sanitize_key( $label );
		if ( '' === $key ) {
			$key = 'field_' . preg_replace( '/[^a-z0-9_]/i', '_', (string) $field_id );
		}
		return $key;
	}

	/**
	 * Reduce a value to a single string suitable for an email field.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected static function flatten_value( $value ) {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Format a value as a Newsman property value (string).
	 *
	 * Arrays are JSON-encoded so multi-value fields (checkboxes, list, etc.)
	 * survive the round-trip; scalars are cast to string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected static function format_value( $value ) {
		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
