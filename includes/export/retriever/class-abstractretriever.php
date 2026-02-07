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
	 */
	public function process_list_parameters( $data = array(), $blog_id = null ) {
		$params = $this->process_list_where_parameters( $data, $blog_id );

		$sort_found = false;
		if ( isset( $data['sort'] ) ) {
			$allowed_sort = $this->get_allowed_sort_fields();
			if ( isset( $allowed_sort[ $data['sort'] ] ) ) {
				$params['sort'] = $allowed_sort[ $data['sort'] ];
				$sort_found     = true;
			}
		}
		$params['order'] = 'ASC';
		if ( isset( $data['order'] ) && strcasecmp( $data['order'], 'desc' ) === 0 ) {
			$params['order'] = 'DESC';
		}
		if ( ! $sort_found ) {
			unset( $params['sort'] );
			unset( $params['order'] );
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
	 */
	public function process_list_where_parameters( $data = array(), $blog_id = null ) {
		$blog_id;
		$params = array( 'filters' => array() );

		$operators = array_keys( $this->get_expressions_definition() );

		foreach ( $this->get_where_parameters_mapping() as $request_name => $definition ) {
			if ( ! isset( $data[ $request_name ] ) ) {
				continue;
			}

			$field_name = $definition['field'];

			if ( is_array( $data[ $request_name ] ) && ! empty( array_intersect( array_keys( $data[ $request_name ] ), $operators ) ) ) {
				foreach ( $data[ $request_name ] as $operator => $value ) {
					if ( ! in_array( $operator, $operators, true ) ) {
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
	 * Get allowed sort fields
	 *
	 * @return array
	 */
	public function get_allowed_sort_fields() {
		return array();
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
