<?php
/**
 * Tests for AbstractRetriever list parameter processing (sort, pagination, filters),
 * exercised through the Users retriever.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Export\Retriever\Users;
use Newsman\Export\V1\ApiV1Exception;

/**
 * List parameter tests.
 */
class ListParametersTest extends NewsmanTestCase {
	/**
	 * Without a sort the deterministic default (ID ASC) and default page size apply.
	 */
	public function test_defaults() {
		$params = ( new Users() )->process_list_parameters( array() );

		$this->assertSame( 'ID', $params['sort'] );
		$this->assertSame( 'ASC', $params['order'] );
		$this->assertSame( 0, $params['start'] );
		$this->assertSame( 1000, $params['limit'] );
		$this->assertSame( 1000, $params['default_page_size'] );
		$this->assertSame( array(), $params['filters'] );
	}

	/**
	 * Allowed sort fields are mapped to column names and the order is normalised.
	 */
	public function test_allowed_sort_is_mapped() {
		$params = ( new Users() )->process_list_parameters(
			array(
				'sort'  => 'email',
				'order' => 'DeSc',
			)
		);
		$this->assertSame( 'user_email', $params['sort'] );
		$this->assertSame( 'DESC', $params['order'] );

		$params = ( new Users() )->process_list_parameters(
			array(
				'sort'  => 'created_at',
				'order' => 'anything-else',
			)
		);
		$this->assertSame( 'user_registered', $params['sort'] );
		$this->assertSame( 'ASC', $params['order'] );
	}

	/**
	 * Legacy requests with an unknown sort fall back to the default sort.
	 */
	public function test_legacy_unknown_sort_falls_back_to_default() {
		$params = ( new Users() )->process_list_parameters(
			array(
				'sort'  => 'nope',
				'order' => 'desc',
			)
		);
		$this->assertSame( 'ID', $params['sort'] );
		$this->assertSame( 'ASC', $params['order'] );
	}

	/**
	 * API v1 requests with an unknown sort are rejected with error 1008.
	 */
	public function test_v1_unknown_sort_is_rejected() {
		try {
			( new Users() )->process_list_parameters(
				array(
					'sort'              => 'nope',
					'_v1_filter_fields' => array(),
				)
			);
			$this->fail( 'Expected ApiV1Exception.' );
		} catch ( ApiV1Exception $e ) {
			$this->assertSame( 1008, $e->get_error_code() );
			$this->assertSame( 400, $e->get_http_status() );
		}
	}

	/**
	 * Start and limit are cast to integers; invalid values use the defaults.
	 */
	public function test_pagination_values() {
		$params = ( new Users() )->process_list_parameters(
			array(
				'start' => '20',
				'limit' => '7',
			)
		);
		$this->assertSame( 20, $params['start'] );
		$this->assertSame( 7, $params['limit'] );

		$params = ( new Users() )->process_list_parameters(
			array(
				'start' => -5,
				'limit' => 0,
			)
		);
		$this->assertSame( 0, $params['start'] );
		$this->assertSame( 1000, $params['limit'] );
	}

	/**
	 * Scalar values become "eq", multiple fields become "in", operator maps are expanded.
	 */
	public function test_filters_are_built_from_the_mapping() {
		$params = ( new Users() )->process_list_where_parameters(
			array(
				'email'          => 'a@example.org',
				'subscriber_ids' => array( 3, 4 ),
				'created_at'     => array(
					'from' => '2025-01-01',
					'to'   => '2025-12-31',
				),
				'unknown_param'  => 'ignored',
			)
		);

		$this->assertSame(
			array(
				array(
					'field'    => 'user_registered',
					'operator' => 'from',
					'value'    => '2025-01-01',
					'type'     => 'string',
				),
				array(
					'field'    => 'user_registered',
					'operator' => 'to',
					'value'    => '2025-12-31',
					'type'     => 'string',
				),
				array(
					'field'    => 'ID',
					'operator' => 'in',
					'value'    => array( 3, 4 ),
					'type'     => 'int',
				),
				array(
					'field'    => 'user_email',
					'operator' => 'eq',
					'value'    => 'a@example.org',
					'type'     => 'string',
				),
			),
			$params['filters']
		);
	}

	/**
	 * Legacy requests silently skip unknown operators.
	 */
	public function test_legacy_unknown_operator_is_skipped() {
		$params = ( new Users() )->process_list_where_parameters(
			array(
				'created_at' => array(
					'between' => 'x',
					'gt'      => '2025-01-01',
				),
			)
		);
		$this->assertCount( 1, $params['filters'] );
		$this->assertSame( 'gt', $params['filters'][0]['operator'] );
	}

	/**
	 * API v1 requests reject unknown filter fields (1006) and operators (1007).
	 */
	public function test_v1_invalid_filter_field_and_operator_are_rejected() {
		try {
			( new Users() )->process_list_where_parameters(
				array(
					'nope'              => 'x',
					'_v1_filter_fields' => array( 'nope' ),
				)
			);
			$this->fail( 'Expected ApiV1Exception for unknown field.' );
		} catch ( ApiV1Exception $e ) {
			$this->assertSame( 1006, $e->get_error_code() );
		}

		try {
			( new Users() )->process_list_where_parameters(
				array(
					'created_at'        => array( 'between' => 'x' ),
					'_v1_filter_fields' => array( 'created_at' ),
				)
			);
			$this->fail( 'Expected ApiV1Exception for unknown operator.' );
		} catch ( ApiV1Exception $e ) {
			$this->assertSame( 1007, $e->get_error_code() );
		}
	}

	/**
	 * The users retriever does not support modified_at.
	 */
	public function test_modified_at_is_not_supported_for_users() {
		try {
			( new Users() )->process_list_parameters(
				array(
					'modified_at'       => array( 'from' => '2025-01-01' ),
					'_v1_filter_fields' => array( 'modified_at' ),
				)
			);
			$this->fail( 'Expected ApiV1Exception.' );
		} catch ( ApiV1Exception $e ) {
			$this->assertSame( 1010, $e->get_error_code() );
		}

		$this->expectException( \Exception::class );
		( new Users() )->process_list_parameters( array( 'modified_at' => array( 'from' => '2025-01-01' ) ) );
	}

	/**
	 * Every operator in the expression definition maps to a SQL operator.
	 */
	public function test_expression_definition() {
		$definition = ( new Users() )->get_expressions_definition();
		$this->assertSame( '=', $definition['eq'] );
		$this->assertSame( 'IN', $definition['in'] );
		$this->assertSame( '>=', $definition['from'] );
		$this->assertSame( '<=', $definition['to'] );
		$this->assertSame( 'LIKE', $definition['like'] );
	}
}
