<?php
/**
 * Tests for the shared subscribe helper used by the form integrations and the subscribe form block.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Subscribe\Helper as SubscribeHelper;

/**
 * Subscribe helper tests against a faked Newsman API: which calls run for each subscriber status,
 * for newsletter subscriptions and for other forms.
 */
class SubscribeHelperTest extends NewsmanTestCase {
	/**
	 * Status getByEmail answers with, or null for "not found".
	 *
	 * @var string|null
	 */
	private $status = null;

	/**
	 * Endpoints that fail with an API error.
	 *
	 * @var array
	 */
	private $failing = array();

	/**
	 * Requests seen by the fake API: endpoint => decoded POST body (or query for GET).
	 *
	 * @var array
	 */
	private $calls = array();

	/**
	 * Arguments of the newsman_resubscribed action.
	 *
	 * @var array|null
	 */
	private $resubscribed = null;

	/**
	 * Fake the API and record the resubscribe action.
	 */
	public function set_up() {
		parent::set_up();
		$this->enable_api();
		$this->status       = null;
		$this->failing      = array();
		$this->calls        = array();
		$this->resubscribed = null;
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );
		add_action( 'newsman_resubscribed', array( $this, 'record_resubscribed' ), 10, 7 );
	}

	/**
	 * Action callback for newsman_resubscribed.
	 *
	 * @param int    $blog_id    Blog ID.
	 * @param string $list_id    List ID.
	 * @param string $email      Email.
	 * @param array  $properties Properties.
	 * @param string $ip         IP.
	 * @param string $status     Status before.
	 * @param string $optin_mode Opt-in mode.
	 * @return void
	 */
	public function record_resubscribed( $blog_id, $list_id, $email, $properties, $ip, $status, $optin_mode ) {
		unset( $blog_id, $list_id, $properties, $ip );
		$this->resubscribed = array( $email, $status, $optin_mode );
	}

	/**
	 * Fake Newsman API.
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array
	 */
	public function fake_newsman_api( $pre, $args, $url ) {
		unset( $pre );
		$endpoint = preg_match( '#/([a-zA-Z]+\.[a-zA-Z]+)\.json#', $url, $m ) ? $m[1] : $url;
		$body     = isset( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		if ( null === $body ) {
			$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
			wp_parse_str( $query, $body );
		}
		$this->calls[ $endpoint ] = $body;

		$response = array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => 'true',
		);

		$not_found = 'subscriber.getByEmail' === $endpoint && null === $this->status;
		if ( $not_found || in_array( $endpoint, $this->failing, true ) ) {
			$response['response'] = array(
				'code'    => 500,
				'message' => 'Error',
			);
			$response['body']     = '{"err":"Error","message":"Failure","code":500}';
		} elseif ( 'subscriber.getByEmail' === $endpoint ) {
			$response['body'] = wp_json_encode(
				array(
					'subscriber_id' => 77,
					'email'         => 'ana@example.com',
					'status'        => $this->status,
				)
			);
		} elseif ( in_array( $endpoint, array( 'subscriber.saveSubscribe', 'subscriber.initSubscribe' ), true ) ) {
			$response['body'] = '77';
		}
		return $response;
	}

	/**
	 * Run the helper for ana@example.com with a phone property, names and segment 9.
	 *
	 * @param string $optin_mode `'single'` or `'double'`.
	 * @param bool   $newsletter Newsletter subscription.
	 * @param array  $properties Properties.
	 * @return void
	 */
	private function subscribe( $optin_mode, $newsletter, $properties = array( 'phone' => '0721' ) ) {
		SubscribeHelper::subscribe_with_props( 1, '5', 'ana@example.com', $properties, '203.0.113.1', $optin_mode, 'Ana', 'Pop', '9', 'confirm-7', $newsletter );
	}

	/**
	 * Called endpoints, in call order.
	 *
	 * @return array
	 */
	private function endpoints() {
		return array_keys( $this->calls );
	}

	/**
	 * Newsletter + unsubscribed + single opt-in: saveSubscribe again, props, segment.
	 */
	public function test_newsletter_resubscribes_unsubscribed_single() {
		$this->status = 'unsubscribed';
		$this->subscribe( 'single', true );

		$this->assertSame( array( 'subscriber.getByEmail', 'subscriber.saveSubscribe', 'subscriber.updateProps', 'segment.addSubscriber' ), $this->endpoints() );
		$this->assertSame( 'Ana', $this->calls['subscriber.saveSubscribe']['firstname'] );
		$this->assertSame( array( 'phone' => '0721' ), $this->calls['subscriber.saveSubscribe']['props'] );
		$this->assertSame( array( 'ana@example.com', 'unsubscribed', 'single' ), $this->resubscribed );
	}

	/**
	 * Newsletter + pending + double opt-in: initSubscribe again with the segment and the confirmation form inline.
	 */
	public function test_newsletter_resends_confirmation_for_pending_double() {
		$this->status = 'pending';
		$this->subscribe( 'double', true );

		$this->assertSame( array( 'subscriber.getByEmail', 'subscriber.initSubscribe', 'subscriber.updateProps' ), $this->endpoints() );
		$this->assertSame(
			array(
				'segments' => array( 9 ),
				'form_id'  => 'confirm-7',
			),
			$this->calls['subscriber.initSubscribe']['options']
		);
		$this->assertSame( array( 'ana@example.com', 'pending', 'double' ), $this->resubscribed );
	}

	/**
	 * Other forms keep refreshing an unsubscribed row only, they never subscribe it again.
	 */
	public function test_other_forms_do_not_resubscribe() {
		foreach ( array( 'unsubscribed', 'pending' ) as $status ) {
			$this->status = $status;
			$this->calls  = array();
			$this->subscribe( 'single', false );

			$this->assertSame( array( 'subscriber.getByEmail', 'subscriber.updateProps', 'segment.addSubscriber' ), $this->endpoints(), $status );
		}
		$this->assertNull( $this->resubscribed );
	}

	/**
	 * A confirmed subscriber is refreshed the same way for newsletter and other forms.
	 */
	public function test_subscribed_is_only_refreshed() {
		foreach ( array( true, false ) as $newsletter ) {
			$this->status = 'subscribed';
			$this->calls  = array();
			$this->subscribe( 'double', $newsletter );

			$this->assertSame( array( 'subscriber.getByEmail', 'subscriber.update', 'subscriber.updateProps', 'segment.addSubscriber' ), $this->endpoints() );
		}
		$this->assertNull( $this->resubscribed );
	}

	/**
	 * An email not on the list is created as before.
	 */
	public function test_new_email_is_created() {
		$this->subscribe( 'double', true );
		$this->assertSame( array( 'subscriber.getByEmail', 'subscriber.initSubscribe' ), $this->endpoints() );
	}

	/**
	 * Without properties there is no updateProps call after the re-subscribe.
	 */
	public function test_resubscribe_without_properties_skips_update_props() {
		$this->status = 'unsubscribed';
		$this->subscribe( 'double', true, array() );
		$this->assertSame( array( 'subscriber.getByEmail', 'subscriber.initSubscribe' ), $this->endpoints() );
	}

	/**
	 * A failed updateProps after a successful re-subscribe does not fail the subscribe;
	 * a failed saveSubscribe does.
	 */
	public function test_resubscribe_failures() {
		$this->status  = 'unsubscribed';
		$this->failing = array( 'subscriber.updateProps' );
		$this->subscribe( 'single', true );
		$this->assertSame( array( 'ana@example.com', 'unsubscribed', 'single' ), $this->resubscribed );

		$this->failing      = array( 'subscriber.saveSubscribe' );
		$this->resubscribed = null;
		$this->expectException( \Exception::class );
		try {
			$this->subscribe( 'single', true );
		} finally {
			$this->assertNull( $this->resubscribed );
		}
	}

	/**
	 * The newsman_subscribe_newsletter filter turns the newsletter behavior on for other callers.
	 */
	public function test_newsletter_filter() {
		$this->status = 'unsubscribed';
		add_filter( 'newsman_subscribe_newsletter', '__return_true' );
		$this->subscribe( 'single', false );
		remove_filter( 'newsman_subscribe_newsletter', '__return_true' );

		$this->assertContains( 'subscriber.saveSubscribe', $this->endpoints() );
	}
}
