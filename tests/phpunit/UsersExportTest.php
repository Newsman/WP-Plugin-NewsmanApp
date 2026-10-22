<?php
/**
 * Integration tests for the WordPress users based exports (Users and
 * SubscribersWordpressFeed retrievers) against a real database.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Export\Retriever\SubscribersWordpressFeed;
use Newsman\Export\Retriever\Users;

/**
 * Users export tests.
 */
class UsersExportTest extends NewsmanTestCase {
	/**
	 * IDs of the subscriber users created in set_up, in creation order.
	 *
	 * @var int[]
	 */
	private $subscriber_ids = array();

	/**
	 * Create five subscribers and one editor.
	 */
	public function set_up() {
		parent::set_up();

		$names = array(
			array( 'eve', 'Eve', 'Ember', '2024-03-01 10:00:00' ),
			array( 'bob', 'Bob', 'Brown', '2024-01-15 10:00:00' ),
			array( 'dan', 'Dan', 'Doe', '2024-05-20 10:00:00' ),
			array( 'amy', 'Amy', 'Adams', '2023-12-24 10:00:00' ),
			array( 'cid', 'Cid', 'Clark', '2024-02-02 10:00:00' ),
		);
		foreach ( $names as $n ) {
			$this->subscriber_ids[] = $this->create_user(
				'subscriber',
				$n[0] . '@example.org',
				$n[1],
				$n[2],
				array( 'user_registered' => $n[3] )
			);
		}
		$this->create_user( 'editor', 'editor@example.org', 'Ed', 'Itor' );
	}

	/**
	 * Fetch users with the given role and request data.
	 *
	 * @param array  $data Request data.
	 * @param string $role Role.
	 * @return array
	 */
	private function export( $data = array(), $role = 'subscriber' ) {
		$data['wp_newsman_internal_role'] = $role;
		return ( new Users() )->process( $data );
	}

	/**
	 * Only users of the requested role are exported, with the expected fields.
	 */
	public function test_exports_users_of_the_requested_role() {
		$rows = $this->export();

		$this->assertCount( 5, $rows );
		$emails = wp_list_pluck( $rows, 'email' );
		$this->assertNotContains( 'editor@example.org', $emails );
		$this->assertNotContains( WP_TESTS_EMAIL, $emails );

		$row = $rows[0];
		$this->assertSame( $this->subscriber_ids[0], (int) $row['subscriber_id'] );
		$this->assertSame( 'eve@example.org', $row['email'] );
		$this->assertSame( 'Eve', $row['firstname'] );
		$this->assertSame( 'Ember', $row['lastname'] );
	}

	/**
	 * Without an explicit sort rows come back ordered by ID.
	 */
	public function test_default_order_is_by_id() {
		$ids    = array_map( 'intval', wp_list_pluck( $this->export(), 'subscriber_id' ) );
		$sorted = $ids;
		sort( $sorted );
		$this->assertSame( $sorted, $ids );
	}

	/**
	 * Walking the export page by page returns every user exactly once.
	 */
	public function test_pagination_is_complete_and_without_duplicates() {
		$ids = $this->walk_pages(
			function ( $start, $limit ) {
				return $this->export(
					array(
						'start' => $start,
						'limit' => $limit,
					)
				);
			},
			'subscriber_id',
			2
		);

		$this->assertCount( 5, $ids );
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );
		$expected = $this->subscriber_ids;
		sort( $expected );
		$this->assertSame( $expected, $ids );
	}

	/**
	 * Sorting by email descending.
	 */
	public function test_sort_by_email_desc() {
		$rows = $this->export(
			array(
				'sort'  => 'email',
				'order' => 'desc',
			)
		);
		$this->assertSame(
			array( 'eve@example.org', 'dan@example.org', 'cid@example.org', 'bob@example.org', 'amy@example.org' ),
			wp_list_pluck( $rows, 'email' )
		);
	}

	/**
	 * Sorting by registration date ascending.
	 */
	public function test_sort_by_created_at() {
		$rows = $this->export( array( 'sort' => 'created_at' ) );
		$this->assertSame(
			array( 'amy@example.org', 'bob@example.org', 'cid@example.org', 'eve@example.org', 'dan@example.org' ),
			wp_list_pluck( $rows, 'email' )
		);
	}

	/**
	 * Filtering by a list of IDs.
	 */
	public function test_filter_by_subscriber_ids() {
		$wanted = array( $this->subscriber_ids[1], $this->subscriber_ids[3] );
		$rows   = $this->export( array( 'subscriber_ids' => $wanted ) );

		$this->assertSame( $wanted, array_map( 'intval', wp_list_pluck( $rows, 'subscriber_id' ) ) );
	}

	/**
	 * Filtering by a single ID and by exact email.
	 */
	public function test_filter_by_subscriber_id_and_email() {
		$rows = $this->export( array( 'subscriber_id' => $this->subscriber_ids[2] ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'dan@example.org', $rows[0]['email'] );

		$rows = $this->export( array( 'email' => 'bob@example.org' ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( $this->subscriber_ids[1], (int) $rows[0]['subscriber_id'] );
	}

	/**
	 * The SQL style LIKE wildcard is translated for WP_User_Query.
	 */
	public function test_filter_by_email_like() {
		$rows = $this->export( array( 'email' => array( 'like' => '%@example.org' ) ) );
		$this->assertCount( 5, $rows );

		$rows = $this->export( array( 'email' => array( 'like' => 'b%' ) ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'bob@example.org', $rows[0]['email'] );
	}

	/**
	 * Filtering by registration date range.
	 */
	public function test_filter_by_created_at_range() {
		$rows = $this->export( array( 'created_at' => array( 'from' => '2024-02-01' ) ) );
		$this->assertSame(
			array( 'eve@example.org', 'dan@example.org', 'cid@example.org' ),
			wp_list_pluck( $rows, 'email' )
		);

		$rows = $this->export(
			array(
				'created_at' => array(
					'from' => '2024-01-01',
					'to'   => '2024-02-28',
				),
			)
		);
		$this->assertSame( array( 'bob@example.org', 'cid@example.org' ), wp_list_pluck( $rows, 'email' ) );

		$this->assertSame( array(), $this->export( array( 'created_at' => array( 'from' => '2030-01-01' ) ) ) );
	}

	/**
	 * Unknown roles are rejected.
	 */
	public function test_rejects_unknown_role() {
		$this->expectException( \Exception::class );
		$this->export( array(), 'administrator' );
	}

	/**
	 * The WordPress subscribers feed adds the subscription fields.
	 */
	public function test_wordpress_subscribers_feed_rows() {
		$rows = ( new SubscribersWordpressFeed() )->process( array( 'limit' => 1 ) );

		$this->assertCount( 1, $rows );
		$row = $rows[0];
		$this->assertSame( $this->subscriber_ids[0], (int) $row['subscriber_id'] );
		$this->assertSame( 'eve@example.org', $row['email'] );
		$this->assertSame( 1, $row['confirmed'] );
		$this->assertSame( '2024-03-01 10:00:00', $row['date_subscribed'] );
		$this->assertSame( 'WordPress users role subscriber', $row['source'] );
	}
}
