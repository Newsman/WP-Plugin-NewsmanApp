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

namespace Newsman\Export\Retriever;

use Newsman\Config;
use Newsman\Export\V1\ApiV1Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscriber retriever backed by Elementor Pro form submissions.
 *
 * Pulls subscribers from the `wp_e_submissions` + `wp_e_submissions_values` tables,
 * filtered to the form selected on the Newsman settings page. The widget's per-field
 * Newsman markers (`newsman_is_email` / `newsman_is_firstname` / `newsman_is_lastname` /
 * `newsman_is_phone`) are resolved by walking the hosting post's `_elementor_data`
 * meta to recover the field IDs, then those IDs are looked up in `e_submissions_values`
 * via JOINs.
 *
 * Filters supported: `created_at`, `subscriber_id`(s), `email`, `firstname`, `lastname`.
 * Sorts supported: `created_at`, `email`, `subscriber_id`. `customer_id` filter throws
 * the standard 1010 unsupported-filter error. Output rows are deduped by email; the
 * most recent submission wins. Phone is gated by `Remarketing\Config::is_send_telephone()`
 * (parity with `SubscribersWoocommerceFeed`); IP prefers the per-submission `user_ip`
 * column with a fallback to the configured server IP.
 *
 * @class \Newsman\Export\Retriever\SubscribersElementorPro
 */
