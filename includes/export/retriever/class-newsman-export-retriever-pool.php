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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Export Retriever Pool
 *
 * @class Newsman_Export_Retriever_Pool
 */
class Newsman_Export_Retriever_Pool {
	/**
	 * Configuration list of retriever
	 *
	 * @var array
	 */
	protected $retriever_list = array(
		'version'     => array(
			'code'  => 'version',
			'class' => 'Newsman_Export_Retriever_Version',
		),
		'orders'      => array(
			'code'  => 'orders',
			'class' => 'Newsman_Export_Retriever_Orders',
		),
		'products'    => array(
			'code'  => 'products',
			'class' => 'Newsman_Export_Retriever_Products',
		),
		'customers'   => array(
			'code'  => 'customers',
			'class' => 'Newsman_Export_Retriever_Customers',
		),
		'subscribers' => array(
			'code'  => 'subscribers',
			'class' => 'Newsman_Export_Retriever_Subscribers',
		),
		'count'       => array(
			'code'  => 'count',
			'class' => 'Newsman_Export_Retriever_Count',
		),
		'coupons'     => array(
			'code'  => 'coupons',
			'class' => 'Newsman_Export_Retriever_Coupons',
		),
		'wordpress'   => array(
			'code'  => 'wordpress',
			'class' => 'Newsman_Export_Retriever_SubscribersWordpress',
		),
		'woocommerce' => array(
			'code'  => 'woocommerce',
			'class' => 'Newsman_Export_Retriever_SubscribersWoocommerce',
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
	 * @var Newsman_Export_Retriever_Factory
	 */
	protected $factory;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->factory = new Newsman_Export_Retriever_Factory();
	}

	/**
	 * Get retriever list
	 *
	 * @return array
	 */
	public function get_retriever_list() {
		return $this->retriever_list;
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
	 * @return Newsman_Export_Retriever_Interface
	 * @throws \InvalidArgumentException Throws invalid argument code retriever exception.
	 */
	public function get_retriever_by_code( $code, $data ) {
		$code = strtolower( $code );

		if ( 'cron' === $code ) {
			$code = $data['method'];
		}

		if ( isset( $this->retriever_instances[ $code ] ) ) {
			return $this->retriever_instances[ $code ];
		}

		foreach ( $this->retriever_list as $retriever ) {
			if ( $retriever['code'] === $code ) {
				if ( empty( $retriever['class'] ) ) {
					throw new \InvalidArgumentException( 'The parameter "class" is missing.' );
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
