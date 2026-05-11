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

use Newsman\Logger;
use Newsman\Subscribe\Helper as SubscribeHelper;
use Newsman\User\IpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side processor for Newsman-enabled Elementor form submissions.
 *
 * @class \Newsman\Elementor\FormProcessor
 */
class FormProcessor {
	/**
	 * Handle a form submission.
	 *
	 * Hooked on `elementor_pro/forms/process`. Checks the per-form `newsman_enable`
	 * switcher; when enabled, finds the email field via the per-field `newsman_is_email`
	 * marker, builds a properties array from the per-field `newsman_send_field` markers,
	 * then calls `\Newsman\Service\SubscribeEmail`. Errors are surfaced to the end user
	 * via `$ajax_handler->add_error_message()` so the form does not show a success state.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       Submitted form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Ajax response handler.
	 * @return void
	 */
	public function process( $record, $ajax_handler ) {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
			return;
		}

		$settings = $record->get( 'form_settings' );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$enable = isset( $settings['newsman_enable'] ) ? $settings['newsman_enable'] : '';
		if ( 'yes' !== $enable ) {
			return;
		}

		/**
		 * Filter whether to process this Newsman-enabled legacy Form submission.
		 *
		 * Return false to skip Newsman processing entirely (e.g. spam-flagged records,
		 * conditional logic). The form's other actions (email, redirect, ...) still run.
		 *
		 * @param bool   $should   Default true.
		 * @param object $record   Submitted form record.
		 * @param array  $settings Form widget settings.
		 */
		if ( ! apply_filters( 'newsman_elementor_form_should_process', true, $record, $settings ) ) {
			return;
		}

		$logger  = Logger::init();
		$list_id = isset( $settings['newsman_list_id'] ) ? trim( (string) $settings['newsman_list_id'] ) : '';

		if ( '' === $list_id ) {
			$message = esc_html__( 'Newsman list is not configured for this form.', 'newsman' );
			$logger->error( $message );
			$this->add_error( $ajax_handler, $this->filter_error_message( $message, 'no_list', $record ) );
			return;
		}

		$form_fields_settings = isset( $settings['form_fields'] ) && is_array( $settings['form_fields'] )
			? $settings['form_fields']
			: array();

		$email_field_id     = '';
		$firstname_field_id = '';
		$lastname_field_id  = '';
		$prop_field_ids     = array();

		foreach ( $form_fields_settings as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$custom_id = isset( $row['custom_id'] ) ? (string) $row['custom_id'] : '';
			if ( '' === $custom_id ) {
				continue;
			}
			if ( '' === $email_field_id && isset( $row['newsman_is_email'] ) && 'yes' === $row['newsman_is_email'] ) {
				$email_field_id = $custom_id;
			}
			if ( '' === $firstname_field_id && isset( $row['newsman_is_firstname'] ) && 'yes' === $row['newsman_is_firstname'] ) {
				$firstname_field_id = $custom_id;
			}
			if ( '' === $lastname_field_id && isset( $row['newsman_is_lastname'] ) && 'yes' === $row['newsman_is_lastname'] ) {
				$lastname_field_id = $custom_id;
			}
			if ( isset( $row['newsman_send_field'] ) && 'yes' === $row['newsman_send_field'] ) {
				$prop_field_ids[ $custom_id ] = $custom_id;
			}
		}

		if ( '' === $email_field_id ) {
			$message = esc_html__( 'Newsman: no field is marked as the email field.', 'newsman' );
			$logger->error( $message );
			$this->add_error( $ajax_handler, $this->filter_error_message( $message, 'no_email_field', $record ) );
			return;
		}

		$submitted = $record->get( 'fields' );
		if ( ! is_array( $submitted ) ) {
			$submitted = array();
		}

		$email = isset( $submitted[ $email_field_id ]['value'] ) ? trim( (string) $submitted[ $email_field_id ]['value'] ) : '';

		/**
		 * Filter the email extracted from the legacy Form submission.
		 *
		 * Allows normalization (e.g. lowercase, alias stripping) with full record context.
		 *
		 * @param string $email    Raw email pulled from the marked field.
		 * @param object $record   Submitted form record.
		 * @param array  $settings Form widget settings.
		 */
		$email = (string) apply_filters( 'newsman_elementor_form_email', $email, $record, $settings );

		if ( '' === $email ) {
			$message = esc_html__( 'Newsman: email field is empty.', 'newsman' );
			$logger->error( $message );
			$this->add_error( $ajax_handler, $this->filter_error_message( $message, 'empty_email', $record ) );
			return;
		}

