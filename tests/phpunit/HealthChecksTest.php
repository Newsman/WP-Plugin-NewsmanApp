<?php
/**
 * Tests for the health checks of the NewsMAN page and the Site Health integration.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Export\LastRun;
use Newsman\Health\Checks;
use Newsman\Health\SiteHealth;

/**
 * Health checks and Site Health tests.
 */
class HealthChecksTest extends NewsmanTestCase {
	/**
	 * Lists returned by the fake list.all call, or null for an API error.
	 *
	 * @var array|null
	 */
	private $api_lists = array(
		array(
			'list_id'   => '1',
			'list_name' => 'Store <b>list</b>',
		),
		array(
			'list_id'   => '2',
			'list_name' => 'Other',
		),
	);

	/**
	 * Number of list.all calls.
	 *
	 * @var int
	 */
	private $list_calls = 0;

	/**
	 * Fake the NewsMAN API and log in as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );
		wp_set_current_user( $this->create_user( 'administrator', 'health-admin@example.org', 'Health', 'Admin' ) );
		delete_option( LastRun::OPTION_NAME );
		delete_option( 'newsman_useremarketing' );
		delete_option( 'newsman_remarketingid' );
	}

	/**
	 * Log out.
	 */
	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Fake NewsMAN API: list.all answers with $this->api_lists or an error.
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array|false
	 */
	public function fake_newsman_api( $pre, $args, $url ) {
		unset( $args );
		if ( false === strpos( $url, 'list.all' ) ) {
			return $pre;
		}
		++$this->list_calls;
		if ( null === $this->api_lists ) {
			return array(
				'headers'  => array(),
				'cookies'  => array(),
				'response' => array(
					'code'    => 500,
					'message' => 'Error',
				),
				'body'     => '{"err":"Error","message":"Invalid API key","code":401}',
			);
		}
		return array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => wp_json_encode( $this->api_lists ),
		);
	}

	/**
	 * Without credentials the connection is critical and the API is not called.
	 */
	public function test_missing_credentials() {
		delete_option( 'newsman_userid' );
		delete_option( 'newsman_apikey' );
		delete_option( 'newsman_list' );
		$checks = new Checks();

		$credentials = $checks->check_credentials();
		$this->assertSame( Checks::STATUS_CRITICAL, $credentials['status'] );
		$this->assertStringContainsString( 'page=NewsmanOauth', $credentials['action_url'] );
		$this->assertSame( Checks::STATUS_RECOMMENDED, $checks->check_list()['status'] );
		$this->assertSame( 0, $this->list_calls );

		$result = ( new SiteHealth( $checks ) )->test_connection();
		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'newsman_connection', $result['test'] );
		$this->assertSame( 'NewsMAN', $result['badge']['label'] );
	}

	/**
	 * Valid credentials and a list that exists: one API call for all checks.
	 */
	public function test_valid_credentials_and_list() {
		$this->enable_api();
		$checks = new Checks();

		$this->assertSame( Checks::STATUS_GOOD, $checks->check_credentials()['status'] );
		$list = $checks->check_list();
		$this->assertSame( Checks::STATUS_GOOD, $list['status'] );
		$this->assertSame( 'Store <b>list</b> (ID: 1)', $list['value'] );
		$this->assertSame( 1, $this->list_calls, 'The API is called once per request.' );

		$result = ( new SiteHealth( $checks ) )->test_connection();
		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'Store &lt;b&gt;list&lt;/b&gt; (ID: 1)', $result['description'], 'The list name is escaped.' );
		$this->assertSame( '', $result['actions'] );
	}

	/**
	 * A list ID that is not in the account is critical.
	 */
	public function test_list_not_in_account() {
		$this->enable_api();
		update_option( 'newsman_list', '99' );
		$checks = new Checks();

		$list = $checks->check_list();
		$this->assertSame( Checks::STATUS_CRITICAL, $list['status'] );
		$this->assertStringContainsString( 'page=NewsmanSync', $list['action_url'] );

		$result = ( new SiteHealth( $checks ) )->test_connection();
		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'page=NewsmanSync', $result['actions'] );
	}

	/**
	 * Rejected credentials are critical; the list cannot be checked.
	 */
	public function test_rejected_credentials() {
		$this->enable_api();
		$this->api_lists = null;
		$checks          = new Checks();

		$this->assertFalse( $checks->is_valid_credentials() );
		$this->assertSame( Checks::STATUS_CRITICAL, $checks->check_credentials()['status'] );
		$this->assertSame( Checks::STATUS_INFO, $checks->check_list()['status'] );

		$result = ( new SiteHealth( $checks ) )->test_connection();
		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'page=NewsmanSettings', $result['actions'] );
	}

	/**
	 * The "Allow API access" setting.
	 */
	public function test_api_access() {
		$this->enable_api();
		$this->assertSame( Checks::STATUS_GOOD, ( new Checks() )->check_api_access()['status'] );
		$this->assertSame( 'good', ( new SiteHealth() )->test_api_access()['status'] );

		update_option( 'newsman_api', '' );
		$this->assertSame( Checks::STATUS_RECOMMENDED, ( new Checks() )->check_api_access()['status'] );
		$this->assertSame( 'recommended', ( new SiteHealth() )->test_api_access()['status'] );
	}

	/**
	 * Remarketing: off is information, on without ID needs attention, on with ID is good.
	 */
	public function test_remarketing() {
		$this->enable_api();
		$this->assertSame( Checks::STATUS_INFO, ( new Checks() )->check_remarketing()['status'] );

		update_option( 'newsman_useremarketing', 'on' );
		$this->assertSame( Checks::STATUS_RECOMMENDED, ( new Checks() )->check_remarketing()['status'] );
		$this->assertSame( 'recommended', ( new SiteHealth() )->test_remarketing()['status'] );

		update_option( 'newsman_remarketingid', '12345-678-abc' );
		$check = ( new Checks() )->check_remarketing();
		$this->assertSame( Checks::STATUS_GOOD, $check['status'] );
		$this->assertSame( 'On', $check['value'] );
		$this->assertSame( 'good', ( new SiteHealth() )->test_remarketing()['status'] );
	}

	/**
	 * WooCommerce presence.
	 */
	public function test_woocommerce() {
		if ( $this->has_woocommerce() ) {
			$check = ( new Checks() )->check_woocommerce();
			$this->assertSame( Checks::STATUS_GOOD, $check['status'] );
			$this->assertStringContainsString( WC_VERSION, $check['value'] );
		}

		add_filter( 'newsman_woocommerce_exist', '__return_false' );
		$this->assertSame( Checks::STATUS_INFO, ( new Checks() )->check_woocommerce()['status'] );
		remove_filter( 'newsman_woocommerce_exist', '__return_false' );
	}

	/**
	 * Pending, past due and failed NewsMAN background tasks.
	 */
	public function test_action_scheduler_counts() {
		$this->require_woocommerce();
		$group = \Newsman\Util\ActionScheduler::GROUP_SUBSCRIBE;

		$check = ( new Checks() )->check_action_scheduler();
		$this->assertSame( Checks::STATUS_GOOD, $check['status'] );

		as_schedule_single_action( time() + HOUR_IN_SECONDS, 'newsman_health_test_future', array(), $group );
		as_schedule_single_action( time() + HOUR_IN_SECONDS, 'other_plugin_task', array(), 'other-group' );
		$counts = ( new Checks() )->get_action_scheduler_counts();
		$this->assertSame( 1, $counts['pending'] );
		$this->assertSame( 0, $counts['past_due'] );
		$this->assertSame( Checks::STATUS_GOOD, ( new Checks() )->check_action_scheduler()['status'] );

		$failed_id = as_schedule_single_action( time() - DAY_IN_SECONDS, 'newsman_health_test_failed', array(), $group );
		$store     = \ActionScheduler::store();
		$store->log_execution( $failed_id );
		$store->mark_failure( $failed_id );
		$check = ( new Checks() )->check_action_scheduler();
		$this->assertSame( Checks::STATUS_RECOMMENDED, $check['status'] );
		$this->assertStringContainsString( 'status=failed', $check['action_url'] );

		as_schedule_single_action( time() - 2 * HOUR_IN_SECONDS, 'newsman_health_test_late', array(), $group );
		$counts = ( new Checks() )->get_action_scheduler_counts();
		$this->assertSame( 2, $counts['pending'] );
		$this->assertSame( 1, $counts['past_due'] );
		$this->assertSame( 1, $counts['failed'] );

		$check = ( new Checks() )->check_action_scheduler();
		$this->assertSame( Checks::STATUS_RECOMMENDED, $check['status'] );
		$this->assertStringContainsString( 'status=past-due', $check['action_url'] );
		$this->assertStringContainsString( 's=newsman', $check['action_url'] );
		$this->assertSame( 'recommended', ( new SiteHealth() )->test_action_scheduler()['status'] );
	}

	/**
	 * Without Action Scheduler the check only informs and no Site Health test is added.
	 */
	public function test_without_action_scheduler() {
		add_filter( 'newsman_action_scheduler_exist', '__return_false' );
		$this->assertSame( Checks::STATUS_INFO, ( new Checks() )->check_action_scheduler()['status'] );
		$tests = ( new SiteHealth() )->add_tests( array() );
		$this->assertArrayNotHasKey( 'newsman_action_scheduler', $tests['direct'] );
		remove_filter( 'newsman_action_scheduler_exist', '__return_false' );
	}

	/**
	 * Last exports: never recorded, then a date with the elapsed time.
	 */
	public function test_last_exports() {
		$exports = ( new Checks() )->get_last_exports();
		$types   = wp_list_pluck( $exports, 'type' );
		$this->assertContains( 'subscribers', $types );
		$this->assertSame( $this->has_woocommerce(), in_array( 'orders', $types, true ) );
		foreach ( $exports as $export ) {
			$this->assertNull( $export['time'] );
			$this->assertSame( 'No export recorded yet', $export['value'] );
		}

		( new LastRun() )->record( 'subscribers', null, time() - 3 * HOUR_IN_SECONDS );
		$exports = wp_list_pluck( ( new Checks() )->get_last_exports(), 'value', 'type' );
		$this->assertStringContainsString( '(3 hours ago)', $exports['subscribers'] );
	}

	/**
	 * The tests registered in Site Health.
	 */
	public function test_site_health_tests_registered() {
		$tests = apply_filters( 'site_status_tests', array() );

		$this->assertArrayHasKey( 'newsman_connection', $tests['async'], 'The connection test calls the NewsMAN API, so it is asynchronous.' );
		$connection = $tests['async']['newsman_connection'];
		$this->assertTrue( $connection['has_rest'] );
		$this->assertStringContainsString( 'newsman/v1/site-health/connection', $connection['test'] );
		$this->assertTrue( is_callable( $connection['async_direct_test'] ) );

		$this->assertArrayHasKey( 'newsman_api_access', $tests['direct'] );
		$this->assertSame( $this->has_woocommerce(), isset( $tests['direct']['newsman_action_scheduler'] ) );
		$this->assertArrayNotHasKey( 'newsman_remarketing', $tests['direct'], 'Remarketing turned off is a choice, not an issue.' );

		update_option( 'newsman_useremarketing', 'on' );
		$tests = apply_filters( 'site_status_tests', array() );
		$this->assertArrayHasKey( 'newsman_remarketing', $tests['direct'] );
	}

	/**
	 * The REST route of the asynchronous test is only for users who may view Site Health.
	 */
	public function test_rest_route_permissions() {
		global $wp_rest_server;
		$this->enable_api();
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$request  = new WP_REST_Request( 'GET', '/newsman/v1/site-health/connection' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'good', $data['status'] );
		$this->assertSame( 'newsman_connection', $data['test'] );

		wp_set_current_user( $this->create_user( 'editor', 'health-editor@example.org', 'Health', 'Editor' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 401, 403 ) );

		$wp_rest_server = null;
	}

	/**
	 * The NewsMAN section of Tools > Site Health > Info, without an API call.
	 */
	public function test_debug_information() {
		$this->enable_api();
		update_option( 'newsman_remarketingid', '12345-678-abc' );
		( new LastRun() )->record( 'subscribers', null, 1700000000 );

		$info = apply_filters( 'debug_information', array() );
		$this->assertArrayHasKey( 'newsman', $info );
		$fields = $info['newsman']['fields'];

		$this->assertSame( NEWSMAN_VERSION, $fields['version']['value'] );
		$this->assertSame( 'yes', $fields['credentials']['debug'] );
		$this->assertSame( '1', $fields['list_id']['value'] );
		$this->assertSame( 'on', $fields['api_access']['debug'] );
		$this->assertSame( 'off', $fields['remarketing']['debug'] );
		$this->assertSame( '12345-678-abc', $fields['remarketing_id']['value'] );
		$this->assertSame( '2023-11-14 22:13:20 UTC', $fields['last_export_subscribers']['debug'] );
		$this->assertSame( $this->has_woocommerce(), isset( $fields['last_export_orders'] ) );
		if ( $this->has_woocommerce() ) {
			$this->assertSame( 'never', $fields['last_export_orders']['debug'] );
			$this->assertSame( 0, $fields['tasks_pending']['value'] );
		}
		$this->assertArrayNotHasKey( 'api_key', $fields );
		$this->assertSame( 0, $this->list_calls, 'The Info tab does not call the NewsMAN API.' );
	}
}
