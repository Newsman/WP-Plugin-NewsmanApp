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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Users
 *
 * @class \Newsman\Export\Retriever\Users
 */
class Users extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Allowed rows to export
	 *
	 * @var array
	 */
	protected $allowed_roles = array(
		'subscriber',
		'customer',
	);

	/**
	 * Process users retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws \Exception On errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		if ( empty( $data['wp_newsman_internal_role'] ) ) {
			throw new \Exception( 'wp_newsman_internal_role is empty!' );
		}
		$role = $data['wp_newsman_internal_role'];
		if ( ! in_array( $role, $this->get_allowed_roles(), true ) ) {
			/* translators: 1: User role */
			throw new \Exception(
				sprintf(
					/* translators: 1: User role */
					esc_html__( 'wp_newsman_internal_role %s is not allowed!', 'newsman' ),
					esc_html( $role )
				)
			);
		}

		$data['default_page_size'] = self::DEFAULT_PAGE_SIZE;

		$processed_params = $this->process_list_parameters( $data, $blog_id );

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: User role, 2: Batch start, 3: Batch end, 4: WP blog ID */
				esc_html__( 'Export %1$s %2$d, %3$d, blog ID %4$s', 'newsman' ),
				$role,
				$processed_params['start'],
				$processed_params['limit'],
				$blog_id
			)
		);

		$args = array(
			'role'   => $role,
			'offset' => $processed_params['start'],
			'number' => $processed_params['limit'],
		);

		if ( isset( $processed_params['sort'] ) ) {
			$args['orderby'] = $processed_params['sort'];
			$args['order']   = $processed_params['order'];
		}

		$meta_query = array();
		$date_query = array();

		foreach ( $processed_params['filters'] as $filter ) {
			$field    = $filter['field'];
			$operator = $this->get_expressions_definition()[ $filter['operator'] ];
			$value    = $filter['value'];

			if ( 'user_registered' === $field ) {
				$date_query[] = array(
					'column'  => 'user_registered',
					'value'   => $value,
					'compare' => $operator,
				);
			} elseif ( 'ID' === $field ) {
				if ( 'in' === $filter['operator'] ) {
					$args['include'] = (array) $value;
				} elseif ( 'nin' === $filter['operator'] ) {
					$args['exclude'] = (array) $value;
				} elseif ( 'eq' === $filter['operator'] ) {
					$args['include'] = array( $value );
				}
			} elseif ( 'user_email' === $field ) {
				$args['search']         = $value;
				$args['search_columns'] = array( 'user_email' );
			} else {
				$meta_query[] = array(
					'key'     => $field,
					'value'   => $value,
					'compare' => $operator,
				);
			}
		}

		if ( ! empty( $meta_query ) ) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = $meta_query;
		}
		if ( ! empty( $date_query ) ) {
			$args['date_query'] = $date_query;
		}

		$args = apply_filters(
			'newsman_export_retriever_users_process_fetch',
			$args,
			array(
				'data'    => $data,
				'blog_id' => $blog_id,
			)
		);

		$customers = get_users( $args );

		if ( empty( $customers ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			return array();
		}

		$result = array();
		foreach ( $customers as $customer ) {
			try {
				$result[] = $this->process_customer( $customer, $role, $blog_id );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Exported %1$s %2$d, %3$d, blog ID %4$s', 'newsman' ),
				$role,
				$processed_params['start'],
				$processed_params['limit'],
				$blog_id
			)
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Process list parameters
	 *
	 * @param array    $data Data.
	 * @param int|null $blog_id WP blog ID.
	 * @return array
	 * @throws \Exception On errors.
	 */
	public function process_list_parameters( $data = array(), $blog_id = null ) {
		if ( isset( $data['modified_at'] ) ) {
			throw new \Exception( 'modified_at is not implemented for users.' );
		}

		return parent::process_list_parameters( $data, $blog_id );
	}

	/**
	 * Get allowed request parameters
	 *
	 * @return array
	 */
	public function get_where_parameters_mapping() {
		return array(
			'created_at'     => array(
				'field' => 'user_registered',
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
			'customer_id'    => array(
				'field' => 'ID',
				'type'  => 'int',
			),
			'customer_ids'   => array(
				'field'    => 'ID',
				'multiple' => true,
				'type'     => 'int',
			),
			'email'          => array(
				'field' => 'user_email',
				'type'  => 'string',
			),
			'firstname'      => array(
				'field' => 'first_name',
				'type'  => 'string',
			),
			'lastname'       => array(
				'field' => 'last_name',
				'type'  => 'string',
			),
		);
	}

	/**
	 * Get allowed sort fields
	 *
	 * @return array
	 */
	public function get_allowed_sort_fields() {
		return array(
			'email'         => 'user_email',
			'created_at'    => 'user_registered',
			'subscriber_id' => 'ID',
			'customer_id'   => 'ID',
		);
	}

	/**
	 * Process customer
	 *
	 * @param \WP_User|\WC_Customer $customer Customer instance.
	 * @param null|int              $role Role.
	 * @param null|int              $blog_id WP blog ID.
	 * @return array
	 */
	public function process_customer( $customer, $role, $blog_id = null ) {
		$data = get_user_meta( $customer->data->ID );

		$row = array(
			'subscriber_id' => $customer->data->ID,
			'email'         => $customer->data->user_email,
			'firstname'     => $data['first_name'][0],
			'lastname'      => $data['last_name'][0],
		);

		$telephone = $this->get_telphone_from_user_data( $data );
		if ( ! empty( $telephone ) ) {
			$row['phone'] = $telephone;
		}

		return apply_filters(
			'newsman_export_retriever_users_process_customer',
			$row,
			array(
				'customer' => $customer,
				'role'     => $role,
				'blog_id'  => $blog_id,
			)
		);
	}

	/**
	 * Get telephone from user data
	 *
	 * @param array $data User meta data.
	 * @return false|string
	 */
	public function get_telphone_from_user_data( $data ) {
		if ( ! $this->remarketing_config->is_send_telephone() ) {
			return false;
		}

		if ( ! empty( $data['billing_phone'] ) && ! empty( $data['billing_phone'][0] ) ) {
			return $this->clean_phone( $data['billing_phone'][0] );
		}
		return '';
	}

	/**
	 * Get telephone from a customer object
	 *
	 * @param \WC_Customer $customer User meta data.
	 * @return false|string
	 */
	public function get_telphone_from_customer( $customer ) {
		if ( ! $this->remarketing_config->is_send_telephone() ) {
			return false;
		}

		if ( $this->remarketing_config->is_send_telephone() && method_exists( $customer, 'get_billing_phone' ) ) {
			return $this->clean_phone( $customer->get_billing_phone() );
		}

		return '';
	}

	/**
	 * Get user ip from user data
	 *
	 * @param array    $data User meta data.
	 * @param null|int $blog_id WP blog ID.
	 * @return false|string
	 */
	public function get_user_ip_from_user_data( $data, $blog_id = null ) {
		$ip = '';
		$ip = apply_filters(
			'newsman_export_retriever_users_get_user_ip',
			$ip,
			array(
				'data'    => $data,
				'blog_id' => $blog_id,
			)
		);
		if ( ! empty( $ip ) ) {
			return $ip;
		}

		$server_ip = $this->config->get_server_ip( $blog_id );
		if ( ! empty( $server_ip ) && \Newsman\User\HostIpAddress::NOT_FOUND !== $server_ip ) {
			return $server_ip;
		}

		return '';
	}

	/**
	 * Get allowed roles
	 *
	 * @return array
	 */
	public function get_allowed_roles() {
		$roles = apply_filters( 'newsman_export_retriever_users_get_allowed_roles', $this->allowed_roles );
		$roles = array_map( 'sanitize_text_field', $roles );
		$roles = array_diff( $roles, array( 'administrator', 'admin', 'editor', 'author', 'contributor' ) );
		return $roles;
	}
}
