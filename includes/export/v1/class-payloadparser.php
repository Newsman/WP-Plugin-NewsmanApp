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

namespace Newsman\Export\V1;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses and validates the API v1 JSON request payload.
 *
 * Maps the JSON `method` field to an internal retriever code and flattens
 * `params.filter` into the top-level data array so the existing retrievers
 * can consume it without modification.
 *
 * @class \Newsman\Export\V1\PayloadParser
 */
class PayloadParser {
	/**
	 * Base mapping from API v1 method name to internal retriever code.
	 *
	 * Use get_method_map() instead of accessing this directly.
	 *
	 * @var array
	 */
	private static $method_map = array(
		'customer.list'             => 'customers',
		'subscriber.list'           => 'subscribers',
		'subscriber.subscribe'      => 'subscriber-subscribe',
		'subscriber.unsubscribe'    => 'subscriber-unsubscribe',
		'product.list'              => 'products-feed',
		'order.list'                => 'orders',
		'coupon.create'             => 'coupons',
		'custom.sql'                => 'custom-sql',
		'platform.name'             => 'platform-name',
		'platform.version'          => 'platform-version',
		'platform.language'         => 'platform-language',
		'platform.language_version' => 'platform-language-version',
		'integration.name'          => 'integration-name',
		'integration.version'       => 'integration-version',
		'server.ip'                 => 'server-ip',
		'server.cloudflare'         => 'server-cloudflare',
		'sql.name'                  => 'sql-name',
		'sql.version'               => 'sql-version',
		'refresh.remarketing'       => 'refresh-remarketing',
	);

	/**
	 * Methods that require WooCommerce to be active.
	 *
	 * @var string[]
	 */
	private static $woocommerce_methods = array(
		'customer.list',
		'product.list',
		'order.list',
		'coupon.create',
	);

	/**
	 * Return the active method map, excluding methods whose dependencies are
	 * not met (e.g. WooCommerce methods when WooCommerce is not installed).
	 *
	 * Applies the `newsman_api_v1_method_map` filter so third-party code can
	 * add, remove or remap methods.
	 *
	 * @return array
	 */
	public static function get_method_map() {
		$map = self::$method_map;

		$woo = new \Newsman\Util\WooCommerceExist();
		if ( ! $woo->exist() ) {
			foreach ( self::$woocommerce_methods as $method ) {
				unset( $map[ $method ] );
			}
		}

		/**
		 * Filter the API v1 method map.
		 *
		 * @param array $map Method name => retriever code pairs.
		 */
		return apply_filters( 'newsman_api_v1_method_map', $map );
	}

	/**
	 * Determine whether the raw request body should be handled as an API v1 payload.
	 *
	 * Detection rules (either is sufficient):
	 * - Content-Type header contains "application/json"
	 * - Raw body starts with "{" (JSON object)
	 *
	 * @param string $raw_body     Raw HTTP request body.
	 * @param string $content_type Value of the Content-Type header.
	 * @return bool
	 */
	public function is_v1_payload( $raw_body, $content_type = '' ) {
		if ( ! empty( $content_type ) && strpos( $content_type, 'application/json' ) !== false ) {
			return true;
		}
		$trimmed = ltrim( (string) $raw_body );
		return ! empty( $trimmed ) && '{' === $trimmed[0];
	}

	/**
	 * Parse, validate and translate a JSON payload into a retriever code + flat data array.
	 *
	 * The returned array has two keys:
	 * - "code": internal retriever code string (e.g. "customers")
	 * - "data": flat key->value array ready to pass to Processor::process()
	 *
	 * Filters from `params.filter` are merged into the top-level data array so
	 * the existing AbstractRetriever::process_list_where_parameters() can read them
	 * by field name without any changes.
	 *
	 * @param string $raw_body Raw HTTP request body.
	 * @return array Array with keys 'code' (string) and 'data' (array).
	 * @throws ApiV1Exception On invalid payload, missing or unknown method.
	 */
	public function parse( $raw_body ) {
		$payload = json_decode( (string) $raw_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new ApiV1Exception( 1002, 'Invalid JSON payload', 400 );
		}

		if ( ! is_array( $payload ) || ! array_key_exists( 'method', $payload ) ) {
			throw new ApiV1Exception( 1003, 'Missing "method" parameter', 400 );
		}

		$method     = $payload['method'];
		$method_map = self::get_method_map();
		if ( ! isset( $method_map[ $method ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ApiV1Exception( 1004, 'Unknown method: ' . $method, 404 );
		}

		$params = isset( $payload['params'] ) ? $payload['params'] : array();
		if ( ! is_array( $params ) ) {
			throw new ApiV1Exception( 1005, 'Invalid "params" parameter', 400 );
		}

		// Flatten params.filter into the top-level data array.
		// The existing AbstractRetriever::process_list_where_parameters() reads filter
		// fields by their request name at the top level of the $data array, so
		// {"filter": {"created_at": {"from": "2025-01-01"}}} becomes
		// $data['created_at'] = ['from' => '2025-01-01'].
		$data          = $params;
		$filter_fields = array();
		if ( isset( $params['filter'] ) && is_array( $params['filter'] ) ) {
			foreach ( $params['filter'] as $field_name => $field_value ) {
				$data[ $field_name ] = $field_value;
				$filter_fields[]     = $field_name;
			}
		}
		unset( $data['filter'] );
		$data['_v1_filter_fields'] = $filter_fields;

		return array(
			'code' => $method_map[ $method ],
			'data' => $data,
		);
	}
}
