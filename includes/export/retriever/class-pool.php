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
 * Class Export Retriever Pool
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
		'platform-name'                => array(
			'code'             => 'platform-name',
			'class'            => '\Newsman\Export\Retriever\PlatformName',
			'only_woocommerce' => false,
		),
		'platform-version'             => array(
			'code'             => 'platform-version',
			'class'            => '\Newsman\Export\Retriever\PlatformVersion',
			'only_woocommerce' => false,
		),
		'platform-language'            => array(
			'code'             => 'platform-language',
			'class'            => '\Newsman\Export\Retriever\PlatformLanguage',
			'only_woocommerce' => false,
		),
		'platform-language-version'    => array(
			'code'             => 'platform-language-version',
			'class'            => '\Newsman\Export\Retriever\PlatformLanguageVersion',
			'only_woocommerce' => false,
		),
		'platform-has-products'        => array(
			'code'             => 'platform-has-products',
			'class'            => '\Newsman\Export\Retriever\PlatformHasProducts',
			'only_woocommerce' => false,
		),
		'integration-name'             => array(
			'code'             => 'integration-name',
			'class'            => '\Newsman\Export\Retriever\IntegrationName',
			'only_woocommerce' => false,
		),
		'integration-version'          => array(
			'code'             => 'integration-version',
			'class'            => '\Newsman\Export\Retriever\IntegrationVersion',
			'only_woocommerce' => false,
		),
		'server-ip'                    => array(
			'code'             => 'server-ip',
			'class'            => '\Newsman\Export\Retriever\ServerIp',
			'only_woocommerce' => false,
		),
		'server-cloudflare'            => array(
			'code'             => 'server-cloudflare',
			'class'            => '\Newsman\Export\Retriever\ServerCloudflare',
			'only_woocommerce' => false,
		),
		'sql-name'                     => array(
			'code'             => 'sql-name',
			'class'            => '\Newsman\Export\Retriever\SqlName',
			'only_woocommerce' => false,
		),
		'sql-version'                  => array(
			'code'             => 'sql-version',
			'class'            => '\Newsman\Export\Retriever\SqlVersion',
			'only_woocommerce' => false,
		),
		'orders'                       => array(
			'code'             => 'orders',
			'class'            => '\Newsman\Export\Retriever\Orders',
			'only_woocommerce' => true,
			'has_filters'      => true,
		),
		'products'                     => array(
			'code'             => 'products',
			'class'            => '\Newsman\Export\Retriever\Products',
			'only_woocommerce' => true,
		),
		'products-feed'                => array(
			'code'             => 'products-feed',
			'class'            => '\Newsman\Export\Retriever\ProductsFeed',
			'only_woocommerce' => true,
			'has_filters'      => true,
		),
		'customers'                    => array(
			'code'             => 'customers',
			'class'            => '\Newsman\Export\Retriever\Customers',
			'only_woocommerce' => true,
			'has_filters'      => true,
		),
		'subscriber-subscribe'         => array(
			'code'             => 'subscriber-subscribe',
			'class'            => '\Newsman\Export\Retriever\SubscriberSubscribe',
			'only_woocommerce' => false,
		),
		'subscriber-unsubscribe'       => array(
			'code'             => 'subscriber-unsubscribe',
			'class'            => '\Newsman\Export\Retriever\SubscriberUnsubscribe',
			'only_woocommerce' => false,
		),
		'subscribers'                  => array(
			'code'             => 'subscribers',
			'class'            => '\Newsman\Export\Retriever\Subscribers',
			'only_woocommerce' => false,
			'has_filters'      => true,
		),
		'subscribers-feed'             => array(
			'code'             => 'subscribers-wordpress-feed',
			'class'            => '\Newsman\Export\Retriever\SubscribersWordpressFeed',
			'only_woocommerce' => false,
			'has_filters'      => true,
		),
		'subscribers-woocommerce-feed' => array(
			'code'             => 'subscribers-woocommerce-feed',
			'class'            => '\Newsman\Export\Retriever\SubscribersWoocommerceFeed',
			'only_woocommerce' => true,
			'has_filters'      => true,
		),
		'count'                        => array(
			'code'             => 'count',
			'class'            => '\Newsman\Export\Retriever\Count',
			'only_woocommerce' => false,
		),
		'coupons'                      => array(
			'code'             => 'coupons',
			'class'            => '\Newsman\Export\Retriever\Coupons',
			'only_woocommerce' => true,
		),
		'wordpress'                    => array(
			'code'             => 'wordpress',
			'class'            => '\Newsman\Export\Retriever\SubscribersWordpress',
			'only_woocommerce' => false,
		),
		'woocommerce'                  => array(
			'code'             => 'woocommerce',
			'class'            => '\Newsman\Export\Retriever\SubscribersWoocommerce',
			'only_woocommerce' => true,
		),
		'send-orders'                  => array(
			'code'             => 'send-orders',
			'class'            => '\Newsman\Export\Retriever\SendOrders',
			'only_woocommerce' => true,
		),
		'custom-sql'                   => array(
			'code'             => 'custom-sql',
			'class'            => '\Newsman\Export\Retriever\CustomSql',
			'only_woocommerce' => false,
		),
		'refresh-remarketing'          => array(
			'code'             => 'refresh-remarketing',
			'class'            => '\Newsman\Export\Retriever\RefreshRemarketing',
			'only_woocommerce' => false,
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
	public function get_retriever_by_code( $code, $data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$code = strtolower( $code );

		// phpcs:disable Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.InlineComment.InvalidEndChar
		// if ( 'cron' === $code ) {
		// $code = $data['method'];
		// }
		// phpcs:enable Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.InlineComment.InvalidEndChar

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

	/**
	 * Get retrievers with filters
	 *
	 * @return array
	 */
	public function get_retrievers_with_filters() {
		$retrievers = array();

		foreach ( $this->get_retriever_list() as $retriever ) {
			if ( isset( $retriever['has_filters'] ) && $retriever['has_filters'] ) {
				$retrievers[] = $retriever;
			}
		}

		return $retrievers;
	}
}
