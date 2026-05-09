<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman Contact Form 7 submission processor.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\ContactForm7;

use Newsman\Logger;
use Newsman\Subscribe\Helper as SubscribeHelper;
use Newsman\User\IpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side processor for Newsman-enabled Contact Form 7 submissions.
 *
 * Hooked on `wpcf7_before_send_mail`. Reads the form's persisted Newsman settings, pulls
 * the email + property values from the submission, and calls `\Newsman\Subscribe\Helper`.
 * The processor never aborts the form (`$abort` is left false) — Newsman subscription is
 * an independent side-effect of a successful CF7 submission and a Newsman API failure
 * should not block the user's primary action (mail send).
 *
 * v1: errors are logged via the Newsman Logger but NOT surfaced to the end user. CF7's
 * response cycle has no first-class plumbing for inline error injection from third-party
 * integrations — comparable to the Atomic Forms processor limitation.
 *
 * @class \Newsman\ContactForm7\FormProcessor
 */
class FormProcessor {
	/**
	 * Handle a contact form submission.
	 *
	 * @param object $contact_form CF7 contact form.
	 * @param bool   $abort        Pass-by-reference abort flag (we never set it).
	 * @param object $submission   `WPCF7_Submission` instance.
	 * @return void
	 */
	public function process( $contact_form, &$abort, $submission ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'prop' ) ) {
			return;
		}
		if ( ! is_object( $submission ) || ! method_exists( $submission, 'get_posted_data' ) ) {
			return;
		}

		$prop = FormPanel::resolve_prop( $contact_form );
		if ( empty( $prop['enable'] ) ) {
			return;
		}

		/**
		 * Filter whether to process this Newsman-enabled CF7 submission.
		 *
		 * Return false to skip Newsman processing (e.g. honeypot/spam, conditional logic).
		 * The form's mail and other actions still run.
		 *
		 * @param bool   $should       Default true.
		 * @param object $contact_form CF7 contact form.
		 * @param object $submission   `WPCF7_Submission` instance.
		 */
		if ( ! apply_filters( 'newsman_cf7_should_process', true, $contact_form, $submission ) ) {
			return;
		}

		$logger = Logger::init();

		$list_id = isset( $prop['list_id'] ) ? trim( (string) $prop['list_id'] ) : '';
		if ( '' === $list_id ) {
			$logger->error( esc_html__( 'Newsman: CF7 form is enabled but no list is selected.', 'newsman' ) );
			return;
		}

		$email_field = isset( $prop['email_field'] ) ? trim( (string) $prop['email_field'] ) : '';
		if ( '' === $email_field ) {
			$logger->error( esc_html__( 'Newsman: CF7 form has no email field configured.', 'newsman' ) );
			return;
		}

		$email = self::flatten_value( $submission->get_posted_data( $email_field ) );
		$email = trim( (string) $email );

		/**
		 * Filter the email extracted from the CF7 submission.
		 *
		 * @param string $email        Resolved email value (post-trim).
		 * @param object $contact_form CF7 contact form.
		 * @param object $submission   `WPCF7_Submission` instance.
		 */
		$email = (string) apply_filters( 'newsman_cf7_email', $email, $contact_form, $submission );

		if ( '' === $email ) {
			$logger->error( esc_html__( 'Newsman: CF7 email field is empty.', 'newsman' ) );
			return;
		}

		$send_fields = isset( $prop['send_fields'] ) && is_array( $prop['send_fields'] )
			? $prop['send_fields']
			: array();

		$properties = array();
		foreach ( $send_fields as $field_name ) {
			$field_name = (string) $field_name;
			if ( '' === $field_name || $field_name === $email_field ) {
				continue;
			}
			$value = $submission->get_posted_data( $field_name );
			if ( null === $value ) {
				continue;
			}
			$properties[ $field_name ] = self::format_value( $value );
		}

		/**
		 * Filter the subscriber properties for the CF7 submission.
		 *
		 * @param array  $properties   Properties built from `send_fields`.
		 * @param object $contact_form CF7 contact form.
		 * @param object $submission   `WPCF7_Submission` instance.
		 * @param string $email        Resolved email.
		 */
		$properties = apply_filters( 'newsman_cf7_properties', $properties, $contact_form, $submission, $email );

		try {
			SubscribeHelper::subscribe_with_props(
				get_current_blog_id(),
				$list_id,
				$email,
				$properties,
				IpAddress::init()->get_ip()
			);

			/**
			 * Fires after a CF7 submission was successfully processed by Newsman.
			 *
			 * @param string $list_id      Newsman list ID.
			 * @param string $email        Subscribed email.
			 * @param array  $properties   Properties pushed.
			 * @param object $contact_form CF7 contact form.
			 * @param object $submission   `WPCF7_Submission` instance.
			 */
			do_action( 'newsman_cf7_processed', $list_id, $email, $properties, $contact_form, $submission );
		} catch ( \Exception $e ) {
			$logger->log_exception( $e );

			/**
			 * Fires when a CF7 submission failed Newsman processing.
			 *
			 * Errors are not surfaced to the end user inline; subscribe failures must not
			 * block the form's mail send. Use this action to push the failure to your own
			 * monitoring / alerting.
			 *
			 * @param \Exception $e            Caught exception.
			 * @param string     $list_id      Newsman list ID.
			 * @param string     $email        Resolved email.
			 * @param array      $properties   Properties that would have been pushed.
			 * @param object     $contact_form CF7 contact form.
			 * @param object     $submission   `WPCF7_Submission` instance.
			 */
			do_action( 'newsman_cf7_process_failed', $e, $list_id, $email, $properties, $contact_form, $submission );
		}
	}

	/**
	 * Reduce a CF7 posted value to a single string suitable for an email field.
	 *
	 * Email-as-text fields always come through as scalars, but checkbox / select-multiple
	 * fields can produce arrays — pick the first element for the email case.
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
	 * Format a CF7 posted value as a Newsman property value (string).
	 *
	 * Arrays are JSON-encoded so multi-value fields (checkboxes, select-multiple) survive
	 * the round-trip; scalars are cast to string.
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
