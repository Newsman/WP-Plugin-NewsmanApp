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
 * Subscriber retriever backed by Gravity Forms entries.
 *
 * Reads from `wp_gf_entry` joined to `wp_gf_entry_meta` (one LEFT JOIN per
 * resolved field marker, keyed on `meta_key`). The source form's persisted
 * field markers (`newsman_email_field`, `_firstname_field`, `_lastname_field`,
 * `_phone_field`) live inside the form's `display_meta` JSON in
 * `wp_gf_form_meta`; the values they hold are GF field ids (numeric for simple
 * fields, dotted like `1.3` for compound-field sub-inputs).
 *
 * Statuses 'spam' and 'trash' are filtered out. Rows are deduped by email,
 * latest submission wins. Phone is gated by `Remarketing\Config::is_send_telephone()`;
 * IP comes from `wp_gf_entry.ip` directly.
 *
 * Filters supported: `created_at`, `subscriber_id`(s), `email`, `firstname`, `lastname`.
 * Sorts supported: `created_at`, `email`, `subscriber_id`. `customer_id`/`modified_at`
 * throw 1010.
 *
 * @class \Newsman\Export\Retriever\SubscribersGravityForms
 */
class SubscribersGravityForms extends AbstractRetriever implements RetrieverInterface {
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
		if ( ! defined( 'GF_MIN_WP_VERSION' ) ) {
			return false;
		}

		$config  = Config::init();
		$form_id = (int) $config->get_gravity_forms_export_form_id( $blog_id );
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
				throw new \Exception( esc_html( $unsupported . ' is not supported by the Gravity Forms subscriber retriever.' ) );
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

		$form_id = (int) $this->config->get_gravity_forms_export_form_id( $blog_id );
		$prop    = self::resolve_form_settings( $form_id );
		if ( null === $prop || '' === (string) $prop['newsman_email_field'] ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			if ( isset( $data['_v1_filter_fields'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new ApiV1Exception( 3002, 'Subscriber export not enabled', 500 );
			}
			throw new \Exception( 'Configured Gravity Forms form could not be resolved or has no email field.' );
		}

		$entries_table = $wpdb->prefix . 'gf_entry';
		$meta_table    = $wpdb->prefix . 'gf_entry_meta';

		$email_field     = (string) $prop['newsman_email_field'];
		$firstname_field = (string) $prop['newsman_firstname_field'];
		$lastname_field  = (string) $prop['newsman_lastname_field'];
		$phone_field     = (string) $prop['newsman_phone_field'];

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$joins  = $wpdb->prepare(
			"JOIN {$meta_table} v_email ON v_email.entry_id = e.id AND v_email.meta_key = %s",
			$email_field
		);
		$select = 'v_email.meta_value AS email, MAX(e.id) AS ID, MAX(e.date_created) AS post_date, MAX(e.ip) AS ip_address';

		if ( '' !== $firstname_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$meta_table} v_fn ON v_fn.entry_id = e.id AND v_fn.meta_key = %s",
				$firstname_field
			);
			$select .= ', MAX(v_fn.meta_value) AS firstname';
		} else {
			$select .= ", '' AS firstname";
		}
		if ( '' !== $lastname_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$meta_table} v_ln ON v_ln.entry_id = e.id AND v_ln.meta_key = %s",
				$lastname_field
			);
			$select .= ', MAX(v_ln.meta_value) AS lastname';
		} else {
			$select .= ", '' AS lastname";
		}
		if ( '' !== $phone_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$meta_table} v_phone ON v_phone.entry_id = e.id AND v_phone.meta_key = %s",
				$phone_field
			);
			$select .= ', MAX(v_phone.meta_value) AS phone';
		} else {
			$select .= ", '' AS phone";
		}

		$where  = $wpdb->prepare( 'e.form_id = %d', $form_id );
		$where .= " AND e.status NOT IN ('spam','trash')";
		$where .= " AND v_email.meta_value <> ''";

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
					$where .= ' AND e.id ' . $operator . ' (' . implode( ',', $ids ) . ')';
				} else {
					$where .= $wpdb->prepare( ' AND e.id ' . $operator . ' %d', (int) $value );
				}
			} elseif ( 'post_date' === $field ) {
				$where .= $wpdb->prepare( ' AND e.date_created ' . $operator . ' %s', (string) $value );
			} elseif ( 'email' === $field ) {
				$where .= $wpdb->prepare( ' AND v_email.meta_value ' . $operator . ' %s', (string) $value );
			} elseif ( 'firstname' === $field && '' !== $firstname_field ) {
				$where .= $wpdb->prepare( ' AND v_fn.meta_value ' . $operator . ' %s', (string) $value );
			} elseif ( 'lastname' === $field && '' !== $lastname_field ) {
				$where .= $wpdb->prepare( ' AND v_ln.meta_value ' . $operator . ' %s', (string) $value );
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
				GROUP BY v_email.meta_value
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
			'source'          => 'Gravity Forms entries',
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

		return apply_filters( 'newsman_export_retriever_subscribers_gravity_forms_process_subscriber', $out, $row, $blog_id );
	}

	/**
	 * Decode a Gravity Forms form's persisted Newsman settings from its
	 * `display_meta` JSON.
	 *
	 * Returns null when the form does not exist or the JSON is malformed.
	 *
	 * @param int $form_id Gravity Forms form id.
	 * @return array|null `[ newsman_enable, newsman_newsletter_form, newsman_email_field, newsman_firstname_field, newsman_lastname_field, newsman_phone_field ]`
	 */
	protected static function resolve_form_settings( $form_id ) {
		global $wpdb;
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT display_meta FROM {$wpdb->prefix}gf_form_meta WHERE form_id = %d", $form_id ) );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		return array(
			'newsman_enable'          => isset( $data['newsman_enable'] ) ? (string) $data['newsman_enable'] : '',
			'newsman_newsletter_form' => isset( $data['newsman_newsletter_form'] ) ? (string) $data['newsman_newsletter_form'] : '',
			'newsman_email_field'     => isset( $data['newsman_email_field'] ) ? (string) $data['newsman_email_field'] : '',
			'newsman_firstname_field' => isset( $data['newsman_firstname_field'] ) ? (string) $data['newsman_firstname_field'] : '',
			'newsman_lastname_field'  => isset( $data['newsman_lastname_field'] ) ? (string) $data['newsman_lastname_field'] : '',
			'newsman_phone_field'     => isset( $data['newsman_phone_field'] ) ? (string) $data['newsman_phone_field'] : '',
		);
	}
}
