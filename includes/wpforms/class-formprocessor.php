<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman WPForms submission processor.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\WPForms;

use Newsman\Logger;
use Newsman\Subscribe\Helper as SubscribeHelper;
use Newsman\User\IpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side processor for Newsman-enabled WPForms submissions.
 *
 * Hooked on `wpforms_process_complete` (fires after the entry has been saved
 * and notification emails dispatched). Reads the form's persisted Newsman
 * settings, pulls the email + property values out of `$fields`, and calls
 * `\Newsman\Subscribe\Helper`. The processor never blocks WPForms' own flow —
 * a Newsman API failure is logged but does not affect entry storage or emails.
 *
 * @class \Newsman\WPForms\FormProcessor
 */
class FormProcessor {
	/**
	 * Handle a WPForms submission.
	 *
	 * @param array $fields    Processed fields keyed by field id, each item:
	 *                         `[ 'name', 'value', 'id', 'type' ]`.
	 * @param array $entry     Raw posted entry payload.
	 * @param array $form_data Full form definition.
	 * @param int   $entry_id  Saved entry id (0 if entry storage disabled).
	 * @return void
	 */
	public function process( $fields, $entry, $form_data, $entry_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_array( $fields ) || ! is_array( $form_data ) ) {
			return;
		}

		$prop = FormPanel::resolve_settings( $form_data );
		if ( '1' !== (string) $prop['newsman_enable'] ) {
			return;
		}

		/**
		 * Filter whether to process this Newsman-enabled WPForms submission.
		 *
		 * Return false to skip Newsman processing (e.g. honeypot/spam, conditional
		 * logic). The form's other actions (entry storage, emails) still run.
		 *
		 * @param bool  $should     Default true.
		 * @param array $fields     Processed fields.
		 * @param array $entry      Raw entry payload.
		 * @param array $form_data  Full form definition.
		 * @param int   $entry_id   Saved entry id.
		 */
		if ( ! apply_filters( 'newsman_wpforms_should_process', true, $fields, $entry, $form_data, $entry_id ) ) {
			return;
		}

		$logger = Logger::init();

		$list_id = isset( $prop['newsman_list_id'] ) ? trim( (string) $prop['newsman_list_id'] ) : '';
		if ( '' === $list_id ) {
			$logger->error( esc_html__( 'Newsman: WPForms form is enabled but no list is selected.', 'newsman' ) );
			return;
		}

		$email_field_id = isset( $prop['newsman_email_field'] ) ? trim( (string) $prop['newsman_email_field'] ) : '';
		if ( '' === $email_field_id ) {
			$logger->error( esc_html__( 'Newsman: WPForms form has no email field configured.', 'newsman' ) );
			return;
		}

		$email = self::flatten_value( self::field_value( $fields, $email_field_id ) );
		$email = trim( (string) $email );

		/**
		 * Filter the email extracted from the WPForms submission.
		 *
		 * @param string $email      Resolved email value (post-trim).
		 * @param array  $fields     Processed fields.
		 * @param array  $entry      Raw entry payload.
		 * @param array  $form_data  Full form definition.
		 */
		$email = (string) apply_filters( 'newsman_wpforms_email', $email, $fields, $entry, $form_data );

		if ( '' === $email ) {
			$logger->error( esc_html__( 'Newsman: WPForms email field is empty.', 'newsman' ) );
			return;
		}

		$send_fields = isset( $prop['newsman_send_fields'] ) && is_array( $prop['newsman_send_fields'] )
			? array_keys( $prop['newsman_send_fields'] )
			: array();

		$firstname_field_id = isset( $prop['newsman_firstname_field'] ) ? trim( (string) $prop['newsman_firstname_field'] ) : '';
		$lastname_field_id  = isset( $prop['newsman_lastname_field'] ) ? trim( (string) $prop['newsman_lastname_field'] ) : '';

		$firstname = '';
		if ( '' !== $firstname_field_id ) {
			$firstname = trim( (string) self::flatten_value( self::field_value( $fields, $firstname_field_id ) ) );
		}
		$lastname = '';
		if ( '' !== $lastname_field_id ) {
			$lastname = trim( (string) self::flatten_value( self::field_value( $fields, $lastname_field_id ) ) );
		}

