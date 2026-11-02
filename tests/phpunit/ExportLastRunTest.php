<?php
/**
 * Tests for the last export per type that the NewsMAN page and Site Health show.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Export\LastRun;
use Newsman\Export\Retriever\Authenticator;
use Newsman\Export\Retriever\Processor;

/**
 * Last export tests.
 */
class ExportLastRunTest extends NewsmanTestCase {
	/**
	 * Start without recorded exports.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( LastRun::OPTION_NAME );
	}

	/**
	 * Retriever codes map to the four export types; other codes are not exports.
	 */
	public function test_codes_map_to_types() {
		$last_run = new LastRun();

		$this->assertSame( 'subscribers', $last_run->get_type_by_code( 'subscribers' ) );
		$this->assertSame( 'subscribers', $last_run->get_type_by_code( 'subscribers-woocommerce-feed' ) );
		$this->assertSame( 'customers', $last_run->get_type_by_code( 'customers' ) );
		$this->assertSame( 'products', $last_run->get_type_by_code( 'products-feed' ) );
		$this->assertSame( 'orders', $last_run->get_type_by_code( 'ORDERS' ) );

		foreach ( array( 'platform-name', 'coupons', 'custom-sql', 'subscriber-subscribe', 'refresh-remarketing' ) as $code ) {
			$this->assertFalse( $last_run->get_type_by_code( $code ), $code );
			$this->assertFalse( $last_run->record( $code ), $code );
		}
		$this->assertSame( array(), $last_run->get_all() );
	}

	/**
	 * A paginated export writes once per minute and type, then updates again.
	 */
	public function test_record_throttles_per_type() {
		$last_run = new LastRun();
		$start    = 1700000000;

		$this->assertTrue( $last_run->record( 'orders', null, $start ) );
		$this->assertFalse( $last_run->record( 'orders', null, $start + 30 ) );
		$this->assertSame( $start, $last_run->get_time( 'orders' ) );

		$this->assertTrue( $last_run->record( 'subscribers', null, $start + 30 ), 'Another type is not throttled.' );

		$this->assertTrue( $last_run->record( 'orders', null, $start + LastRun::MIN_INTERVAL ) );
		$this->assertSame( $start + LastRun::MIN_INTERVAL, $last_run->get_time( 'orders' ) );
		$this->assertNull( $last_run->get_time( 'customers' ) );

		$runs = $last_run->get_all();
		$this->assertSame( 'orders', $runs['orders']['code'] );
	}

	/**
	 * The option is not loaded on every page.
	 */
	public function test_option_is_not_autoloaded() {
		global $wpdb;
		( new LastRun() )->record( 'subscribers' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads the autoload column, which has no API.
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", LastRun::OPTION_NAME ) );
		$this->assertContains( $autoload, array( 'no', 'off' ) );
	}

	/**
	 * WordPress without WooCommerce has only the subscribers export.
	 */
	public function test_types_without_woocommerce() {
		$last_run = new LastRun();
		$this->assertSame( array( 'subscribers' ), $last_run->get_types( false ) );
		$this->assertSame( array( 'customers', 'subscribers', 'products', 'orders' ), $last_run->get_types( true ) );
	}

	/**
	 * The processor records a successful export and nothing when authentication fails.
	 */
	public function test_processor_records_successful_export_only() {
		$this->enable_api();
		$processor = new Processor();
		$blog_id   = get_current_blog_id();

		try {
			$processor->process( 'subscribers', $blog_id, array( Authenticator::API_KEY_PARAM => 'wrong' ) );
			$this->fail( 'Expected OutOfBoundsException for a wrong key.' );
		} catch ( \OutOfBoundsException $e ) {
			$this->assertNull( ( new LastRun() )->get_time( 'subscribers' ) );
		}

		try {
			$processor->process( 'subscribers', $blog_id, array( Authenticator::API_KEY_PARAM => self::API_KEY ) );
			$this->fail( 'Expected an exception: no subscriber export is enabled.' );
		} catch ( \Exception $e ) {
			$this->assertNull( ( new LastRun() )->get_time( 'subscribers' ), 'A failed export is not recorded.' );
		}

		update_option( 'newsman_useremarketing', 'on' );
		update_option( 'newsman_remarketingid', '12345-678-abc' );
		update_option( 'newsman_remarketingexportwordpresssubscribers', 'on' );
		$before = time();
		$processor->process( 'subscribers', $blog_id, array( Authenticator::API_KEY_PARAM => self::API_KEY ) );
		$time = ( new LastRun() )->get_time( 'subscribers' );
		$this->assertNotNull( $time );
		$this->assertGreaterThanOrEqual( $before, $time );

		$processor->process( 'platform-name', $blog_id, array( Authenticator::API_KEY_PARAM => self::API_KEY ) );
		$this->assertSame( array( 'subscribers' ), array_keys( ( new LastRun() )->get_all() ) );
	}
}
