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
 * Server-side processor for Newsman-enabled Atomic Form submissions.
 *
 * Hooked on `elementor_pro/atomic_forms/form_submitted`. Reads the in-flight ajax
 * request, looks up the Atomic_Form widget settings (and its nested input widgets)
 * in the saved document, and if the form is Newsman-enabled, subscribes the email
 * field's value to the chosen Newsman list with the marked fields as properties.
 *
 * v1 limitation: errors are logged via the Newsman Logger but NOT surfaced to the
 * end user inline. Atomic Form's controller runs its own response cycle independently
 * and we cannot inject error messages into it. The form will still appear successful.
 *
 * @class \Newsman\Elementor\AtomicFormProcessor
 */
class AtomicFormProcessor {
	/**
	 * Nonce action for the Atomic Form ajax dispatch (matches Pro's controller).
	 */
	public const NONCE_ACTION = 'elementor_pro_atomic_forms_send_form';

	/**
	 * Handle a form submission.
	 *
	 * @return void
	 */
	public function on_form_submitted() {
		$logger = Logger::init();

		try {
			$post_data = $this->read_post_data();
			if ( null === $post_data ) {
				return;
			}

			$widget_settings = $this->lookup_widget_settings( $post_data['post_id'], $post_data['form_id'] );
			if ( null === $widget_settings ) {
				return;
			}

			$enable = $this->resolve_atomic_value( $widget_settings['newsman_enable'] ?? false );
			if ( ! $this->is_truthy( $enable ) ) {
				return;
			}

			/**
			 * Filter whether to process this Newsman-enabled Atomic Form submission.
			 *
			 * Return false to skip Newsman processing entirely.
			 *
			 * @param bool  $should          Default true.
			 * @param array $widget_settings Resolved Atomic Form widget settings.
			 * @param array $post_data       Validated request payload (post_id, form_id, form_fields).
			 */
			if ( ! apply_filters( 'newsman_atomic_form_should_process', true, $widget_settings, $post_data ) ) {
				return;
			}

			$list_id = (string) $this->resolve_atomic_value( $widget_settings['newsman_list_id'] ?? '' );
			if ( '' === trim( $list_id ) ) {
				$logger->error( esc_html__( 'Newsman: Atomic Form is enabled but no list is selected.', 'newsman' ) );
				return;
			}

			$inputs_meta = $this->collect_inputs_meta( $post_data['post_id'], $post_data['form_id'] );

			$email_widget_id = '';
			$prop_widget_ids = array();
			foreach ( $inputs_meta as $widget_id => $flags ) {
				if ( '' === $email_widget_id && ! empty( $flags['is_email'] ) ) {
					$email_widget_id = $widget_id;
				}
				if ( ! empty( $flags['send'] ) ) {
					$prop_widget_ids[ $widget_id ] = $flags['prop_key'];
				}
			}

			if ( '' === $email_widget_id ) {
				$logger->error( esc_html__( 'Newsman: no field is marked as the email field on this Atomic Form.', 'newsman' ) );
				return;
			}

			$submitted = $this->extract_submitted_values( $post_data['form_fields'] );

			$email = isset( $submitted[ $email_widget_id ] ) ? trim( (string) $submitted[ $email_widget_id ] ) : '';

			/**
			 * Filter the email extracted from the Atomic Form submission.
			 *
			 * @param string $email           Raw email pulled from the marked input.
			 * @param array  $widget_settings Resolved Atomic Form widget settings.
			 * @param array  $post_data       Validated request payload.
			 */
			$email = (string) apply_filters( 'newsman_atomic_form_email', $email, $widget_settings, $post_data );

			if ( '' === $email ) {
				$logger->error( esc_html__( 'Newsman: Atomic Form email field is empty.', 'newsman' ) );
				return;
			}

			$properties = array();
			foreach ( $prop_widget_ids as $widget_id => $prop_key ) {
				if ( $widget_id === $email_widget_id ) {
					continue;
				}
				if ( ! array_key_exists( $widget_id, $submitted ) ) {
					continue;
				}
				$value                   = $submitted[ $widget_id ];
				$properties[ $prop_key ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			}

			/**
			 * Filter the subscriber properties for the Atomic Form submission.
			 *
			 * @param array  $properties      Properties built from inputs marked send=true.
			 * @param array  $widget_settings Resolved Atomic Form widget settings.
			 * @param array  $post_data       Validated request payload.
			 * @param string $email           Resolved email.
			 */
			$properties = apply_filters( 'newsman_atomic_form_properties', $properties, $widget_settings, $post_data, $email );

			SubscribeHelper::subscribe_with_props(
				get_current_blog_id(),
				$list_id,
				$email,
				$properties,
				IpAddress::init()->get_ip()
			);

			/**
			 * Fires after an Atomic Form submission was successfully processed.
			 *
			 * @param string $list_id         Newsman list ID.
			 * @param string $email           Subscribed email.
			 * @param array  $properties      Properties pushed.
			 * @param array  $widget_settings Resolved Atomic Form widget settings.
			 * @param array  $post_data       Validated request payload.
			 */
			do_action( 'newsman_atomic_form_processed', $list_id, $email, $properties, $widget_settings, $post_data );
		} catch ( \Exception $e ) {
			$logger->log_exception( $e );

			/**
			 * Fires when an Atomic Form submission failed Newsman processing.
			 *
			 * Atomic Forms run their own response cycle so this is the only signal
			 * available to integrators (errors are not surfaced to the end user).
			 *
			 * @param \Exception $e Caught exception.
			 */
			do_action( 'newsman_atomic_form_process_failed', $e );
		}
	}

	/**
	 * Read post_id, form_id and form_fields from the ajax request payload.
	 *
	 * @return array|null
	 */
	protected function read_post_data() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is validated below.
		if ( ! isset( $_POST['_nonce'] ) ) {
			return null;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return null;
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$form_id = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';

		$form_fields = array();
		if ( isset( $_POST['form_fields'] ) && is_array( $_POST['form_fields'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitized per-type inside extract_submitted_values().
			$form_fields = wp_unslash( $_POST['form_fields'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 0 === $post_id || '' === $form_id ) {
			return null;
		}

		return array(
			'post_id'     => $post_id,
			'form_id'     => $form_id,
			'form_fields' => $form_fields,
		);
	}

	/**
	 * Look up the Atomic_Form widget's settings by post_id + form_id.
	 *
	 * @param int    $post_id WP post ID.
	 * @param string $form_id Element ID of the Atomic_Form widget.
	 * @return array|null Resolved settings or null if not found.
	 */
	protected function lookup_widget_settings( $post_id, $form_id ) {
		$elements = $this->get_document_elements( $post_id );
		if ( null === $elements ) {
			return null;
		}

		$form_element = $this->find_element_by_id( $elements, $form_id );
		if ( null === $form_element ) {
			return null;
		}

		return $this->resolve_atomic_array( isset( $form_element['settings'] ) ? $form_element['settings'] : array() );
	}

	/**
	 * Walk the form's nested elements and return per-input Newsman flags keyed by the
	 * widget element id (which matches `form_fields[i].id` in the AJAX submission — NOT
	 * the input's `_cssid`/`name`, despite both also referring to the same field).
	 *
	 * @param int    $post_id WP post ID.
	 * @param string $form_id Element ID of the Atomic_Form widget.
	 * @return array map of `widget_id => [ 'send' => bool, 'is_email' => bool, 'prop_key' => string ]`
	 *               where `prop_key` is the input's `_cssid` (or widget id as fallback) and
	 *               is used as the human-readable Newsman property key.
	 */
	protected function collect_inputs_meta( $post_id, $form_id ) {
		$elements = $this->get_document_elements( $post_id );
		if ( null === $elements ) {
			return array();
		}

		$form_element = $this->find_element_by_id( $elements, $form_id );
		if ( null === $form_element ) {
			return array();
		}

		$meta = array();
		$this->walk_collect_inputs( $form_element, $meta );
		return $meta;
	}

	/**
	 * Recursively walk an element subtree and accumulate input-widget Newsman flags.
	 *
	 * @param array $element Element node.
	 * @param array $meta    Accumulator (passed by reference).
	 * @return void
	 */
	protected function walk_collect_inputs( $element, &$meta ) {
		if ( ! is_array( $element ) ) {
			return;
		}

		$widget_type = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
		$el_type     = isset( $element['elType'] ) ? (string) $element['elType'] : '';
		$type        = '' !== $widget_type ? $widget_type : $el_type;

		if ( in_array( $type, AtomicFormControls::get_input_types(), true ) ) {
			$settings  = isset( $element['settings'] ) ? $this->resolve_atomic_array( $element['settings'] ) : array();
			$widget_id = isset( $element['id'] ) ? (string) $element['id'] : '';
			if ( '' === $widget_id ) {
				return;
			}
			$cssid = isset( $settings['_cssid'] ) ? (string) $settings['_cssid'] : '';
			if ( '' === $cssid ) {
				$cssid = $widget_id;
			}

			$meta[ $widget_id ] = array(
				'send'     => $this->is_truthy( $settings['newsman_send_field'] ?? true ),
				'is_email' => $this->is_truthy( $settings['newsman_is_email'] ?? false ),
				'prop_key' => $cssid,
			);
		}

		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $child ) {
				$this->walk_collect_inputs( $child, $meta );
			}
		}
	}

	/**
	 * Build a `field_id => value` map from the ajax-submitted form_fields array.
	 *
	 * @param array $form_fields Raw `form_fields` from $_POST.
	 * @return array
	 */
	protected function extract_submitted_values( $form_fields ) {
		$values = array();
		if ( ! is_array( $form_fields ) ) {
			return $values;
		}

		foreach ( $form_fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$id = isset( $field['id'] ) ? sanitize_text_field( $field['id'] ) : '';
			if ( '' === $id ) {
				continue;
			}
			$value = isset( $field['value'] ) ? $field['value'] : '';
			if ( is_array( $value ) ) {
				$values[ $id ] = array_map( 'sanitize_text_field', $value );
			} else {
				$type = isset( $field['type'] ) ? sanitize_text_field( $field['type'] ) : 'text';
				if ( 'textarea' === $type ) {
					$values[ $id ] = sanitize_textarea_field( $value );
				} else {
					$values[ $id ] = sanitize_text_field( $value );
				}
			}
		}

		return $values;
	}

	/**
	 * Get the elements tree for a given post id, or null when unavailable.
	 *
	 * @param int $post_id WP post ID.
	 * @return array|null
	 */
	protected function get_document_elements( $post_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}

		$elementor = \Elementor\Plugin::instance();
		if ( ! isset( $elementor->documents ) || ! is_object( $elementor->documents ) ) {
			return null;
		}

		$document = $elementor->documents->get( (int) $post_id );
		if ( ! $document || ! method_exists( $document, 'get_elements_data' ) ) {
			return null;
		}

		$elements = $document->get_elements_data();
		return is_array( $elements ) ? $elements : null;
	}

	/**
	 * Recursively find an element by its `id` in an elements tree.
	 *
	 * @param array  $elements Elements tree.
	 * @param string $id       Element ID to find.
	 * @return array|null
	 */
	protected function find_element_by_id( $elements, $id ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['id'] ) && (string) $element['id'] === (string) $id ) {
				return $element;
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = $this->find_element_by_id( $element['elements'], $id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Resolve an atomic-widget settings array, unwrapping every `{ $$type, value }` wrapper.
	 *
	 * Mirrors `ElementorPro\Modules\AtomicWidgets\Settings_Resolver::resolve()` so we don't
	 * depend on Pro's internal class. (The format is documented and stable for atomic widgets.)
	 *
	 * @param array $settings Raw atomic settings.
	 * @return array
	 */
	protected function resolve_atomic_array( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}
		$resolved = array();
		foreach ( $settings as $key => $value ) {
			$resolved[ $key ] = $this->resolve_atomic_value( $value );
		}
		return $resolved;
	}

	/**
	 * Recursively unwrap a single atomic value.
	 *
	 * @param mixed $value Value (possibly wrapped).
	 * @return mixed
	 */
	protected function resolve_atomic_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( ! empty( $value['$$type'] ) && array_key_exists( 'value', $value ) ) {
			return $this->resolve_atomic_value( $value['value'] );
		}
		return array_map( array( $this, 'resolve_atomic_value' ), $value );
	}

	/**
	 * Boolean coercion that handles atomic Boolean_Prop_Type, JS booleans, and 'yes' strings.
	 *
	 * @param mixed $value Value to test.
	 * @return bool
	 */
	protected function is_truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			$lower = strtolower( $value );
			return 'yes' === $lower || 'true' === $lower || '1' === $lower;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value > 0;
		}
		return false;
	}
}