		$properties = array();
		foreach ( $send_fields as $field_id ) {
			$field_id = (string) $field_id;
			if ( '' === $field_id || $field_id === $email_field_id ) {
				continue;
			}
			// Firstname/lastname fields are sent via the context, not props.
			if ( '' !== $firstname_field_id && $field_id === $firstname_field_id ) {
				continue;
			}
			if ( '' !== $lastname_field_id && $field_id === $lastname_field_id ) {
				continue;
			}
			$value = self::field_value( $fields, $field_id );
			if ( null === $value ) {
				continue;
			}
			$key                = self::property_key( $fields, $form_data, $field_id );
			$properties[ $key ] = self::format_value( $value );
		}

		/**
		 * Filter the subscriber properties for the WPForms submission.
		 *
		 * @param array $properties Properties built from `send_fields`.
		 * @param array $fields     Processed fields.
		 * @param array $entry      Raw entry payload.
		 * @param array $form_data  Full form definition.
		 * @param string $email     Resolved email.
		 */
		$properties = apply_filters( 'newsman_wpforms_properties', $properties, $fields, $entry, $form_data, $email );

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
				$lastname
			);

			/**
			 * Fires after a WPForms submission was successfully processed by Newsman.
			 *
			 * @param string $list_id    Newsman list ID.
			 * @param string $email      Subscribed email.
			 * @param array  $properties Properties pushed.
			 * @param array  $fields     Processed fields.
			 * @param array  $form_data  Full form definition.
			 * @param int    $entry_id   Saved entry id.
			 */
			do_action( 'newsman_wpforms_processed', $list_id, $email, $properties, $fields, $form_data, $entry_id );
		} catch ( \Exception $e ) {
			$logger->log_exception( $e );

			/**
			 * Fires when a WPForms submission failed Newsman processing.
			 *
			 * @param \Exception $e          Caught exception.
			 * @param string     $list_id    Newsman list ID.
			 * @param string     $email      Resolved email.
			 * @param array      $properties Properties that would have been pushed.
			 * @param array      $fields     Processed fields.
			 * @param array      $form_data  Full form definition.
			 * @param int        $entry_id   Saved entry id.
			 */
			do_action( 'newsman_wpforms_process_failed', $e, $list_id, $email, $properties, $fields, $form_data, $entry_id );
		}
	}

	/**
	 * Pull a field's submitted value out of the processed `$fields` array.
	 *
	 * `$fields` is keyed by the integer field id; each entry has at minimum a
	 * `value` key. We accept both string and integer keys to handle WPForms'
	 * ambiguity (settings store ids as strings; `$fields` keys them as ints).
	 *
	 * @param array      $fields   Processed fields.
	 * @param string|int $field_id Field id to look up.
	 * @return mixed Returns null when the field is absent.
	 */
	protected static function field_value( $fields, $field_id ) {
		if ( isset( $fields[ $field_id ] ) ) {
			return isset( $fields[ $field_id ]['value'] ) ? $fields[ $field_id ]['value'] : null;
		}
		$int_id = (int) $field_id;
		if ( isset( $fields[ $int_id ] ) ) {
			return isset( $fields[ $int_id ]['value'] ) ? $fields[ $int_id ]['value'] : null;
		}
		return null;
	}

	/**
	 * Resolve a property key for a given field id.
	 *
	 * Prefers the field's label (sanitized to a stable lowercase snake_case
	 * key) so Newsman properties stay readable; falls back to the field id
	 * when no label is set.
	 *
	 * @param array      $fields    Processed fields.
	 * @param array      $form_data Form definition (carries the field label).
	 * @param string|int $field_id  Field id.
	 * @return string
	 */
	protected static function property_key( $fields, $form_data, $field_id ) {
		$label = '';
		if ( isset( $fields[ $field_id ]['name'] ) ) {
			$label = (string) $fields[ $field_id ]['name'];
		} elseif ( isset( $form_data['fields'][ $field_id ]['label'] ) ) {
			$label = (string) $form_data['fields'][ $field_id ]['label'];
		}

		$key = sanitize_key( $label );
		if ( '' === $key ) {
			$key = 'field_' . (string) $field_id;
		}
		return $key;
	}

	/**
	 * Reduce a value to a single string suitable for an email field.
	 *
	 * @param mixed $value Raw posted value.
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
	 * Arrays are JSON-encoded so multi-value fields (checkboxes, address,
	 * etc.) survive the round-trip; scalars are cast to string.
	 *
	 * @param mixed $value Raw posted value.
	 * @return string
	 */
	protected static function format_value( $value ) {
		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
