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

		$logger  = Logger::init();
		$list_id = isset( $settings['newsman_list_id'] ) ? trim( (string) $settings['newsman_list_id'] ) : '';

		if ( '' === $list_id ) {
			$message = esc_html__( 'Newsman list is not configured for this form.', 'newsman' );
			$logger->error( $message );
			$this->add_error( $ajax_handler, $message );
			return;
		}

		$form_fields_settings = isset( $settings['form_fields'] ) && is_array( $settings['form_fields'] )
			? $settings['form_fields']
			: array();

		$email_field_id = '';
		$prop_field_ids = array();

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
			if ( isset( $row['newsman_send_field'] ) && 'yes' === $row['newsman_send_field'] ) {
				$prop_field_ids[ $custom_id ] = $custom_id;
			}
		}

		if ( '' === $email_field_id ) {
			$message = esc_html__( 'Newsman: no field is marked as the email field.', 'newsman' );
			$logger->error( $message );
			$this->add_error( $ajax_handler, $message );
			return;
		}

		$submitted = $record->get( 'fields' );
		if ( ! is_array( $submitted ) ) {
			$submitted = array();
		}

		$email = isset( $submitted[ $email_field_id ]['value'] ) ? trim( (string) $submitted[ $email_field_id ]['value'] ) : '';
		if ( '' === $email ) {
			$message = esc_html__( 'Newsman: email field is empty.', 'newsman' );
			$logger->error( $message );
			$this->add_error( $ajax_handler, $message );
			return;
		}

		$properties = array();
		foreach ( $prop_field_ids as $field_id ) {
			if ( $field_id === $email_field_id ) {
				continue;
			}
			if ( ! isset( $submitted[ $field_id ] ) ) {
				continue;
			}
			$value                   = isset( $submitted[ $field_id ]['value'] ) ? $submitted[ $field_id ]['value'] : '';
			$properties[ $field_id ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		}

		try {
			SubscribeHelper::subscribe_with_props(
				get_current_blog_id(),
				$list_id,
				$email,
				$properties,
				IpAddress::init()->get_ip()
			);
		} catch ( \Exception $e ) {
			$logger->log_exception( $e );
			$message = $e->getMessage();
			if ( '' === trim( (string) $message ) ) {
				$message = esc_html__( 'Newsman subscription failed.', 'newsman' );
			}
			$this->add_error( $ajax_handler, $message );
			$this->add_admin_error( $ajax_handler, $message );
		}
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
