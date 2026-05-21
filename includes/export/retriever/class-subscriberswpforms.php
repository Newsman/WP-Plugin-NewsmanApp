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
 * Subscriber retriever backed by WPForms Pro form entries.
 *
 * Reads from `wp_wpforms_entries` joined to `wp_wpforms_entry_fields` (one LEFT
 * JOIN per resolved field marker). The source form's per-field markers
 * (`settings.newsman_email_field`, `_firstname_field`, `_lastname_field`,
 * `_phone_field`) are decoded from the form's `post_content` JSON; each marker
 * value is the field id used as `wp_wpforms_entry_fields.field_id`.
 *
 * WPForms Lite does NOT persist entries — this retriever simply returns empty
 * when the entries table is absent. Statuses 'spam' and 'trash' are filtered
 * out. Rows are deduped by email, latest submission wins.
 *
 * Filters supported: `created_at`, `subscriber_id`(s), `email`, `firstname`, `lastname`.
 * Sorts supported: `created_at`, `email`, `subscriber_id`. `customer_id`/`modified_at`
 * throw 1010.
 *
 * @class \Newsman\Export\Retriever\SubscribersWPForms
 */
class SubscribersWPForms extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Default batch page size.
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Check that all preconditions for this source are still satisfied.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public static function is_eligible( $blog_id = null ) {
		// Use WPFORMS_VERSION (defined when the plugin loads its main file) as the
		// active-plugin signal. Don't check `post_type_exists('wpforms')` — that
		// only returns true after the `init` hook fires, but the Newsman API endpoint
		// hooks `init` itself at the same priority, so the wpforms post type may not
		// be registered yet when this eligibility check runs.
		if ( ! defined( 'WPFORMS_VERSION' ) ) {
			return false;
		}

		$config  = Config::init();
		$form_id = (int) $config->get_wpforms_export_form_id( $blog_id );
		if ( $form_id <= 0 ) {
			return false;
		}

		$prop = self::resolve_form_settings( $form_id );
		if ( null === $prop ) {
			return false;
		}
		if ( '1' !== (string) $prop['newsman_enable'] || '1' !== (string) $prop['newsman_newsletter_form'] ) {
			return false;
		}
		return '' !== (string) $prop['newsman_email_field'];
	}

	/**
	 * Allowed v1 filter fields - internal column mapping.
	 *
	 * @return array
	 */
	public function get_where_parameters_mapping() {
		return array(
			'created_at'     => array(
				'field' => 'post_date',
				'type'  => 'string',
			),
			'subscriber_id'  => array(
				'field' => 'ID',
				'type'  => 'int',
			),
			'subscriber_ids' => array(
				'field'    => 'ID',
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
			'created_at'    => 'post_date',
			'email'         => 'email',
			'subscriber_id' => 'ID',
		);
	}

	/**
	 * Reject unsupported v1 filters with a clean 1010.
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
				throw new \Exception( esc_html( $unsupported . ' is not supported by the WPForms subscriber retriever.' ) );
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

		$form_id = (int) $this->config->get_wpforms_export_form_id( $blog_id );
		$prop    = self::resolve_form_settings( $form_id );
		if ( null === $prop || '' === (string) $prop['newsman_email_field'] ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			if ( isset( $data['_v1_filter_fields'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new ApiV1Exception( 3002, 'Subscriber export not enabled', 500 );
			}
			throw new \Exception( 'Configured WPForms form could not be resolved or has no email field.' );
		}

		// WPForms Lite does not create the entries tables; return empty rather than
		// fail the request loudly.
		$entries_table = $wpdb->prefix . 'wpforms_entries';
		$fields_table  = $wpdb->prefix . 'wpforms_entry_fields';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		if ( empty( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $entries_table ) ) ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			return array();
		}

		$email_field     = (string) $prop['newsman_email_field'];
		$firstname_field = (string) $prop['newsman_firstname_field'];
		$lastname_field  = (string) $prop['newsman_lastname_field'];
		$phone_field     = (string) $prop['newsman_phone_field'];

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$joins  = $wpdb->prepare(
			"JOIN {$fields_table} v_email ON v_email.entry_id = e.entry_id AND v_email.field_id = %s",
			$email_field
		);
		$select = 'v_email.value AS email, MAX(e.entry_id) AS ID, MAX(e.date) AS post_date, MAX(e.ip_address) AS ip_address';

		if ( '' !== $firstname_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$fields_table} v_fn ON v_fn.entry_id = e.entry_id AND v_fn.field_id = %s",
				$firstname_field
			);
			$select .= ', MAX(v_fn.value) AS firstname';
		} else {
			$select .= ", '' AS firstname";
		}
		if ( '' !== $lastname_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$fields_table} v_ln ON v_ln.entry_id = e.entry_id AND v_ln.field_id = %s",
				$lastname_field
			);
			$select .= ', MAX(v_ln.value) AS lastname';
		} else {
			$select .= ", '' AS lastname";
		}
		if ( '' !== $phone_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$fields_table} v_phone ON v_phone.entry_id = e.entry_id AND v_phone.field_id = %s",
				$phone_field
			);
			$select .= ', MAX(v_phone.value) AS phone';
		} else {
			$select .= ", '' AS phone";
		}

		$where  = $wpdb->prepare( 'e.form_id = %d', $form_id );
		$where .= " AND e.status NOT IN ('spam','trash')";
		$where .= " AND v_email.value <> ''";

		foreach ( $processed['filters'] as $filter ) {
			$operator = $this->get_expressions_definition()[ $filter['operator'] ];
			$value    = $filter['value'];
			$field    = $filter['field'];

			if ( 'ID' === $field ) {
				if ( in_array( $filter['operator'], array( 'in', 'nin' ), true ) ) {
					$ids = array_filter( array_map( 'intval', (array) $value ) );
					if ( empty( $ids ) ) {
						$ids = array( 0 );
					}
					$where .= ' AND e.entry_id ' . $operator . ' (' . implode( ',', $ids ) . ')';
				} else {
					$where .= $wpdb->prepare( ' AND e.entry_id ' . $operator . ' %d', (int) $value );
				}
			} elseif ( 'post_date' === $field ) {
				$where .= $wpdb->prepare( ' AND e.date ' . $operator . ' %s', (string) $value );
			} elseif ( 'email' === $field ) {
				$where .= $wpdb->prepare( ' AND v_email.value ' . $operator . ' %s', (string) $value );
			} elseif ( 'firstname' === $field && '' !== $firstname_field ) {
				$where .= $wpdb->prepare( ' AND v_fn.value ' . $operator . ' %s', (string) $value );
			} elseif ( 'lastname' === $field && '' !== $lastname_field ) {
				$where .= $wpdb->prepare( ' AND v_ln.value ' . $operator . ' %s', (string) $value );
			}
		}

		$order_clause = 'ORDER BY post_date DESC';
		if ( isset( $processed['sort'] ) ) {
			$order_dir   = ( 'DESC' === $processed['order'] ) ? 'DESC' : 'ASC';
			$sort_column = (string) $processed['sort'];
			if ( in_array( $sort_column, array( 'post_date', 'email', 'ID' ), true ) ) {
				$order_clause = 'ORDER BY ' . $sort_column . ' ' . $order_dir;
			}
		}

		$sql = "SELECT {$select}
				FROM {$entries_table} e
				{$joins}
				WHERE {$where}
				GROUP BY v_email.value
				{$order_clause}
				LIMIT %d OFFSET %d";

		$sql  = $wpdb->prepare( $sql, (int) $processed['limit'], (int) $processed['start'] );
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is built with $wpdb->prepare().
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
	 * Compose the subscriber row from an aggregated query result.
	 *
	 * @param array    $row     Aggregated entry row.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	protected function build_subscriber_row( $row, $blog_id ) {
		$out = array(
			'subscriber_id'   => isset( $row['ID'] ) ? (int) $row['ID'] : 0,
			'email'           => isset( $row['email'] ) ? (string) $row['email'] : '',
			'firstname'       => isset( $row['firstname'] ) ? (string) $row['firstname'] : '',
			'lastname'        => isset( $row['lastname'] ) ? (string) $row['lastname'] : '',
			'date_subscribed' => isset( $row['post_date'] ) ? (string) $row['post_date'] : '',
			'confirmed'       => 1,
			'source'          => 'WPForms entries',
		);

		if ( $this->remarketing_config->is_send_telephone() && ! empty( $row['phone'] ) ) {
			$cleaned = $this->clean_phone( (string) $row['phone'] );
			if ( '' !== $cleaned ) {
				$out['phone'] = $cleaned;
			}
		}

		$ip = isset( $row['ip_address'] ) ? trim( (string) $row['ip_address'] ) : '';
		if ( '' === $ip ) {
			$server_ip = $this->config->get_server_ip( $blog_id );
			if ( ! empty( $server_ip ) && \Newsman\User\HostIpAddress::NOT_FOUND !== $server_ip ) {
				$ip = $server_ip;
			}
		}
		if ( '' !== $ip ) {
			$out['ip'] = $ip;
		}

		return apply_filters( 'newsman_export_retriever_subscribers_wpforms_process_subscriber', $out, $row, $blog_id );
	}

	/**
	 * Decode a WPForms form's persisted Newsman settings from its post_content JSON.
	 *
	 * Returns null when the post does not exist, is not a `wpforms` post, or the
	 * JSON is malformed.
	 *
	 * @param int $form_post_id WPForms form post ID.
	 * @return array|null `[ newsman_enable, newsman_newsletter_form, newsman_email_field, newsman_firstname_field, newsman_lastname_field, newsman_phone_field ]`
	 */
	protected static function resolve_form_settings( $form_post_id ) {
		$form_post_id = (int) $form_post_id;
		if ( $form_post_id <= 0 ) {
			return null;
		}
		$post = get_post( $form_post_id );
		if ( ! $post || 'wpforms' !== $post->post_type ) {
			return null;
		}
		$data = json_decode( (string) $post->post_content, true );
		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return null;
		}
		$s = $data['settings'];
		return array(
			'newsman_enable'          => isset( $s['newsman_enable'] ) ? (string) $s['newsman_enable'] : '',
			'newsman_newsletter_form' => isset( $s['newsman_newsletter_form'] ) ? (string) $s['newsman_newsletter_form'] : '',
			'newsman_email_field'     => isset( $s['newsman_email_field'] ) ? (string) $s['newsman_email_field'] : '',
			'newsman_firstname_field' => isset( $s['newsman_firstname_field'] ) ? (string) $s['newsman_firstname_field'] : '',
			'newsman_lastname_field'  => isset( $s['newsman_lastname_field'] ) ? (string) $s['newsman_lastname_field'] : '',
			'newsman_phone_field'     => isset( $s['newsman_phone_field'] ) ? (string) $s['newsman_phone_field'] : '',
		);
	}
}
