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
use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Abstract Retriever
 *
 * @class \Newsman\Export\Retriever\AbstractRetriever
 */
class AbstractRetriever {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Remarketing Config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Telephone util
	 *
	 * @var Telephone
	 */
	protected $telephone;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->logger             = Logger::init();
		$this->telephone          = new Telephone();
	}

	/**
	 * Is different WP blog than current
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_different_blog( $blog_id = null ) {
		if ( ! ( function_exists( 'is_multisite' ) && is_multisite() ) ) {
			return false;
		}

		$current_blog_id = get_current_blog_id();
		if ( ( null === $current_blog_id ) || ( null === $blog_id ) ) {
			return false;
		}
		return ( (int) $blog_id !== $current_blog_id );
	}

	/**
	 * Clean telephone string
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public function clean_phone( $phone ) {
		return $this->telephone->clean( $phone );
	}

	/**
	 * Process list parameters
	 *
	 * @param array    $data Data.
	 * @param int|null $blog_id WP blog ID.
	 * @return array
	 * @throws ApiV1Exception On invalid sort field in API v1 context.
	 */
	public function process_list_parameters( $data = array(), $blog_id = null ) {
		$params = $this->process_list_where_parameters( $data, $blog_id );

		$sort_found = false;
		if ( isset( $data['sort'] ) ) {
			$allowed_sort = $this->get_allowed_sort_fields();
			if ( isset( $allowed_sort[ $data['sort'] ] ) ) {
				$params['sort'] = $allowed_sort[ $data['sort'] ];
				$sort_found     = true;
			} elseif ( isset( $data['_v1_filter_fields'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new ApiV1Exception( 1008, 'Invalid sort field: ' . $data['sort'], 400 );
			}
		}
		$params['order'] = 'ASC';
		if ( isset( $data['order'] ) && strcasecmp( $data['order'], 'desc' ) === 0 ) {
			$params['order'] = 'DESC';
		}
		if ( ! $sort_found ) {
			// Without an explicit sort the underlying queries give no stable row
			// order between two pages of the same export (or order by a non-unique
			// date column). Paginated exports then repeat some rows and skip
			// others, so part of the catalog never reaches Newsman. Fall back to
			// the deterministic sort field of the retriever when it defines one.
			$default_sort = $this->get_default_sort_field();
			if ( ! empty( $default_sort ) ) {
				$params['sort']  = $default_sort;
				$params['order'] = 'ASC';
			} else {
				unset( $params['sort'] );
				unset( $params['order'] );
			}
		}

		if ( ! isset( $data['default_page_size'] ) ) {
			$data['default_page_size'] = 1000;
		}

		$params['start']             = ( ! empty( $data['start'] ) && $data['start'] > 0 ) ? (int) $data['start'] : 0;
		$params['limit']             = empty( $data['limit'] ) ? $data['default_page_size'] : (int) $data['limit'];
		$params['default_page_size'] = (int) $data['default_page_size'];

		return $params;
	}

	/**
	 * Process list where parameters
	 *
	 * @param array    $data Data.
	 * @param int|null $blog_id WP blog ID.
	 * @return array
	 * @throws ApiV1Exception On invalid filter field or operator in API v1 context.
	 */
	public function process_list_where_parameters( $data = array(), $blog_id = null ) {
		$blog_id;

		if ( ! empty( $data['_v1_filter_fields'] ) ) {
			$allowed_mapping = $this->get_where_parameters_mapping();
			foreach ( $data['_v1_filter_fields'] as $field ) {
				if ( ! isset( $allowed_mapping[ $field ] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					throw new ApiV1Exception( 1006, 'Invalid filter field: ' . $field, 400 );
				}
			}
		}

		$params = array( 'filters' => array() );

		$operators = array_keys( $this->get_expressions_definition() );

		foreach ( $this->get_where_parameters_mapping() as $request_name => $definition ) {
			if ( ! isset( $data[ $request_name ] ) ) {
				continue;
			}

			$field_name = $definition['field'];

			if ( is_array( $data[ $request_name ] ) && ! empty( $data[ $request_name ] ) && is_string( array_keys( $data[ $request_name ] )[0] ) ) {
				foreach ( $data[ $request_name ] as $operator => $value ) {
					if ( ! in_array( $operator, $operators, true ) ) {
						if ( isset( $data['_v1_filter_fields'] ) ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
							throw new ApiV1Exception( 1007, 'Invalid filter operator: ' . $operator, 400 );
						}
						continue;
					}

					$params['filters'][] = array(
						'field'    => $field_name,
						'operator' => $operator,
						'value'    => $value,
						'type'     => isset( $definition['type'] ) ? $definition['type'] : 'string',
					);
				}
			} elseif ( is_array( $data[ $request_name ] ) && isset( $definition['multiple'] ) && $definition['multiple'] ) {
				$value               = $data[ $request_name ];
				$params['filters'][] = array(
					'field'    => $field_name,
					'operator' => 'in',
					'value'    => $value,
					'type'     => isset( $definition['type'] ) ? $definition['type'] : 'string',
				);
			} else {
				$value               = $data[ $request_name ];
				$params['filters'][] = array(
					'field'    => $field_name,
					'operator' => 'eq',
					'value'    => $value,
					'type'     => isset( $definition['type'] ) ? $definition['type'] : 'string',
				);
			}
		}

		return $params;
	}

	/**
	 * Get allowed request parameters
	 *
	 * @return array
	 */
	public function get_where_parameters_mapping() {
		return array();
	}

	/**
	 * Get the sort field used to keep paginated exports deterministic
	 *
	 * Returns an empty string when the retriever applies its own ordering or
	 * when it is not paginated.
	 *
	 * @return string
	 */
	public function get_default_sort_field() {
		return '';
	}

	/**
	 * Get allowed sort fields
	 *
	 * @return array
	 */
	public function get_allowed_sort_fields() {
		return array();
	}

	/**
	 * Apply the processed sort parameters to a WP/WC query args array.
	 *
	 * Sort field names must be valid orderby keys for the underlying query
	 * class; invalid keys are dropped silently (WP_Query even rejects 'id'
	 * because its allowed list only contains 'ID'). Non-unique sort columns
	 * get the unique ID column appended as a tie-breaker, otherwise rows
	 * sharing the sort value can swap pages between two requests of the same
	 * paginated export.
	 *
	 * @param array $args Query args.
	 * @param array $processed_params Processed list parameters.
	 * @return array
	 */
	protected function apply_sort_args( $args, $processed_params ) {
		if ( ! isset( $processed_params['sort'] ) ) {
			return $args;
		}

		if ( 'ID' === $processed_params['sort'] ) {
			$args['orderby'] = 'ID';
		} else {
			$args['orderby'] = array(
				$processed_params['sort'] => $processed_params['order'],
				'ID'                      => $processed_params['order'],
			);
		}
		$args['order'] = $processed_params['order'];

		return $args;
	}

	/**
	 * Get SQL conditions expression definition
	 *
	 * @return array
	 */
	public function get_expressions_definition() {
		return array(
			'eq'      => '=',
			'neq'     => '!=',
			'like'    => 'LIKE',
			'nlike'   => 'NOT LIKE',
			'in'      => 'IN',
			'nin'     => 'NOT IN',
			'is'      => 'IS',
			'notnull' => 'IS NOT NULL',
			'null'    => 'IS NULL',
			'gt'      => '>',
			'lt'      => '<',
			'gteq'    => '>=',
			'lteq'    => '<=',
			'from'    => '>=',
			'to'      => '<=',
		);
	}
}
