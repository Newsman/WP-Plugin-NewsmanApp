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

use Newsman\Config;
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

		// Contact Form 7's own Flamingo module skips storing a submission in both of these
		// cases, and pushing a subscriber to Newsman is storage in a third-party system:
		// demo mode means the submission is not a real one, and `do_not_store: on` is an
		// explicit privacy instruction from the site owner.
		$should_process = ! ( method_exists( $contact_form, 'in_demo_mode' ) && $contact_form->in_demo_mode() )
			&& ! ( method_exists( $submission, 'get_meta' ) && $submission->get_meta( 'do_not_store' ) );

		/**
		 * Filter whether to process this Newsman-enabled CF7 submission.
		 *
		 * Return false to skip Newsman processing (e.g. honeypot/spam, conditional logic),
		 * or true to process a submission Newsman would otherwise skip. Defaults to false
		 * when the form is in demo mode or carries the `do_not_store: on` additional
		 * setting, true otherwise. The form's mail and other actions always still run.
		 *
		 * @param bool   $should       Whether to process; see above for the default.
		 * @param object $contact_form CF7 contact form.
		 * @param object $submission   `WPCF7_Submission` instance.
		 */
		if ( ! apply_filters( 'newsman_cf7_should_process', $should_process, $contact_form, $submission ) ) {
			return;
		}

		$logger = Logger::init();
		$config = Config::init();

		// Newsletter-form mode: override list_id and segment_id from the Newsman - Sync config.
		// Campaign-form mode: use the per-form list_id and segment_id.
		if ( ! empty( $prop['newsletter_form'] ) ) {
			$list_id    = trim( (string) $config->get_list_id( get_current_blog_id() ) );
			$segment_id = trim( (string) $config->get_segment_id( get_current_blog_id() ) );
			if ( '' === $list_id ) {
				$logger->error( esc_html__( 'Newsman: CF7 form is marked as newsletter form but no list is configured in Newsman - Sync.', 'newsman' ) );
				return;
			}
		} else {
			$list_id    = isset( $prop['list_id'] ) ? trim( (string) $prop['list_id'] ) : '';
			$segment_id = isset( $prop['segment_id'] ) ? trim( (string) $prop['segment_id'] ) : '';
			if ( '' === $list_id ) {
				$logger->error( esc_html__( 'Newsman: CF7 form is enabled but no list is selected.', 'newsman' ) );
				return;
			}
			if ( '' !== $segment_id && ! \Newsman\Subscribe\Segments::belongs_to_list( get_current_blog_id(), $list_id, $segment_id ) ) {
				$segment_id = '';
			}
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

		$firstname_field = isset( $prop['firstname_field'] ) ? trim( (string) $prop['firstname_field'] ) : '';
		$lastname_field  = isset( $prop['lastname_field'] ) ? trim( (string) $prop['lastname_field'] ) : '';
		$phone_field     = isset( $prop['phone_field'] ) ? trim( (string) $prop['phone_field'] ) : '';

		$firstname = '';
		if ( '' !== $firstname_field ) {
			$firstname = trim( (string) self::flatten_value( $submission->get_posted_data( $firstname_field ) ) );
		}
		$lastname = '';
		if ( '' !== $lastname_field ) {
			$lastname = trim( (string) self::flatten_value( $submission->get_posted_data( $lastname_field ) ) );
		}

		// One field mapped to both Firstname and Lastname can only be a full-name field, so
		// sending it as both would store the name twice ("Ion Popescu Ion Popescu"). Split
		// it on whitespace instead: the last word is the lastname, the rest the firstname.
		if ( '' !== $firstname_field && $firstname_field === $lastname_field ) {
			list( $firstname, $lastname ) = self::split_full_name( $firstname );
		}

		$phone = '';
		if ( '' !== $phone_field ) {
			$phone = trim( (string) self::flatten_value( $submission->get_posted_data( $phone_field ) ) );
		}

		$properties = array();
		foreach ( $send_fields as $field_name ) {
			$field_name = (string) $field_name;
			if ( '' === $field_name || $field_name === $email_field ) {
				continue;
			}
			// Firstname/lastname/phone fields are sent via dedicated keys, not under their form-tag name.
			if ( '' !== $firstname_field && $field_name === $firstname_field ) {
				continue;
			}
			if ( '' !== $lastname_field && $field_name === $lastname_field ) {
				continue;
			}
			if ( '' !== $phone_field && $field_name === $phone_field ) {
				continue;
			}
			// Tag types that opt out of storage ([quiz], CAPTCHA responses, ...) must not
			// leave the site as subscriber properties — same filtering CF7 applies before
			// handing a submission to Flamingo.
			if ( self::is_do_not_store_field( $contact_form, $field_name ) ) {
				continue;
			}
			$value = $submission->get_posted_data( $field_name );
			if ( null === $value ) {
				continue;
			}
			$properties[ $field_name ] = self::format_value( $value );
		}

		if ( '' !== $phone ) {
			$properties['phone'] = $phone;
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

		$optin_mode = isset( $prop['optin_mode'] ) && 'double' === $prop['optin_mode'] ? 'double' : 'single';

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
	 * Split a full name into a firstname and a lastname.
	 *
	 * The last whitespace-separated word becomes the lastname and everything before it the
	 * firstname, so "Ion Mihai Popescu" yields "Ion Mihai" + "Popescu". A value with no
	 * space is kept as the firstname: people routinely enter only a given name, and an
	 * empty firstname would break `{{firstname}}` personalisation in campaigns.
	 *
	 * @param string $name Full name as entered in the form.
	 * @return array{0:string,1:string} `[ firstname, lastname ]`.
	 */
	protected static function split_full_name( $name ) {
		// Collapse every run of whitespace - including the non-breaking spaces that ride
		// along with copy-pasted values - so the word split is reliable. A malformed UTF-8
		// value makes preg_replace() return null; keep the raw value in that case.
		$normalized = preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $name );
		$name       = trim( null === $normalized ? (string) $name : $normalized );

		if ( '' === $name ) {
			return array( '', '' );
		}

		$parts = explode( ' ', $name );
		if ( count( $parts ) < 2 ) {
			return array( $name, '' );
		}

		$lastname = (string) array_pop( $parts );

		return array( implode( ' ', $parts ), $lastname );
	}

	/**
	 * Whether a form-tag is flagged `do-not-store` by Contact Form 7.
	 *
	 * Mirrors the per-field filtering CF7's Flamingo module applies before persisting a
	 * submission: tag types such as `[quiz]` and CAPTCHA responses declare the
	 * `do-not-store` feature and must never be persisted anywhere.
	 *
	 * @param object $contact_form CF7 contact form.
	 * @param string $field_name   Form-tag name.
	 * @return bool
	 */
	protected static function is_do_not_store_field( $contact_form, $field_name ) {
		if ( ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return false;
		}

		$tags = $contact_form->scan_form_tags(
			array(
				'name'    => $field_name,
				'feature' => 'do-not-store',
			)
		);

		return ! empty( $tags );
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
