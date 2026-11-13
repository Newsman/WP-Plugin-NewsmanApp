<?php
/**
 * Tests for the anonymise (email) and unsubscribe (SMS phone numbers) sent to NewsMAN when
 * personal data is erased: Tools > Erase Personal Data and deleted users.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Privacy\Eraser;

/**
 * Personal data eraser tests.
 */
class PrivacyEraserTest extends NewsmanTestCase {
	/**
	 * Requests seen by the fake API, in call order: [ endpoint, decoded body ].
	 *
	 * @var array
	 */
	private $calls = array();

	/**
	 * Answer of the fake API: ok, missing or error.
	 *
	 * @var string
	 */
	private $answer = 'ok';

	/**
	 * Answers per endpoint, override $answer.
	 *
	 * @var array
	 */
	private $answers = array();

	/**
	 * Connect the plugin and fake the NewsMAN API.
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$this->enable_api();
		update_option( 'newsman_newslettertype', 'save' );
		update_option( 'newsman_developer_use_action_scheduler', '' );
		update_option( 'newsman_developer_use_as_unsubscribe', '' );
		$this->calls   = array();
		$this->answer  = 'ok';
		$this->answers = array();
		update_option( 'newsman_usesms', '' );
		update_option( 'newsman_smslist', '77' );
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
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array
	 */
	public function fake_newsman_api( $pre, $args, $url ) {
		unset( $pre );
		$endpoint = preg_match( '#/([a-zA-Z]+\.[a-zA-Z]+)\.json#', $url, $m ) ? $m[1] : $url;
		$body     = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		if ( null === $body ) {
			$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
			wp_parse_str( $query, $body );
		}
		$this->calls[] = array( $endpoint, $body );

		$response = array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => 'true',
		);
		$answer   = isset( $this->answers[ $endpoint ] ) ? $this->answers[ $endpoint ] : $this->answer;
		if ( 'missing' === $answer ) {
			$response['response'] = array(
				'code'    => 400,
				'message' => 'Bad Request',
			);
			$response['body']     = '{"err":true,"message":"No such subscriber for email and list_id 1","code":121}';
		} elseif ( 'error' === $answer ) {
			$response['response'] = array(
				'code'    => 500,
				'message' => 'Error',
			);
			$response['body']     = '{"err":"Error","message":"Failure","code":500}';
		}
		return $response;
	}

	/**
	 * Called endpoints, in call order.
	 *
	 * @return string[]
	 */
	private function endpoints() {
		return array_map(
			function ( $call ) {
				return $call[0];
			},
			$this->calls
		);
	}

	/**
	 * Emails sent to subscriber.anonymiseEmail, in call order. Never sent to subscriber.saveUnsubscribe.
	 *
	 * @return string[]
	 */
	private function anonymised_emails() {
		$emails = array();
		foreach ( $this->calls as $call ) {
			$this->assertNotSame( 'subscriber.saveUnsubscribe', $call[0] );
			if ( 'subscriber.anonymiseEmail' === $call[0] ) {
				$emails[] = $call[1]['email'];
			}
		}
		return $emails;
	}

	/**
	 * Emails sent to subscriber.saveUnsubscribe, in call order. Never sent to subscriber.anonymiseEmail.
	 *
	 * @return string[]
	 */
	private function unsubscribed_emails() {
		$emails = array();
		foreach ( $this->calls as $call ) {
			$this->assertNotSame( 'subscriber.anonymiseEmail', $call[0] );
			if ( 'subscriber.saveUnsubscribe' === $call[0] ) {
				$emails[] = $call[1]['email'];
			}
		}
		return $emails;
	}

	/**
	 * Delete a user account: from the site on a single site, from the network on a network
	 * (removing a user from a site of a network sends nothing).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function delete_account( $user_id ) {
		if ( is_multisite() ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
			return wpmu_delete_user( $user_id );
		}
		return wp_delete_user( $user_id );
	}

	/**
	 * Phone numbers sent to sms.saveUnsubscribe, sorted.
	 *
	 * @return string[]
	 */
	private function unsubscribed_phones() {
		$phones = array();
		foreach ( $this->calls as $call ) {
			if ( 'sms.saveUnsubscribe' === $call[0] ) {
				$this->assertSame( '77', (string) $call[1]['list_id'] );
				$phones[] = $call[1]['telephone'];
			}
		}
		sort( $phones );
		return $phones;
	}

	/**
	 * Create a WooCommerce order with a billing email and phone.
	 *
	 * @param string $email       Billing email.
	 * @param string $phone       Billing phone.
	 * @param int    $customer_id Customer user ID, 0 for a guest.
	 * @return \WC_Order
	 */
	private function create_order( $email, $phone, $customer_id = 0 ) {
		$order = wc_create_order( array( 'customer_id' => $customer_id ) );
		$order->set_billing_email( $email );
		$order->set_billing_phone( $phone );
		$order->save();
		return $order;
	}

	/**
	 * The user is gone from the site: deleted on a single site, removed from the site on a network.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function assertUserDeleted( $user_id ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( is_multisite() ) {
			$this->assertFalse( is_user_member_of_blog( $user_id, get_current_blog_id() ) );
		} else {
			$this->assertFalse( get_userdata( $user_id ) );
		}
	}

	/**
	 * Run the NewsMAN eraser the way Tools > Erase Personal Data does.
	 *
	 * @param string $email Email address.
	 * @return array Eraser response.
	 */
	private function run_eraser( $email ) {
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayHasKey( Eraser::ERASER_ID, $erasers, 'The NewsMAN eraser is not registered.' );
		return call_user_func( $erasers[ Eraser::ERASER_ID ]['callback'], $email, 1 );
	}

	/**
	 * The eraser is listed only when the plugin is connected to NewsMAN.
	 */
	public function test_eraser_is_registered_only_when_connected() {
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayHasKey( Eraser::ERASER_ID, $erasers );
		$this->assertSame( 'NewsMAN newsletter subscription', $erasers[ Eraser::ERASER_ID ]['eraser_friendly_name'] );

		update_option( 'newsman_api', '' );
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayNotHasKey( Eraser::ERASER_ID, $erasers );
	}

	/**
	 * Erasing anonymises the email right away in the list of the site.
	 */
	public function test_erase_anonymises_the_email() {
		$response = $this->run_eraser( 'erase-me@example.com' );

		$this->assertSame( array( 'subscriber.anonymiseEmail' ), $this->endpoints() );
		$this->assertSame( 'erase-me@example.com', $this->calls[0][1]['email'] );
		$this->assertSame( '1', (string) $this->calls[0][1]['list_id'] );
		$this->assertArrayNotHasKey( 'ip', $this->calls[0][1] );
		$this->assertTrue( $response['items_removed'] );
		$this->assertFalse( $response['items_retained'] );
		$this->assertTrue( $response['done'] );
		$this->assertSame( array( 'The email address was anonymised in the NewsMAN list.' ), $response['messages'] );
	}

	/**
	 * Double opt-in does not apply: the person asked to be forgotten and gets no confirmation email.
	 */
	public function test_erase_ignores_double_optin() {
		update_option( 'newsman_newslettertype', 'init' );
		$this->assertTrue( \Newsman\Config::init()->is_newsletter_double_optin() );

		$this->run_eraser( 'double@example.com' );

		$this->assertSame( array( 'subscriber.anonymiseEmail' ), $this->endpoints() );
	}

	/**
	 * An email that is not on the list is not an error.
	 */
	public function test_erase_email_not_on_the_list() {
		$this->answer = 'missing';

		$response = $this->run_eraser( 'stranger@example.com' );

		$this->assertSame( array( 'subscriber.anonymiseEmail' ), $this->endpoints() );
		$this->assertFalse( $response['items_removed'] );
		$this->assertFalse( $response['items_retained'] );
		$this->assertSame( array(), $response['messages'] );
		$this->assertTrue( $response['done'] );
	}

	/**
	 * A NewsMAN error is reported on the request as retained data, with an escaped message.
	 */
	public function test_erase_reports_newsman_errors() {
		$this->answer = 'error';

		$response = $this->run_eraser( 'fails@example.com' );

		$this->assertFalse( $response['items_removed'] );
		$this->assertTrue( $response['items_retained'] );
		$this->assertTrue( $response['done'] );
		$this->assertCount( 1, $response['messages'] );
		$this->assertStringContainsString( 'NewsMAN could not anonymise the email address: Failure.', $response['messages'][0] );
	}

	/**
	 * Invalid emails and a plugin that is not connected send nothing.
	 */
	public function test_erase_sends_nothing_without_email_or_connection() {
		$eraser = new Eraser();

		$response = $eraser->erase( 'not-an-email', 1 );
		$this->assertFalse( $response['items_removed'] );

		update_option( 'newsman_api', '' );
		$response = $eraser->erase( 'valid@example.com', 1 );
		$this->assertFalse( $response['items_removed'] );
		$this->assertTrue( $response['done'] );

		$this->assertSame( array(), $this->endpoints() );
	}

	/**
	 * Deleting a user anonymises the email right away when Action Scheduler is not used.
	 */
	public function test_deleted_user_is_anonymised() {
		$user_id = $this->create_user( 'customer', 'deleted-user@example.com', 'Ana', 'Pop' );

		$this->assertTrue( $this->delete_account( $user_id ) );

		$this->assertSame( array( 'deleted-user@example.com' ), $this->anonymised_emails() );
		$this->assertUserDeleted( $user_id );
	}

	/**
	 * Every role is anonymised, and double opt-in does not apply.
	 */
	public function test_deleted_user_any_role_double_optin() {
		update_option( 'newsman_newslettertype', 'init' );
		$user_id = $this->create_user( 'subscriber', 'deleted-subscriber@example.com', 'Ion', 'Pop' );

		$this->delete_account( $user_id );

		$this->assertSame( array( 'subscriber.anonymiseEmail' ), $this->endpoints() );
		$this->assertSame( array( 'deleted-subscriber@example.com' ), $this->anonymised_emails() );
	}

	/**
	 * A NewsMAN error, or an email that is not on the list, never stops the deletion.
	 */
	public function test_deleted_user_newsman_errors_do_not_stop_the_deletion() {
		$this->answer = 'error';
		$user_id      = $this->create_user( 'customer', 'deleted-error@example.com', 'Ana', 'Pop' );
		$this->assertTrue( $this->delete_account( $user_id ) );
		$this->assertUserDeleted( $user_id );

		$this->answer = 'missing';
		$user_id      = $this->create_user( 'customer', 'deleted-missing@example.com', 'Ana', 'Pop' );
		$this->assertTrue( $this->delete_account( $user_id ) );
		$this->assertUserDeleted( $user_id );

		$this->assertSame( array( 'deleted-error@example.com', 'deleted-missing@example.com' ), $this->anonymised_emails() );
	}

	/**
	 * Nothing is sent when the plugin is not connected or when the filter says no.
	 */
	public function test_deleted_user_not_connected_or_filtered() {
		update_option( 'newsman_api', '' );
		$this->delete_account( $this->create_user( 'customer', 'deleted-off@example.com', 'Ana', 'Pop' ) );
		$this->assertSame( array(), $this->endpoints() );

		update_option( 'newsman_api', 'on' );
		add_filter( 'newsman_privacy_erase_deleted_user', '__return_false' );
		$this->delete_account( $this->create_user( 'customer', 'deleted-filtered@example.com', 'Ana', 'Pop' ) );
		remove_filter( 'newsman_privacy_erase_deleted_user', '__return_false' );
		$this->assertSame( array(), $this->endpoints() );
	}

	/**
	 * The same email is sent once per site in a request.
	 */
	public function test_same_email_is_sent_once_per_site() {
		$eraser = new Eraser();
		$eraser->execute( 'twice@example.com', get_current_blog_id() );
		$eraser->execute( 'TWICE@example.com', get_current_blog_id() );
		$this->assertSame( array( 'twice@example.com' ), $this->anonymised_emails() );
	}

	/**
	 * The NewsMAN eraser runs first, before the WooCommerce erasers remove the phone numbers it reads.
	 */
	public function test_eraser_runs_first() {
		$erasers = apply_filters(
			'wp_privacy_personal_data_erasers',
			array(
				'other-plugin' => array(
					'eraser_friendly_name' => 'Other',
					'callback'             => '__return_empty_array',
				),
			)
		);
		$this->assertSame( Eraser::ERASER_ID, array_keys( $erasers )[0] );
		$this->assertArrayHasKey( 'other-plugin', $erasers );
	}

	/**
	 * With SMS set up, the erase also unsubscribes from the SMS list the account billing phone
	 * and the billing phones of the orders, digits only, each once.
	 */
	public function test_erase_unsubscribes_phone_numbers_from_the_sms_list() {
		$this->require_woocommerce();
		update_option( 'newsman_usesms', 'on' );
		$user_id = $this->create_user( 'customer', 'sms-erase@example.com', 'Ana', 'Pop' );
		update_user_meta( $user_id, 'billing_phone', '0722 111 222' );
		$this->create_order( 'sms-erase@example.com', '+40 (733) 444-555' );
		$this->create_order( 'other-email@example.com', '0744.666.777', $user_id );
		$this->create_order( 'sms-erase@example.com', '0722-111-222' );
		$this->create_order( 'sms-erase@example.com', '12' );
		$this->create_order( 'someone-else@example.com', '0755888999' );

		$response = $this->run_eraser( 'sms-erase@example.com' );

		$this->assertSame( array( 'sms-erase@example.com' ), $this->anonymised_emails() );
		$this->assertSame( array( '0722111222', '0744666777', '40733444555' ), $this->unsubscribed_phones() );
		$this->assertTrue( $response['items_removed'] );
		$this->assertFalse( $response['items_retained'] );
		$this->assertContains( '3 phone numbers were unsubscribed from the NewsMAN SMS list.', $response['messages'] );
	}

	/**
	 * SMS only (email list not connected): the eraser is listed and sends only the phone numbers;
	 * without SMS no phone number is sent.
	 */
	public function test_erase_sms_only_and_sms_off() {
		update_option( 'newsman_usesms', 'on' );
		update_option( 'newsman_api', '' );
		$user_id = $this->create_user( 'customer', 'sms-only@example.com', 'Ana', 'Pop' );
		update_user_meta( $user_id, 'billing_phone', '0722000111' );

		$response = $this->run_eraser( 'sms-only@example.com' );
		$this->assertSame( array( 'sms.saveUnsubscribe' ), $this->endpoints() );
		$this->assertSame( array( '1 phone number was unsubscribed from the NewsMAN SMS list.' ), $response['messages'] );

		$this->calls = array();
		update_option( 'newsman_api', 'on' );
		update_option( 'newsman_usesms', '' );
		$this->run_eraser( 'sms-only@example.com' );
		$this->assertSame( array( 'subscriber.anonymiseEmail' ), $this->endpoints() );
	}

	/**
	 * A phone number not on the SMS list is not an error; a NewsMAN error is reported with the number.
	 */
	public function test_erase_phone_not_on_the_list_and_errors() {
		update_option( 'newsman_usesms', 'on' );
		$user_id = $this->create_user( 'customer', 'sms-missing@example.com', 'Ana', 'Pop' );
		update_user_meta( $user_id, 'billing_phone', '0722000222' );

		$this->answers = array( 'sms.saveUnsubscribe' => 'missing' );
		$response      = $this->run_eraser( 'sms-missing@example.com' );
		$this->assertSame( array( '0722000222' ), $this->unsubscribed_phones() );
		$this->assertFalse( $response['items_retained'] );
		$this->assertSame( array( 'The email address was anonymised in the NewsMAN list.' ), $response['messages'] );

		$this->answers = array( 'sms.saveUnsubscribe' => 'error' );
		$response      = $this->run_eraser( 'sms-missing@example.com' );
		$this->assertTrue( $response['items_retained'] );
		$this->assertContains( 'NewsMAN could not unsubscribe the phone number 0722000222: Failure. Erase the personal data again later.', $response['messages'] );
	}

	/**
	 * Deleting a user anonymises the email and unsubscribes the account billing phone; in the background
	 * the phone numbers travel with the action, read while the user still exists.
	 */
	public function test_deleted_user_phone_numbers() {
		update_option( 'newsman_usesms', 'on' );
		$user_id = $this->create_user( 'customer', 'sms-deleted@example.com', 'Ana', 'Pop' );
		update_user_meta( $user_id, 'billing_phone', '0722 000 333' );
		$this->delete_account( $user_id );
		$this->assertSame( array( 'sms-deleted@example.com' ), $this->anonymised_emails() );
		$this->assertSame( array( '0722000333' ), $this->unsubscribed_phones() );

		if ( ! $this->has_woocommerce() ) {
			return;
		}
		$this->calls = array();
		update_option( 'newsman_developer_use_action_scheduler', 'on' );
		as_unschedule_all_actions( Eraser::BACKGROUND_HOOK_EVENT );
		$user_id = $this->create_user( 'customer', 'sms-deleted-later@example.com', 'Ana', 'Pop' );
		update_user_meta( $user_id, 'billing_phone', '0722000444' );
		$this->create_order( 'sms-deleted-later@example.com', '0722000555' );
		$this->delete_account( $user_id );

		$args = array( 'sms-deleted-later@example.com', get_current_blog_id(), array( '0722000444', '0722000555' ), Eraser::MODE_ANONYMISE );
		$this->assertSame( array(), $this->endpoints() );
		$this->assertTrue( as_has_scheduled_action( Eraser::BACKGROUND_HOOK_EVENT, $args ) );
		do_action_ref_array( Eraser::BACKGROUND_HOOK_EVENT, $args );
		$this->assertSame( array( 'sms-deleted-later@example.com' ), $this->anonymised_emails() );
		$this->assertSame( array( '0722000444', '0722000555' ), $this->unsubscribed_phones() );
		as_unschedule_all_actions( Eraser::BACKGROUND_HOOK_EVENT );
	}

	/**
	 * Multisite: deleting a user from the network anonymises the email in the list of
	 * every site it belonged to, once. Removing a user from a site, by hand or with
	 * wp_delete_user() (which only removes the user from the site on a network), sends
	 * nothing: the account still exists.
	 */
	public function test_multisite_deleted_user_is_anonymised_in_each_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run the suite with WP_MULTISITE=1.' );
		}
		require_once ABSPATH . 'wp-admin/includes/ms.php';

		$main_id   = get_current_blog_id();
		$second_id = self::factory()->blog->create();
		switch_to_blog( $second_id );
		$this->enable_api();
		update_option( 'newsman_list', '2' );
		update_option( 'newsman_developer_use_action_scheduler', '' );
		restore_current_blog();

		$user_id = $this->create_user( 'customer', 'network-user@example.com', 'Ana', 'Pop' );
		add_user_to_blog( $main_id, $user_id, 'customer' );
		add_user_to_blog( $second_id, $user_id, 'customer' );

		$this->assertTrue( wpmu_delete_user( $user_id ) );

		$lists = array();
		foreach ( $this->calls as $call ) {
			$this->assertSame( 'subscriber.anonymiseEmail', $call[0] );
			$this->assertSame( 'network-user@example.com', $call[1]['email'] );
			$lists[] = (string) $call[1]['list_id'];
		}
		sort( $lists );
		$this->assertSame( array( '1', '2' ), $lists );

		$this->calls = array();
		$user_id     = $this->create_user( 'customer', 'site-user@example.com', 'Ana', 'Pop' );
		add_user_to_blog( $second_id, $user_id, 'customer' );
		switch_to_blog( $second_id );
		wp_delete_user( $user_id );
		restore_current_blog();
		$this->assertFalse( is_user_member_of_blog( $user_id, $second_id ) );
		$this->assertNotFalse( get_userdata( $user_id ) );
		$this->assertSame( array(), $this->endpoints() );

		add_user_to_blog( $second_id, $user_id, 'customer' );
		remove_user_from_blog( $user_id, $second_id );
		remove_user_from_blog( $user_id, $main_id );
		$this->assertSame( array(), $this->endpoints() );

		add_filter( 'newsman_privacy_erase_deleted_user', '__return_true' );
		add_user_to_blog( $second_id, $user_id, 'customer' );
		remove_user_from_blog( $user_id, $second_id );
		remove_filter( 'newsman_privacy_erase_deleted_user', '__return_true' );
		$this->assertSame( array( 'site-user@example.com' ), $this->anonymised_emails() );
		$this->assertSame( '2', (string) $this->calls[0][1]['list_id'] );
	}

	/**
	 * With Action Scheduler the deletion only schedules the anonymise; the action sends it.
	 */
	public function test_deleted_user_is_anonymised_in_the_background() {
		$this->require_woocommerce();
		update_option( 'newsman_developer_use_action_scheduler', 'on' );
		$blog_id = get_current_blog_id();
		$args    = array( 'deleted-later@example.com', $blog_id, array(), Eraser::MODE_ANONYMISE );
		as_unschedule_all_actions( Eraser::BACKGROUND_HOOK_EVENT );

		$this->delete_account( $this->create_user( 'customer', 'deleted-later@example.com', 'Ana', 'Pop' ) );

		$this->assertSame( array(), $this->endpoints() );
		$this->assertTrue( as_has_scheduled_action( Eraser::BACKGROUND_HOOK_EVENT, $args ) );
		$this->assertNotFalse( has_action( Eraser::BACKGROUND_HOOK_EVENT ), 'Nothing handles the scheduled anonymise.' );

		do_action_ref_array( Eraser::BACKGROUND_HOOK_EVENT, $args );
		$this->assertSame( array( 'deleted-later@example.com' ), $this->anonymised_emails() );

		as_unschedule_all_actions( Eraser::BACKGROUND_HOOK_EVENT );
	}

	/**
	 * The scheduled action does nothing once the plugin is disconnected, and fails on errors.
	 */
	public function test_scheduled_action_checks_connection_and_fails_on_errors() {
		$eraser = new Eraser();

		update_option( 'newsman_api', '' );
		$eraser->run_scheduled( 'later-off@example.com', get_current_blog_id() );
		$this->assertSame( array(), $this->endpoints() );

		update_option( 'newsman_api', 'on' );
		$this->answer = 'missing';
		$eraser->run_scheduled( 'later-missing@example.com', get_current_blog_id() );
		$this->assertSame( array( 'later-missing@example.com' ), $this->anonymised_emails() );

		$eraser       = new class() extends Eraser {
			/**
			 * No wait in tests.
			 */
			public const WAIT_RETRY_TIMEOUT = 0;
		};
		$this->answer = 'error';
		$this->expectException( \Exception::class );
		try {
			$eraser->run_scheduled( 'later-error@example.com', get_current_blog_id() );
		} finally {
			// One try and one retry.
			$this->assertSame( array( 'later-missing@example.com', 'later-error@example.com', 'later-error@example.com' ), $this->anonymised_emails() );
		}
	}

	/**
	 * The background unsubscribe of the My Account page has a handler when its setting is on.
	 */
	public function test_account_unsubscribe_background_action_has_a_handler() {
		$this->require_woocommerce();
		update_option( 'newsman_developer_use_action_scheduler', 'on' );
		update_option( 'newsman_developer_use_as_unsubscribe', 'on' );
		remove_all_actions( \Newsman\Scheduler\Unsubscribe\Email::BACKGROUND_HOOK_EVENT );

		$this->assertContains( '\Newsman\Scheduler\Unsubscribe\Email', \WP_Newsman::init()->get_known_scheduled_classes() );
		( new \Newsman\Scheduler\Unsubscribe\Email() )->init_hooks();

		$this->assertNotFalse( has_action( \Newsman\Scheduler\Unsubscribe\Email::BACKGROUND_HOOK_EVENT ) );
		do_action( \Newsman\Scheduler\Unsubscribe\Email::BACKGROUND_HOOK_EVENT, 'account-later@example.com', array(), true );
		$this->assertSame( array( 'subscriber.saveUnsubscribe' ), $this->endpoints() );
		$this->assertSame( 'account-later@example.com', $this->calls[0][1]['email'] );
	}

	/**
	 * Actions scheduled by version 3.7.41 under the old hook still run, and anonymise.
	 */
	public function test_legacy_background_hook_still_anonymises() {
		$this->assertNotFalse( has_action( Eraser::LEGACY_BACKGROUND_HOOK_EVENT ), 'Nothing handles the actions scheduled by 3.7.41.' );

		do_action_ref_array( Eraser::LEGACY_BACKGROUND_HOOK_EVENT, array( 'legacy-later@example.com', get_current_blog_id(), array() ) );

		$this->assertSame( array( 'legacy-later@example.com' ), $this->anonymised_emails() );
	}

	/**
	 * WooCommerce Personal data retention deletes the inactive accounts: nothing is sent, nobody
	 * asked to be forgotten. The same account deleted by hand is anonymised; a filter can send
	 * for the cleanup too.
	 */
	public function test_woocommerce_inactive_accounts_cleanup_sends_nothing() {
		$this->require_woocommerce();
		if ( ! class_exists( '\WC_Privacy' ) ) {
			$this->markTestSkipped( 'WC_Privacy is not loaded.' );
		}
		update_option(
			'woocommerce_delete_inactive_accounts',
			array(
				'number' => '1',
				'unit'   => 'months',
			)
		);
		update_option( 'newsman_usesms', 'on' );

		$inactive = $this->create_user( 'customer', 'inactive@example.com', 'Ana', 'Pop' );
		update_user_meta( $inactive, 'wc_last_active', (string) strtotime( '-2 months' ) );
		update_user_meta( $inactive, 'billing_phone', '0722000666' );
		$active = $this->create_user( 'customer', 'active@example.com', 'Ana', 'Pop' );
		update_user_meta( $active, 'wc_last_active', (string) time() );

		\WC_Privacy::delete_inactive_accounts();

		$this->assertUserDeleted( $inactive );
		$this->assertNotFalse( get_userdata( $active ) );
		$this->assertSame( array(), $this->endpoints() );

		$by_hand = $this->create_user( 'customer', 'inactive-by-hand@example.com', 'Ana', 'Pop' );
		update_user_meta( $by_hand, 'wc_last_active', (string) strtotime( '-2 months' ) );
		$this->delete_account( $by_hand );
		$this->assertSame( array( 'inactive-by-hand@example.com' ), $this->anonymised_emails() );

		$this->calls = array();
		$filtered    = $this->create_user( 'customer', 'inactive-filtered@example.com', 'Ana', 'Pop' );
		update_user_meta( $filtered, 'wc_last_active', (string) strtotime( '-2 months' ) );
		add_filter( 'newsman_privacy_erase_deleted_user', '__return_true' );
		\WC_Privacy::delete_inactive_accounts();
		remove_filter( 'newsman_privacy_erase_deleted_user', '__return_true' );
		$this->assertSame( array( 'inactive-filtered@example.com' ), $this->anonymised_emails() );

		delete_option( 'woocommerce_delete_inactive_accounts' );
	}

	/**
	 * Multisite: deleting a whole site removes each of its users from it, and nothing is sent;
	 * the store owner manages the list in NewsMAN. Deleting the same user from the network
	 * later anonymises it in the list of the sites it still belongs to.
	 */
	public function test_multisite_site_deletion_sends_nothing() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run the suite with WP_MULTISITE=1.' );
		}
		require_once ABSPATH . 'wp-admin/includes/ms.php';

		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		$this->enable_api();
		update_option( 'newsman_list', '3' );
		update_option( 'newsman_developer_use_action_scheduler', '' );
		restore_current_blog();

		$user_id = $this->create_user( 'customer', 'site-deleted@example.com', 'Ana', 'Pop' );
		add_user_to_blog( $site_id, $user_id, 'customer' );
		$other_id = $this->create_user( 'customer', 'site-other@example.com', 'Ana', 'Pop' );
		add_user_to_blog( $site_id, $other_id, 'customer' );

		$this->assertTrue( is_user_member_of_blog( $user_id, $site_id ) );
		wpmu_delete_blog( $site_id, true );

		$this->assertEmpty( get_site( $site_id ) );
		$this->assertNotFalse( get_userdata( $user_id ) );
		$this->assertSame( array(), $this->endpoints() );

		$second_id = self::factory()->blog->create();
		switch_to_blog( $second_id );
		$this->enable_api();
		update_option( 'newsman_list', '4' );
		update_option( 'newsman_developer_use_action_scheduler', '' );
		restore_current_blog();
		add_user_to_blog( $second_id, $user_id, 'customer' );
		remove_user_from_blog( $user_id, get_current_blog_id() );
		$this->assertSame( array(), $this->endpoints() );
		$this->assertTrue( wpmu_delete_user( $user_id ) );
		$this->assertSame( array( 'site-deleted@example.com' ), $this->anonymised_emails() );
		$this->assertSame( '4', (string) $this->calls[0][1]['list_id'] );
	}

	/**
	 * With "Anonymise in NewsMAN when users are deleted" off, the email of a deleted user is
	 * unsubscribed instead (no confirmation flow, also with double opt-in); the phone numbers
	 * are still unsubscribed. A missing option behaves as on.
	 */
	public function test_deleted_user_is_unsubscribed_when_the_setting_is_off() {
		update_option( 'newsman_anonymise_on_delete', '' );
		update_option( 'newsman_newslettertype', 'init' );
		update_option( 'newsman_usesms', 'on' );
		$user_id = $this->create_user( 'customer', 'deleted-unsub@example.com', 'Ana', 'Pop' );
		update_user_meta( $user_id, 'billing_phone', '0722000777' );

		$this->assertTrue( $this->delete_account( $user_id ) );

		$this->assertSame( array( 'subscriber.saveUnsubscribe', 'sms.saveUnsubscribe' ), $this->endpoints() );
		$this->assertSame( array( 'deleted-unsub@example.com' ), $this->unsubscribed_emails() );
		$this->assertSame( '1', (string) $this->calls[0][1]['list_id'] );
		$this->assertSame( array( '0722000777' ), $this->unsubscribed_phones() );

		$this->calls  = array();
		$this->answer = 'missing';
		$this->assertTrue( $this->delete_account( $this->create_user( 'customer', 'deleted-unsub-missing@example.com', 'Ana', 'Pop' ) ) );
		$this->assertSame( array( 'deleted-unsub-missing@example.com' ), $this->unsubscribed_emails() );

		$this->calls  = array();
		$this->answer = 'ok';
		delete_option( 'newsman_anonymise_on_delete' );
		$this->delete_account( $this->create_user( 'customer', 'deleted-no-option@example.com', 'Ana', 'Pop' ) );
		$this->assertSame( array( 'deleted-no-option@example.com' ), $this->anonymised_emails() );

		$this->calls = array();
		update_option( 'newsman_anonymise_on_delete', 'on' );
		add_filter(
			'newsman_privacy_delete_mode',
			function () {
				return Eraser::MODE_UNSUBSCRIBE;
			}
		);
		$this->delete_account( $this->create_user( 'customer', 'deleted-mode-filter@example.com', 'Ana', 'Pop' ) );
		remove_all_filters( 'newsman_privacy_delete_mode' );
		$this->assertSame( array( 'deleted-mode-filter@example.com' ), $this->unsubscribed_emails() );
	}

	/**
	 * Tools > Erase Personal Data always anonymises, whatever the setting.
	 */
	public function test_erase_anonymises_when_the_setting_is_off() {
		update_option( 'newsman_anonymise_on_delete', '' );

		$response = $this->run_eraser( 'erase-setting-off@example.com' );

		$this->assertTrue( $response['items_removed'] );
		$this->assertSame( array( 'erase-setting-off@example.com' ), $this->anonymised_emails() );
	}

	/**
	 * In the background the action carries the mode chosen when the user was deleted; actions
	 * scheduled without a mode (3.7.41 and 3.7.42) follow the setting when they run.
	 */
	public function test_background_unsubscribe_when_the_setting_is_off() {
		$this->require_woocommerce();
		update_option( 'newsman_anonymise_on_delete', '' );
		update_option( 'newsman_developer_use_action_scheduler', 'on' );
		as_unschedule_all_actions( Eraser::BACKGROUND_HOOK_EVENT );
		$args = array( 'deleted-unsub-later@example.com', get_current_blog_id(), array(), Eraser::MODE_UNSUBSCRIBE );

		$this->delete_account( $this->create_user( 'customer', 'deleted-unsub-later@example.com', 'Ana', 'Pop' ) );

		$this->assertSame( array(), $this->endpoints() );
		$this->assertTrue( as_has_scheduled_action( Eraser::BACKGROUND_HOOK_EVENT, $args ) );
		update_option( 'newsman_anonymise_on_delete', 'on' );
		do_action_ref_array( Eraser::BACKGROUND_HOOK_EVENT, $args );
		$this->assertSame( array( 'deleted-unsub-later@example.com' ), $this->unsubscribed_emails() );
		as_unschedule_all_actions( Eraser::BACKGROUND_HOOK_EVENT );

		$this->calls = array();
		update_option( 'newsman_anonymise_on_delete', '' );
		do_action_ref_array( Eraser::BACKGROUND_HOOK_EVENT, array( 'old-action@example.com', get_current_blog_id(), array() ) );
		do_action_ref_array( Eraser::LEGACY_BACKGROUND_HOOK_EVENT, array( 'older-action@example.com', get_current_blog_id(), array() ) );
		$this->assertSame( array( 'old-action@example.com', 'older-action@example.com' ), $this->unsubscribed_emails() );
	}
}
