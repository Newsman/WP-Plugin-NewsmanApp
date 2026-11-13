<?php
/**
 * Tests for the API client error state: NewsMAN unreachable and error answers
 * without a code must be errors, not successes.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Api\Client;
use Newsman\Privacy\Eraser;

/**
 * API client error tests.
 */
class ApiClientErrorTest extends NewsmanTestCase {
	/**
	 * Fake answer: wp_error, status_only, err_no_code_200, err_no_code_400, ok.
	 *
	 * @var string
	 */
	private $answer = 'ok';

	/**
	 * Connect the plugin and fake the NewsMAN API.
	 */
	public function set_up() {
		parent::set_up();
		$this->enable_api();
		update_option( 'newsman_newslettertype', 'save' );
		update_option( 'newsman_developer_use_action_scheduler', '' );
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );
		add_filter( 'newsman_ip_address_get_ip', array( $this, 'fake_ip' ) );
	}

	/**
	 * Remove the fake API.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10 );
		remove_filter( 'newsman_ip_address_get_ip', array( $this, 'fake_ip' ) );
		parent::tear_down();
	}

	/**
	 * Fixed IP, so the server IP is not looked up on the internet.
	 *
	 * @return string
	 */
	public function fake_ip() {
		return '203.0.113.9';
	}

	/**
	 * Fake NewsMAN API.
	 *
	 * @return array|\WP_Error
	 */
	public function fake_newsman_api() {
		if ( 'wp_error' === $this->answer ) {
			return new \WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' );
		}
		$codes = array(
			'ok'              => array( 200, 'true' ),
			'err_no_code_200' => array( 200, '{"err":true,"message":"Something failed"}' ),
			'err_no_code_400' => array( 400, '{"err":true,"message":"Something failed"}' ),
			'status_only'     => array( 503, 'Service Unavailable' ),
		);
		return array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => $codes[ $this->answer ][0],
				'message' => 'Message',
			),
			'body'     => $codes[ $this->answer ][1],
		);
	}

	/**
	 * Call the API once and return the client.
	 *
	 * @return Client
	 */
	private function call() {
		$context = new \Newsman\Api\Context();
		$context->set_blog_id( get_current_blog_id() )->set_list_id( '1' )->set_endpoint( 'subscriber.saveUnsubscribe' );
		$client = new Client();
		$client->post( $context, array(), array( 'list_id' => '1' ) );
		return $client;
	}

	/**
	 * NewsMAN cannot be reached: an error with the connection code, not a success.
	 */
	public function test_unreachable_is_an_error() {
		$this->answer = 'wp_error';
		$client       = $this->call();
		$this->assertTrue( $client->has_error() );
		$this->assertSame( Client::ERROR_CODE_CONNECTION, $client->get_error_code() );
		$this->assertStringContainsString( 'Connection timed out', $client->get_error_message() );
	}

	/**
	 * Error answers without a NewsMAN code are errors; the HTTP status is kept when there is one.
	 */
	public function test_error_answers_without_a_code() {
		$this->answer = 'err_no_code_200';
		$client       = $this->call();
		$this->assertTrue( $client->has_error() );
		$this->assertSame( Client::ERROR_CODE_UNKNOWN, $client->get_error_code() );

		$this->answer = 'err_no_code_400';
		$client       = $this->call();
		$this->assertTrue( $client->has_error() );
		$this->assertSame( 400, $client->get_error_code() );
		$this->assertSame( 'Something failed', $client->get_error_message() );

		$this->answer = 'status_only';
		$this->assertSame( 503, $this->call()->get_error_code() );

		$this->answer = 'ok';
		$this->assertFalse( $this->call()->has_error() );
	}

	/**
	 * Erase Personal Data while NewsMAN cannot be reached: reported as retained, not as anonymised.
	 */
	public function test_erase_while_unreachable_is_reported() {
		$this->answer = 'wp_error';
		$response     = ( new Eraser() )->erase( 'unreachable@example.com', 1 );
		$this->assertFalse( $response['items_removed'] );
		$this->assertTrue( $response['items_retained'] );
		$this->assertStringContainsString( 'NewsMAN could not anonymise the email address', $response['messages'][0] );
	}
}
