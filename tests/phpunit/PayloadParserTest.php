<?php
/**
 * Tests for the API v1 payload parser.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Export\V1\ApiV1Exception;
use Newsman\Export\V1\PayloadParser;

/**
 * PayloadParser tests.
 */
class PayloadParserTest extends NewsmanTestCase {
	/**
	 * Parse a payload and return the ApiV1Exception it throws.
	 *
	 * @param string $raw_body Raw body.
	 * @return ApiV1Exception
	 */
	private function parse_expecting_exception( $raw_body ) {
		$parser = new PayloadParser();
		try {
			$parser->parse( $raw_body );
		} catch ( ApiV1Exception $e ) {
			return $e;
		}
		$this->fail( 'Expected ApiV1Exception was not thrown.' );
	}

	/**
	 * Detection by Content-Type header.
	 */
	public function test_is_v1_payload_by_content_type() {
		$parser = new PayloadParser();
		$this->assertTrue( $parser->is_v1_payload( '', 'application/json; charset=utf-8' ) );
		$this->assertTrue( $parser->is_v1_payload( 'not json', 'application/json' ) );
	}

	/**
	 * Detection by JSON object body.
	 */
	public function test_is_v1_payload_by_body() {
		$parser = new PayloadParser();
		$this->assertTrue( $parser->is_v1_payload( "  \n{\"method\":\"x\"}" ) );
		$this->assertFalse( $parser->is_v1_payload( 'newsman=orders.json', 'application/x-www-form-urlencoded' ) );
		$this->assertFalse( $parser->is_v1_payload( '' ) );
		$this->assertFalse( $parser->is_v1_payload( '[1,2]' ) );
	}

	/**
	 * Invalid JSON is error 1002 / HTTP 400.
	 */
	public function test_parse_rejects_invalid_json() {
		$e = $this->parse_expecting_exception( '{"method": ' );
		$this->assertSame( 1002, $e->get_error_code() );
		$this->assertSame( 400, $e->get_http_status() );
	}

	/**
	 * Missing method is error 1003.
	 */
	public function test_parse_rejects_missing_method() {
		$e = $this->parse_expecting_exception( '{"params": {}}' );
		$this->assertSame( 1003, $e->get_error_code() );
		$this->assertSame( 400, $e->get_http_status() );

		$e = $this->parse_expecting_exception( '"just a string"' );
		$this->assertSame( 1003, $e->get_error_code() );
	}

	/**
	 * Unknown method is error 1004 / HTTP 404.
	 */
	public function test_parse_rejects_unknown_method() {
		$e = $this->parse_expecting_exception( '{"method": "does.not.exist"}' );
		$this->assertSame( 1004, $e->get_error_code() );
		$this->assertSame( 404, $e->get_http_status() );
		$this->assertStringContainsString( 'does.not.exist', $e->getMessage() );
	}

	/**
	 * Non-array params is error 1005.
	 */
	public function test_parse_rejects_invalid_params() {
		$e = $this->parse_expecting_exception( '{"method": "platform.name", "params": "x"}' );
		$this->assertSame( 1005, $e->get_error_code() );
		$this->assertSame( 400, $e->get_http_status() );
	}

	/**
	 * Method is mapped to the retriever code and params are passed through.
	 */
	public function test_parse_maps_method_to_retriever_code() {
		$parser = new PayloadParser();
		$parsed = $parser->parse( '{"method": "platform.name"}' );

		$this->assertSame( 'platform-name', $parsed['code'] );
		$this->assertSame( array( '_v1_filter_fields' => array() ), $parsed['data'] );
	}

	/**
	 * The params.filter map is flattened into the top-level data array.
	 */
	public function test_parse_flattens_filter_into_data() {
		$parser = new PayloadParser();
		$parsed = $parser->parse(
			wp_json_encode(
				array(
					'method' => 'subscriber.list',
					'params' => array(
						'start'  => 10,
						'limit'  => 5,
						'sort'   => 'email',
						'filter' => array(
							'created_at' => array( 'from' => '2025-01-01' ),
							'email'      => 'john@example.org',
						),
					),
				)
			)
		);

		$this->assertSame( 'subscribers', $parsed['code'] );
		$data = $parsed['data'];
		$this->assertArrayNotHasKey( 'filter', $data );
		$this->assertSame( 10, $data['start'] );
		$this->assertSame( 5, $data['limit'] );
		$this->assertSame( 'email', $data['sort'] );
		$this->assertSame( array( 'from' => '2025-01-01' ), $data['created_at'] );
		$this->assertSame( 'john@example.org', $data['email'] );
		$this->assertSame( array( 'created_at', 'email' ), $data['_v1_filter_fields'] );
	}

	/**
	 * WooCommerce methods disappear from the map when WooCommerce is absent.
	 */
	public function test_method_map_drops_woocommerce_methods_without_woocommerce() {
		add_filter( 'newsman_woocommerce_exist', '__return_false' );
		$map = PayloadParser::get_method_map();

		foreach ( array( 'customer.list', 'product.list', 'order.list', 'coupon.create' ) as $method ) {
			$this->assertArrayNotHasKey( $method, $map );
		}
		$this->assertSame( 'subscribers', $map['subscriber.list'] );
		$this->assertSame( 'platform-name', $map['platform.name'] );

		$e = $this->parse_expecting_exception( '{"method": "order.list"}' );
		$this->assertSame( 1004, $e->get_error_code() );
	}

	/**
	 * WooCommerce methods are present when WooCommerce exists.
	 */
	public function test_method_map_keeps_woocommerce_methods_with_woocommerce() {
		add_filter( 'newsman_woocommerce_exist', '__return_true' );
		$map = PayloadParser::get_method_map();

		$this->assertSame( 'orders', $map['order.list'] );
		$this->assertSame( 'customers', $map['customer.list'] );
		$this->assertSame( 'products-feed', $map['product.list'] );
		$this->assertSame( 'coupons', $map['coupon.create'] );
	}

	/**
	 * Third-party code can extend the map through the filter.
	 */
	public function test_method_map_filter_can_add_methods() {
		add_filter(
			'newsman_api_v1_method_map',
			function ( $map ) {
				$map['custom.thing'] = 'platform-name';
				return $map;
			}
		);

		$parser = new PayloadParser();
		$parsed = $parser->parse( '{"method": "custom.thing"}' );
		$this->assertSame( 'platform-name', $parsed['code'] );
	}

	/**
	 * Exception accessors and defaults.
	 */
	public function test_api_v1_exception_accessors() {
		$e = new ApiV1Exception( '1234', 'Boom' );
		$this->assertSame( 1234, $e->get_error_code() );
		$this->assertSame( 500, $e->get_http_status() );
		$this->assertSame( 'Boom', $e->getMessage() );
		$this->assertInstanceOf( \RuntimeException::class, $e );
	}
}
