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
use Newsman\ContactForm7\FormPanel as CF7FormPanel;
use Newsman\Export\V1\ApiV1Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscriber retriever backed by Contact Form 7 submissions persisted by Flamingo.
 *
 * Reads from the `flamingo_inbound` CPT scoped by the `flamingo_inbound_channel`
 * taxonomy term that matches the CF7 form's slug. Per-row field values live in
 * `wp_postmeta` under `_field_<formTagName>` keys; the IP address lives inside
 * the serialized `_meta` array (`remote_ip`).
 *
 * Filters supported: `created_at`, `subscriber_id`(s), `email`, `firstname`, `lastname`.
 * Sorts supported: `created_at`, `email`, `subscriber_id`. `customer_id`/`modified_at`
 * throw 1010. Rows are deduped by email, latest submission wins. Phone is gated by
 * `Remarketing\Config::is_send_telephone()`; IP prefers the per-submission `remote_ip`
 * with a fallback to the configured server IP.
 *
 * @class \Newsman\Export\Retriever\SubscribersContactForm7
 */
class SubscribersContactForm7 extends AbstractRetriever implements RetrieverInterface {
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
		if ( ! class_exists( '\WPCF7_ContactForm' ) || ! class_exists( '\Flamingo_Inbound_Message' ) ) {
			return false;
		}
		$config  = Config::init();
		$form_id = (int) $config->get_contact_form_7_export_form_id( $blog_id );
		if ( $form_id <= 0 ) {
			return false;
		}
		$contact_form = \WPCF7_ContactForm::get_instance( $form_id );
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'prop' ) ) {
			return false;
		}
		$prop = (array) $contact_form->prop( CF7FormPanel::PROPERTY );
		return ! empty( $prop['enable'] ) && ! empty( $prop['newsletter_form'] );
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
				throw new \Exception( esc_html( $unsupported . ' is not supported by the Contact Form 7 subscriber retriever.' ) );
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

		$form_post_id = (int) $this->config->get_contact_form_7_export_form_id( $blog_id );
		$contact_form = \WPCF7_ContactForm::get_instance( $form_post_id );
		if ( ! is_object( $contact_form ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			if ( isset( $data['_v1_filter_fields'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new ApiV1Exception( 3002, 'Subscriber export not enabled', 500 );
			}
			throw new \Exception( 'Configured Contact Form 7 form could not be resolved.' );
		}

		$prop      = (array) $contact_form->prop( CF7FormPanel::PROPERTY );
		$form_slug = get_post_field( 'post_name', $form_post_id );

		$email_field     = isset( $prop['email_field'] ) ? trim( (string) $prop['email_field'] ) : '';
		$firstname_field = isset( $prop['firstname_field'] ) ? trim( (string) $prop['firstname_field'] ) : '';
		$lastname_field  = isset( $prop['lastname_field'] ) ? trim( (string) $prop['lastname_field'] ) : '';
		$phone_field     = isset( $prop['phone_field'] ) ? trim( (string) $prop['phone_field'] ) : '';

		if ( '' === $email_field || '' === $form_slug ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			if ( isset( $data['_v1_filter_fields'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new ApiV1Exception( 3002, 'Subscriber export not enabled', 500 );
			}
			throw new \Exception( 'CF7 form has no email field configured or is missing a slug.' );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$joins  = $wpdb->prepare(
			"JOIN {$wpdb->postmeta} v_email ON v_email.post_id = p.ID AND v_email.meta_key = %s",
			'_field_' . $email_field
		);
		$select = 'v_email.meta_value AS email, MAX(p.ID) AS ID, MAX(p.post_date) AS post_date, MAX(v_meta.meta_value) AS meta_serialized';

		$joins .= " LEFT JOIN {$wpdb->postmeta} v_meta ON v_meta.post_id = p.ID AND v_meta.meta_key = '_meta'";

		if ( '' !== $firstname_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->postmeta} v_fn ON v_fn.post_id = p.ID AND v_fn.meta_key = %s",
				'_field_' . $firstname_field
			);
			$select .= ', MAX(v_fn.meta_value) AS firstname';
		} else {
			$select .= ", '' AS firstname";
		}
		if ( '' !== $lastname_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->postmeta} v_ln ON v_ln.post_id = p.ID AND v_ln.meta_key = %s",
				'_field_' . $lastname_field
			);
			$select .= ', MAX(v_ln.meta_value) AS lastname';
		} else {
			$select .= ", '' AS lastname";
		}
		if ( '' !== $phone_field ) {
			$joins  .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->postmeta} v_phone ON v_phone.post_id = p.ID AND v_phone.meta_key = %s",
				'_field_' . $phone_field
			);
			$select .= ', MAX(v_phone.meta_value) AS phone';
		} else {
			$select .= ", '' AS phone";
		}

		$joins .= " JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
		$joins .= " JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id";
		$joins .= " JOIN {$wpdb->terms} t ON t.term_id = tt.term_id";

		$where  = "p.post_type = 'flamingo_inbound' AND p.post_status NOT IN ('trash','auto-draft')";
		$where .= " AND tt.taxonomy = 'flamingo_inbound_channel'";
		$where .= $wpdb->prepare( ' AND t.slug = %s', $form_slug );
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
					$where .= ' AND p.ID ' . $operator . ' (' . implode( ',', $ids ) . ')';
				} else {
					$where .= $wpdb->prepare( ' AND p.ID ' . $operator . ' %d', (int) $value );
				}
			} elseif ( 'post_date' === $field ) {
				$where .= $wpdb->prepare( ' AND p.post_date ' . $operator . ' %s', (string) $value );
			} elseif ( 'email' === $field ) {
				$where .= $wpdb->prepare( ' AND v_email.meta_value ' . $operator . ' %s', (string) $value );
			} elseif ( 'firstname' === $field && '' !== $firstname_field ) {
				$where .= $wpdb->prepare( ' AND v_fn.meta_value ' . $operator . ' %s', (string) $value );
			} elseif ( 'lastname' === $field && '' !== $lastname_field ) {
				$where .= $wpdb->prepare( ' AND v_ln.meta_value ' . $operator . ' %s', (string) $value );
			}
		}

		// The unique ID tiebreaker keeps LIMIT pages stable between requests
		// when many rows share the same aggregated date.
		$order_clause = 'ORDER BY post_date DESC, ID DESC';
		if ( isset( $processed['sort'] ) ) {
			$order_dir   = ( 'DESC' === $processed['order'] ) ? 'DESC' : 'ASC';
			$sort_column = (string) $processed['sort'];
			if ( in_array( $sort_column, array( 'post_date', 'email', 'ID' ), true ) ) {
				$order_clause = 'ORDER BY ' . $sort_column . ' ' . $order_dir;
				if ( 'ID' !== $sort_column ) {
					$order_clause .= ', ID ' . $order_dir;
				}
			}
		}

		$sql = "SELECT {$select}
			    FROM {$wpdb->posts} p
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
	 * @param array    $row     Aggregated submission row.
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
			'source'          => 'Contact Form 7 submissions (Flamingo)',
		);

		if ( $this->remarketing_config->is_send_telephone() && ! empty( $row['phone'] ) ) {
			$cleaned = $this->clean_phone( (string) $row['phone'] );
			if ( '' !== $cleaned ) {
				$out['phone'] = $cleaned;
			}
		}

		$ip = $this->resolve_ip_from_meta_blob( isset( $row['meta_serialized'] ) ? (string) $row['meta_serialized'] : '' );
		if ( '' === $ip ) {
			$server_ip = $this->config->get_server_ip( $blog_id );
			if ( ! empty( $server_ip ) && \Newsman\User\HostIpAddress::NOT_FOUND !== $server_ip ) {
				$ip = $server_ip;
			}
		}
		if ( '' !== $ip ) {
			$out['ip'] = $ip;
		}

		return apply_filters( 'newsman_export_retriever_subscribers_contact_form_7_process_subscriber', $out, $row, $blog_id );
	}

	/**
	 * Extract the `remote_ip` field from Flamingo's serialized `_meta` blob.
	 *
	 * @param string $serialized Raw `_meta` value from postmeta.
	 * @return string IP or empty string when absent.
	 */
	protected function resolve_ip_from_meta_blob( $serialized ) {
		if ( '' === $serialized ) {
			return '';
		}
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Flamingo stores _meta as serialized PHP; allowed_classes=false prevents object injection.
		$meta = @unserialize( $serialized, array( 'allowed_classes' => false ) );
		if ( ! is_array( $meta ) ) {
			return '';
		}
		if ( ! empty( $meta['remote_ip'] ) && is_string( $meta['remote_ip'] ) ) {
			return $meta['remote_ip'];
		}
		return '';
	}
}