		$firstname = '';
		if ( '' !== $firstname_field_id && isset( $submitted[ $firstname_field_id ]['value'] ) ) {
			$firstname = trim( (string) $submitted[ $firstname_field_id ]['value'] );
		}
		$lastname = '';
		if ( '' !== $lastname_field_id && isset( $submitted[ $lastname_field_id ]['value'] ) ) {
			$lastname = trim( (string) $submitted[ $lastname_field_id ]['value'] );
		}

		$properties = array();
		foreach ( $prop_field_ids as $field_id ) {
			if ( $field_id === $email_field_id ) {
				continue;
			}
			// Firstname/lastname fields are sent via the context, not props.
			if ( '' !== $firstname_field_id && $field_id === $firstname_field_id ) {
				continue;
			}
			if ( '' !== $lastname_field_id && $field_id === $lastname_field_id ) {
				continue;
			}
			if ( ! isset( $submitted[ $field_id ] ) ) {
				continue;
			}
			$value                   = isset( $submitted[ $field_id ]['value'] ) ? $submitted[ $field_id ]['value'] : '';
			$properties[ $field_id ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		}

		/**
		 * Filter the subscriber properties for the legacy Form submission.
		 *
		 * Has access to the full $record (unlike the helper-level filter) — useful when
		 * properties need to depend on fields that aren't marked "Send to Newsman".
		 *
		 * @param array  $properties Properties built from the marked fields.
		 * @param object $record     Submitted form record.
		 * @param array  $settings   Form widget settings.
		 * @param string $email      Resolved email.
		 */
		$properties = apply_filters( 'newsman_elementor_form_properties', $properties, $record, $settings, $email );

		$optin_mode = isset( $settings['newsman_optin_mode'] ) && 'double' === $settings['newsman_optin_mode'] ? 'double' : 'single';

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
			 * Fires after a legacy Elementor Form submission was successfully processed.
			 *
			 * @param string $list_id    Newsman list ID.
			 * @param string $email      Subscribed email.
			 * @param array  $properties Properties pushed.
			 * @param object $record     Submitted form record.
			 * @param array  $settings   Form widget settings.
			 */
			do_action( 'newsman_elementor_form_processed', $list_id, $email, $properties, $record, $settings );
		} catch ( \Exception $e ) {
			$logger->log_exception( $e );
			$message = $e->getMessage();
			if ( '' === trim( (string) $message ) ) {
				$message = esc_html__( 'Newsman subscription failed.', 'newsman' );
			}
			$message = $this->filter_error_message( $message, 'subscribe_failed', $record );
			$this->add_error( $ajax_handler, $message );
			$this->add_admin_error( $ajax_handler, $message );

			/**
			 * Fires when a legacy Elementor Form submission failed Newsman processing.
			 *
			 * @param \Exception $e          Caught exception.
			 * @param string     $list_id    Newsman list ID.
			 * @param string     $email      Resolved email.
			 * @param array      $properties Properties that would have been pushed.
			 * @param object     $record     Submitted form record.
			 * @param array      $settings   Form widget settings.
			 */
			do_action( 'newsman_elementor_form_process_failed', $e, $list_id, $email, $properties, $record, $settings );
		}
	}

	/**
	 * Run the user-facing error message through `newsman_elementor_form_error_message`.
	 *
	 * @param string $message Default message.
	 * @param string $context One of: 'no_list', 'no_email_field', 'empty_email', 'subscribe_failed'.
	 * @param object $record  Submitted form record.
	 * @return string
	 */
	protected function filter_error_message( $message, $context, $record ) {
		/**
		 * Filter the user-facing error surfaced on the form when Newsman processing fails.
		 *
		 * @param string $message Default message.
		 * @param string $context Failure context: 'no_list', 'no_email_field', 'empty_email', 'subscribe_failed'.
		 * @param object $record  Submitted form record.
		 */
		return (string) apply_filters( 'newsman_elementor_form_error_message', $message, $context, $record );
	}

	/**
	 * Surface a user-visible error via the Elementor ajax handler.
	 *
	 * @param object $ajax_handler Ajax handler.
	 * @param string $message      Message to surface.
	 * @return void
	 */
	protected function add_error( $ajax_handler, $message ) {
		if ( is_object( $ajax_handler ) && method_exists( $ajax_handler, 'add_error_message' ) ) {
			$ajax_handler->add_error_message( $message );
		}
	}

	/**
	 * Surface an admin/editor-visible error via the Elementor ajax handler when supported.
	 *
	 * @param object $ajax_handler Ajax handler.
	 * @param string $message      Message to surface.
	 * @return void
	 */
	protected function add_admin_error( $ajax_handler, $message ) {
		if ( is_object( $ajax_handler ) && method_exists( $ajax_handler, 'add_admin_error_message' ) ) {
			$ajax_handler->add_admin_error_message( $message );
		}
	}
}