class SubscribersElementorPro extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Default batch page size.
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Check that all preconditions for this source are still satisfied.
	 *
	 * Used by the orchestrator (`Subscribers::process()`) to decide whether to delegate
	 * to this retriever or fall through to the next source in the chain.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public static function is_eligible( $blog_id = null ) {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return false;
		}
		$config  = Config::init();
		$form_id = $config->get_elementor_export_form_id( $blog_id );
		if ( '' === $form_id ) {
			return false;
		}
		$widget = self::lookup_widget( $form_id );
		if ( null === $widget ) {
			return false;
		}
		return ! empty( $widget['flags']['enable'] ) && ! empty( $widget['flags']['newsletter_form'] );
	}

	/**
	 * Allowed v1 filter fields - internal column mapping.
	 *
	 * @return array
	 */
	public function get_where_parameters_mapping() {
		return array(
			'created_at'     => array(
				'field' => 'created_at',
				'type'  => 'string',
			),
			'subscriber_id'  => array(
				'field' => 'id',
				'type'  => 'int',
			),
			'subscriber_ids' => array(
				'field'    => 'id',
				'multiple' => true,
				'type'     => 'int',
			),
			'email'          => array(
				'field' => 'email',
				'type'  => 'string',
			),
			'firstname'      => array(
				'field' => 'firstname',
				'type'  => 'string',
			),
			'lastname'       => array(
				'field' => 'lastname',
				'type'  => 'string',
			),
		);
	}

	/**
	 * Allowed v1 sort fields - internal column mapping.
	 *
	 * @return array
	 */
	public function get_allowed_sort_fields() {
		return array(
			'created_at'    => 'created_at',
			'email'         => 'email',
			'subscriber_id' => 'id',
		);
	}

	/**
	 * Reject `customer_id`/`modified_at` filters with a clean 1010 in v1 context.
	 *
	 * @param array    $data    Request data.
	 * @param int|null $blog_id WP blog ID.
	 * @return array
	 * @throws ApiV1Exception When an unsupported filter is requested in v1 context.
	 * @throws \Exception     For the legacy code path.
	 */
	public function process_list_parameters( $data = array(), $blog_id = null ) {
		foreach ( array( 'customer_id', 'customer_ids', 'modified_at' ) as $unsupported ) {
			if ( isset( $data[ $unsupported ] ) ) {
				if ( isset( $data['_v1_filter_fields'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					throw new ApiV1Exception( 1010, 'Filter not supported on this platform: ' . $unsupported, 400 );
				}
				throw new \Exception( esc_html( $unsupported . ' is not supported by the Elementor Pro subscriber retriever.' ) );
			}
		}
		return parent::process_list_parameters( $data, $blog_id );
	}

	/**
	 * Process subscribers retriever.
	 *
	 * @param array    $data    Request data.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws ApiV1Exception On v1-context configuration errors.
	 * @throws \Exception On generic errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		global $wpdb;

		$data['default_page_size'] = self::DEFAULT_PAGE_SIZE;
		$processed                 = $this->process_list_parameters( $data, $blog_id );

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$form_id = $this->config->get_elementor_export_form_id( $blog_id );
		$widget  = self::lookup_widget( $form_id );

		if ( null === $widget ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			if ( isset( $data['_v1_filter_fields'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new ApiV1Exception( 3002, 'Subscriber export not enabled', 500 );
			}
			throw new \Exception( 'Configured Elementor form widget could not be resolved.' );
		}

		$email_field     = $widget['fields']['email'];
		$firstname_field = $widget['fields']['firstname'];
		$lastname_field  = $widget['fields']['lastname'];
		$phone_field     = $widget['fields']['phone'];

		if ( '' === $email_field ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			if ( isset( $data['_v1_filter_fields'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new ApiV1Exception( 3002, 'Subscriber export not enabled', 500 );
			}
			throw new \Exception( 'No field is marked as the email field on the selected Elementor form.' );
		}

		$submissions_table = $wpdb->prefix . 'e_submissions';
		$values_table      = $wpdb->prefix . 'e_submissions_values';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$where  = $wpdb->prepare( 's.form_id = %s', $form_id );
		$joins  = $wpdb->prepare( "INNER JOIN {$values_table} v_email ON v_email.submission_id = s.id AND v_email.`key` = %s", $email_field );
		$select = 'v_email.value AS email, MAX(s.id) AS id, MAX(s.created_at) AS created_at, MAX(s.user_ip) AS user_ip';

		if ( '' !== $firstname_field ) {
			$joins  .= $wpdb->prepare( " LEFT JOIN {$values_table} v_fn ON v_fn.submission_id = s.id AND v_fn.`key` = %s", $firstname_field );
			$select .= ', MAX(v_fn.value) AS firstname';
		} else {
			$select .= ", '' AS firstname";
		}
		if ( '' !== $lastname_field ) {
			$joins  .= $wpdb->prepare( " LEFT JOIN {$values_table} v_ln ON v_ln.submission_id = s.id AND v_ln.`key` = %s", $lastname_field );
			$select .= ', MAX(v_ln.value) AS lastname';
		} else {
			$select .= ", '' AS lastname";
		}
		if ( '' !== $phone_field ) {
			$joins  .= $wpdb->prepare( " LEFT JOIN {$values_table} v_phone ON v_phone.submission_id = s.id AND v_phone.`key` = %s", $phone_field );
			$select .= ', MAX(v_phone.value) AS phone';
		} else {
			$select .= ", '' AS phone";
		}

		foreach ( $processed['filters'] as $filter ) {
			$operator = $this->get_expressions_definition()[ $filter['operator'] ];
			$value    = $filter['value'];
			$field    = $filter['field'];

			if ( 'id' === $field ) {
				if ( in_array( $filter['operator'], array( 'in', 'nin' ), true ) ) {
					$ids = array_filter( array_map( 'intval', (array) $value ) );
					if ( empty( $ids ) ) {
						$ids = array( 0 );
					}
					$where .= ' AND s.id ' . $operator . ' (' . implode( ',', $ids ) . ')';
				} else {
					$where .= $wpdb->prepare( ' AND s.id ' . $operator . ' %d', (int) $value );
				}
			} elseif ( 'created_at' === $field ) {
				$where .= $wpdb->prepare( ' AND s.created_at ' . $operator . ' %s', (string) $value );
			} elseif ( 'email' === $field ) {
				$where .= $wpdb->prepare( ' AND v_email.value ' . $operator . ' %s', (string) $value );
			} elseif ( 'firstname' === $field && '' !== $firstname_field ) {
				$where .= $wpdb->prepare( ' AND v_fn.value ' . $operator . ' %s', (string) $value );
			} elseif ( 'lastname' === $field && '' !== $lastname_field ) {
				$where .= $wpdb->prepare( ' AND v_ln.value ' . $operator . ' %s', (string) $value );
			}
		}

		$order_clause = 'ORDER BY created_at DESC';
		if ( isset( $processed['sort'] ) ) {
			$order_dir   = ( 'DESC' === $processed['order'] ) ? 'DESC' : 'ASC';
			$sort_column = (string) $processed['sort'];
			if ( in_array( $sort_column, array( 'created_at', 'email', 'id' ), true ) ) {
				$order_clause = 'ORDER BY ' . $sort_column . ' ' . $order_dir;
			}
		}

		$sql = "SELECT {$select}
			    FROM {$submissions_table} s
			    {$joins}
			    WHERE {$where}
			      AND v_email.value <> ''
			    GROUP BY v_email.value
			    {$order_clause}
			    LIMIT %d OFFSET %d";

		$sql  = $wpdb->prepare( $sql, (int) $processed['limit'], (int) $processed['start'] );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$result = array();
		foreach ( $rows as $row ) {
			$result[] = $this->build_subscriber_row( $row, $blog_id );
		}

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Compose the subscriber row from an aggregated submissions query result.
	 *
	 * @param array    $row     Aggregated submission row.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	protected function build_subscriber_row( $row, $blog_id ) {
		$out = array(
			'subscriber_id'   => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'email'           => isset( $row['email'] ) ? (string) $row['email'] : '',
			'firstname'       => isset( $row['firstname'] ) ? (string) $row['firstname'] : '',
			'lastname'        => isset( $row['lastname'] ) ? (string) $row['lastname'] : '',
			'date_subscribed' => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			'confirmed'       => 1,
			'source'          => 'Elementor Pro form submissions',
		);

		if ( $this->remarketing_config->is_send_telephone() && ! empty( $row['phone'] ) ) {
			$cleaned = $this->clean_phone( (string) $row['phone'] );
			if ( '' !== $cleaned ) {
				$out['phone'] = $cleaned;
			}
		}

		$ip = isset( $row['user_ip'] ) ? trim( (string) $row['user_ip'] ) : '';
		if ( '' === $ip ) {
			$server_ip = $this->config->get_server_ip( $blog_id );
			if ( ! empty( $server_ip ) && \Newsman\User\HostIpAddress::NOT_FOUND !== $server_ip ) {
				$ip = $server_ip;
			}
		}
		if ( '' !== $ip ) {
			$out['ip'] = $ip;
		}

		return apply_filters( 'newsman_export_retriever_subscribers_elementor_pro_process_subscriber', $out, $row, $blog_id );
	}

	/**
	 * Locate the form widget by its 8-char ID and extract Newsman flags + field mappings.
	 *
	 * Scans `_elementor_data` post meta with a `LIKE %form_id%` filter, then walks the JSON
	 * to find the matching widget node. Returns null when the widget cannot be found, the
	 * data is malformed, or the Newsman flags are no longer set.
	 *
	 * @param string $form_id Elementor widget ID.
	 * @return array|null `[ 'flags' => [...], 'fields' => [ 'email' => string, 'firstname' => string, 'lastname' => string, 'phone' => string ] ]`
	 */
	public static function lookup_widget( $form_id ) {
		global $wpdb;

		$form_id = trim( (string) $form_id );
		if ( '' === $form_id ) {
			return null;
		}

		// Include trashed pages: wp_e_submissions rows for this widget id survive when
		// the host page is trashed, so the lookup must still find the form node so the
		// export keeps working. Exclude revisions (they duplicate their parent's data).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				 FROM   {$wpdb->postmeta} pm
				 JOIN   {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE  pm.meta_key = %s
				   AND  pm.meta_value LIKE %s
				   AND  p.post_status NOT IN ('auto-draft')
				   AND  p.post_type != 'revision'
				 LIMIT 50",
				'_elementor_data',
				'%' . $wpdb->esc_like( $form_id ) . '%'
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			$data = json_decode( (string) $row['meta_value'], true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$found = null;
			// `_elementor_data` decodes to a list of top-level sections; walk each one
			// individually since the walker inspects keys directly on the node it receives.
			foreach ( $data as $top_node ) {
				self::walk_for_widget( $top_node, $form_id, $found );
				if ( null !== $found ) {
					break;
				}
			}
			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Recursively walk the Elementor data tree to find the form widget by ID.
	 *
	 * @param array      $node    Current element node.
	 * @param string     $form_id Target widget ID.
	 * @param array|null $found   Out-param.
	 * @return void
	 */
	protected static function walk_for_widget( $node, $form_id, &$found ) {
		if ( null !== $found || ! is_array( $node ) ) {
			return;
		}

		$widget_type = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
		$el_type     = isset( $node['elType'] ) ? (string) $node['elType'] : '';
		$id          = isset( $node['id'] ) ? (string) $node['id'] : '';

		// Legacy Pro Form: widgetType='form'. Atomic Form: elType='e-form' (it's a
		// container, not a widget). Pass a normalized form-kind downstream so the
		// field-mapping resolver picks the right reader (legacy repeater vs atomic walk).
		$is_legacy_form = ( 'form' === $widget_type );
		$is_atomic_form = ( 'e-form' === $el_type || 'e-form' === $widget_type );
		$is_form        = $is_legacy_form || $is_atomic_form;

		if ( $is_form && $id === $form_id ) {
			$settings   = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			$form_kind  = $is_legacy_form ? 'form' : 'e-form';
			$found      = array(
				'widget_type' => $form_kind,
				'flags'       => array(
					'enable'          => self::resolve_flag( $settings, 'newsman_enable' ),
					'newsletter_form' => self::resolve_flag( $settings, 'newsman_newsletter_form' ),
				),
				'fields'      => self::resolve_field_mapping( $node, $form_kind ),
			);
			return;
		}

		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				self::walk_for_widget( $child, $form_id, $found );
				if ( null !== $found ) {
					return;
				}
			}
		}
	}

	/**
	 * Resolve email/firstname/lastname/phone field keys for the form widget.
	 *
	 * Legacy Pro Form: reads `form_fields` repeater, returns each marked field's `custom_id`
	 *   (which is also the value of `wp_e_submissions_values.key` for that field).
	 * Atomic Form: walks the form's `elements` children, returns each marked input's `_cssid`
	 *   (fallback to widget id), again matching `wp_e_submissions_values.key`.
	 *
	 * @param array  $form_node   Element node for the form widget.
	 * @param string $widget_type 'form' (legacy) or 'e-form' (atomic).
	 * @return array `[ 'email' => string, 'firstname' => string, 'lastname' => string, 'phone' => string ]`
	 */
	protected static function resolve_field_mapping( $form_node, $widget_type ) {
		$fields = array(
			'email'     => '',
			'firstname' => '',
			'lastname'  => '',
			'phone'     => '',
		);

		if ( 'form' === $widget_type ) {
			$settings = isset( $form_node['settings'] ) && is_array( $form_node['settings'] ) ? $form_node['settings'] : array();
			$rows     = isset( $settings['form_fields'] ) && is_array( $settings['form_fields'] ) ? $settings['form_fields'] : array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$custom_id = isset( $row['custom_id'] ) ? (string) $row['custom_id'] : '';
				if ( '' === $custom_id ) {
					continue;
				}
				if ( '' === $fields['email'] && isset( $row['newsman_is_email'] ) && 'yes' === $row['newsman_is_email'] ) {
					$fields['email'] = $custom_id;
				}
				if ( '' === $fields['firstname'] && isset( $row['newsman_is_firstname'] ) && 'yes' === $row['newsman_is_firstname'] ) {
					$fields['firstname'] = $custom_id;
				}
				if ( '' === $fields['lastname'] && isset( $row['newsman_is_lastname'] ) && 'yes' === $row['newsman_is_lastname'] ) {
					$fields['lastname'] = $custom_id;
				}
				if ( '' === $fields['phone'] && isset( $row['newsman_is_phone'] ) && 'yes' === $row['newsman_is_phone'] ) {
					$fields['phone'] = $custom_id;
				}
			}
			return $fields;
		}

		// Atomic form — walk children for input widgets carrying the flags.
		self::walk_atomic_inputs( $form_node, $fields );
		return $fields;
	}

	/**
	 * Walk an atomic form subtree to find inputs marked as email/firstname/lastname/phone.
	 *
	 * @param array $node   Element node.
	 * @param array $fields Out-param accumulator.
	 * @return void
	 */
	protected static function walk_atomic_inputs( $node, &$fields ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		$widget_type = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
		$el_type     = isset( $node['elType'] ) ? (string) $node['elType'] : '';
		$type        = '' !== $widget_type ? $widget_type : $el_type;

		$input_types = array( 'e-form-input', 'e-form-textarea', 'e-form-checkbox' );
		if ( in_array( $type, $input_types, true ) ) {
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			$cssid    = self::resolve_string( $settings, '_cssid' );
			if ( '' === $cssid ) {
				$cssid = isset( $node['id'] ) ? (string) $node['id'] : '';
			}
			if ( '' !== $cssid ) {
				if ( '' === $fields['email'] && self::resolve_flag( $settings, 'newsman_is_email' ) ) {
					$fields['email'] = $cssid;
				}
				if ( '' === $fields['firstname'] && self::resolve_flag( $settings, 'newsman_is_firstname' ) ) {
					$fields['firstname'] = $cssid;
				}
				if ( '' === $fields['lastname'] && self::resolve_flag( $settings, 'newsman_is_lastname' ) ) {
					$fields['lastname'] = $cssid;
				}
				if ( '' === $fields['phone'] && self::resolve_flag( $settings, 'newsman_is_phone' ) ) {
					$fields['phone'] = $cssid;
				}
			}
		}

		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				self::walk_atomic_inputs( $child, $fields );
			}
		}
	}

	/**
	 * Resolve a boolean setting that may be stored legacy ('yes'/'') or atomic (array w/ 'value').
	 *
	 * @param array  $settings Element settings.
	 * @param string $key      Setting key.
	 * @return bool
	 */
	protected static function resolve_flag( $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return false;
		}
		$value = $settings[ $key ];
		if ( is_array( $value ) ) {
			$value = isset( $value['value'] ) ? $value['value'] : false;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return 'yes' === $value || '1' === $value || 'true' === $value;
		}
		return (bool) $value;
	}

	/**
	 * Resolve a string setting that may be stored legacy or atomic-shaped.
	 *
	 * @param array  $settings Element settings.
	 * @param string $key      Setting key.
	 * @return string
	 */
	protected static function resolve_string( $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return '';
		}
		$value = $settings[ $key ];
		if ( is_array( $value ) && isset( $value['value'] ) ) {
			$value = $value['value'];
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
