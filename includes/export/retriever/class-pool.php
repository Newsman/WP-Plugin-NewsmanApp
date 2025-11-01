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

use Newsman\Util\WooCommerceExist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Export Retriever Pool
 *
 * @class \Newsman\Export\Retriever\Pool
 */
class Pool {
	/**
	 * Configuration list of retriever
	 *
	 * @var array
	 */
	protected $retriever_list = array(
		'version'     => array(
			'code'             => 'version',
			'class'            => '\Newsman\Export\Retriever\Version',
			'only_woocommerce' => false,
		),
		'orders'      => array(
			'code'             => 'orders',
			'class'            => '\Newsman\Export\Retriever\Orders',
			'only_woocommerce' => true,
		),
		'products'    => array(
			'code'             => 'products',
			'class'            => '\Newsman\Export\Retriever\Products',
			'only_woocommerce' => true,
		),
		'customers'   => array(
			'code'             => 'customers',
			'class'            => '\Newsman\Export\Retriever\Customers',
			'only_woocommerce' => true,
		),
		'subscribers' => array(
			'code'             => 'subscribers',
			'class'            => '\Newsman\Export\Retriever\Subscribers',
			'only_woocommerce' => false,
		),
		'count'       => array(
			'code'             => 'count',
			'class'            => '\Newsman\Export\Retriever\Count',
			'only_woocommerce' => false,
		),
		'coupons'     => array(
			'code'             => 'coupons',
			'class'            => '\Newsman\Export\Retriever\Coupons',
			'only_woocommerce' => true,
		),
		'wordpress'   => array(
			'code'             => 'wordpress',
			'class'            => '\Newsman\Export\Retriever\SubscribersWordpress',
			'only_woocommerce' => false,
		),
		'woocommerce' => array(
			'code'             => 'woocommerce',
			'class'            => '\Newsman\Export\Retriever\SubscribersWoocommerce',
			'only_woocommerce' => true,
		),
	);

	/**
	 * Retriever instances list
	 *
	 * @var array
	 */
	protected $retriever_instances = array();

	/**
	 * Retriever factory
	 *
	 * @var RetrieverFactory
	 */
	protected $factory;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->factory = new RetrieverFactory();
	}

	/**
	 * Get retriever list
	 *
	 * @return array
	 */
	public function get_retriever_list() {
		return apply_filters( 'newsman_export_retriever_pool_get_retriever_list', $this->retriever_list );
	}

	/**
	 * Set retrievers list
	 *
	 * @param array $retriever_list List with new retrievers.
	 * @return self
	 */
	public function set_retriever_list( $retriever_list ) {
		$this->retriever_list = $retriever_list;
		return $this;
	}

	/**
	 * Get retriever by code instantiated
	 *
	 * @param string $code Code of retriever.
	 * @param array  $data Request data parameters.
	 * @return RetrieverInterface
	 * @throws \InvalidArgumentException Throws invalid argument code retriever exception.
	 */
	public function get_retriever_by_code( $code, $data ) {
		$code = strtolower( $code );

		if ( 'cron' === $code ) {
			$code = $data['method'];
		}

		$exist  = new WooCommerceExist();
		$is_woo = $exist->exist();

		if ( isset( $this->retriever_instances[ $code ] ) ) {
			return $this->retriever_instances[ $code ];
		}

		foreach ( $this->get_retriever_list() as $retriever ) {
			if ( $retriever['code'] === $code ) {
				if ( empty( $retriever['class'] ) ) {
					throw new \InvalidArgumentException( 'The parameter "class" is missing.' );
				}

				if ( ! $is_woo && $retriever['only_woocommerce'] ) {
					throw new \InvalidArgumentException( 'Export allowed only in WooCommerce.' );
				}

				$this->retriever_instances[ $code ] = $this->factory->create( $retriever['class'] );
				break;
			}
		}

		if ( ! isset( $this->retriever_instances[ $code ] ) ) {
			throw new \InvalidArgumentException( 'The parameter "code" is missing.' );
		}

		return $this->retriever_instances[ $code ];
	}
}
