<?php
/**
 * Tests for the NewsMAN data in Tools > Export Personal Data and the suggested
 * privacy policy text.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Privacy\Exporter;

/**
 * Personal data exporter tests.
 */
class PrivacyExporterTest extends NewsmanTestCase {
	/**
	 * Requests seen by the fake API: [ endpoint, query ].
	 *
	 * @var array
	 */
	private $calls = array();

	/**
	 * Answer per endpoint: found, missing or error.
	 *
	 * @var array
	 */
	private $answers = array();

	/**
	 * Connect the plugin and fake the NewsMAN API.
	 */
	public function set_up() {
		parent::set_up();
		$this->enable_api();
		update_option( 'newsman_usesms', '' );
		update_option( 'newsman_smslist', '77' );
		$this->calls   = array();
		$this->answers = array();
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );
	}

	/**
	 * Remove the fake API.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10 );
		parent::tear_down();
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
		unset( $pre, $args );
		$endpoint = preg_match( '#/([a-zA-Z]+\.[a-zA-Z]+)\.json#', $url, $m ) ? $m[1] : $url;
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$this->calls[] = array( $endpoint, $query );
		$answer        = isset( $this->answers[ $endpoint ] ) ? $this->answers[ $endpoint ] : 'found';

		$response = array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
		if ( 'missing' === $answer ) {
			$response['response']['code'] = 400;
			$response['body']             = '{"err":true,"message":"Could not load subscriber","code":121}';
		} elseif ( 'error' === $answer ) {
			$response['response']['code'] = 500;
			$response['body']             = '{"err":"Error","message":"Failure","code":500}';
		} elseif ( 'subscriber.getByEmail' === $endpoint ) {
			$response['body'] = wp_json_encode(
				array(
					'list_id'           => 1,
					'subscriber_id'     => 9,
					'email'             => $query['email'],
					'status'            => 'subscribed',
					'subscription_type' => 'api_saved',
					'subscribe_date'    => '2026-11-11 10:00:00',
					'unsubscribe_date'  => '',
					'firstname'         => 'Ana',
					'lastname'          => 'Pop',
					'ip'                => '203.0.113.9',
					'tags'              => array( 'vip' ),
					'props'             => array(
						'city'  => 'Cluj',
						'extra' => array( 'a' => 1 ),
					),
				)
			);
		} else {
			$response['body'] = wp_json_encode(
				array(
					'list_id'          => 77,
					'telephone'        => '4' . $query['telephone'],
					'status'           => 'unsubscribed',
					'subscribe_date'   => '2026-11-11 10:00:00',
					'unsubscribe_date' => '2026-11-11 11:00:00',
					'tags'             => array(),
					'props'            => array(),
				)
			);
		}
		return $response;
	}

	/**
	 * Run the NewsMAN exporter the way Tools > Export Personal Data does.
	 *
	 * @param string $email Email address.
	 * @return array|\WP_Error
	 */
	private function run_exporter( $email ) {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$this->assertArrayHasKey( Exporter::EXPORTER_ID, $exporters, 'The NewsMAN exporter is not registered.' );
		return call_user_func( $exporters[ Exporter::EXPORTER_ID ]['callback'], $email, 1 );
	}

	/**
	 * Values of an exported item, keyed by label.
	 *
	 * @param array $item Exported item.
	 * @return array
	 */
	private function pairs( $item ) {
		return array_column( $item['data'], 'value', 'name' );
	}

	/**
	 * The exporter is listed when the plugin is connected or SMS is set up, not otherwise.
	 */
	public function test_exporter_is_registered_only_when_connected() {
		$this->assertArrayHasKey( Exporter::EXPORTER_ID, apply_filters( 'wp_privacy_personal_data_exporters', array() ) );

		update_option( 'newsman_api', '' );
		$this->assertArrayNotHasKey( Exporter::EXPORTER_ID, apply_filters( 'wp_privacy_personal_data_exporters', array() ) );

		update_option( 'newsman_usesms', 'on' );
		$this->assertArrayHasKey( Exporter::EXPORTER_ID, apply_filters( 'wp_privacy_personal_data_exporters', array() ) );
	}

	/**
	 * The subscriber of the email list is exported, read live from NewsMAN.
	 */
	public function test_export_email_subscriber() {
		$response = $this->run_exporter( 'export-me@example.com' );

		$this->assertTrue( $response['done'] );
		$this->assertCount( 1, $response['data'] );
		$item = $response['data'][0];
		$this->assertSame( 'newsman', $item['group_id'] );
		$this->assertSame( 'NewsMAN', $item['group_label'] );
		$this->assertSame( 'newsman-email-list', $item['item_id'] );
		$pairs = $this->pairs( $item );
		$this->assertSame( '1', $pairs['NewsMAN email list'] );
		$this->assertSame( 'export-me@example.com', $pairs['Email'] );
		$this->assertSame( 'subscribed', $pairs['Status'] );
		$this->assertSame( 'Ana', $pairs['First name'] );
		$this->assertSame( 'vip', $pairs['Tags'] );
		$this->assertSame( 'Cluj', $pairs['city'] );
		$this->assertSame( '{"a":1}', $pairs['extra'] );
		$this->assertArrayNotHasKey( 'Unsubscribed on', $pairs, 'Empty values are left out.' );
		$this->assertSame(
			array(
				array(
					'subscriber.getByEmail',
					array(
						'list_id' => '1',
						'email'   => 'export-me@example.com',
					),
				),
			),
			$this->calls
		);
	}

	/**
	 * An email that is not on the list exports nothing, without an error.
	 */
	public function test_export_email_not_on_the_list() {
		$this->answers = array( 'subscriber.getByEmail' => 'missing' );
		$response      = $this->run_exporter( 'stranger@example.com' );
		$this->assertSame( array(), $response['data'] );
		$this->assertTrue( $response['done'] );
	}

	/**
	 * A NewsMAN error stops the export with a message, so it is not shown as complete.
	 */
	public function test_export_error() {
		$this->answers = array( 'subscriber.getByEmail' => 'error' );
		$response      = $this->run_exporter( 'fails@example.com' );
		$this->assertWPError( $response );
		$this->assertSame( 'NewsMAN could not be read: Failure. Export the personal data again later.', $response->get_error_message() );
	}

	/**
	 * With SMS set up, the SMS list entries of the person's phone numbers are exported;
	 * a phone number not on the SMS list is skipped.
	 */
	public function test_export_sms_subscribers() {
		update_option( 'newsman_usesms', 'on' );
		$user_id = $this->create_user( 'customer', 'sms-export@example.com', 'Ana', 'Pop' );
		update_user_meta( $user_id, 'billing_phone', '0722 111 222' );

		$response = $this->run_exporter( 'sms-export@example.com' );
		$this->assertCount( 2, $response['data'] );
		$this->assertSame( 'newsman-sms-list-0722111222', $response['data'][1]['item_id'] );
		$pairs = $this->pairs( $response['data'][1] );
		$this->assertSame( '77', $pairs['NewsMAN SMS list'] );
		$this->assertSame( '40722111222', $pairs['Phone number'] );
		$this->assertSame( 'unsubscribed', $pairs['Status'] );

		$this->answers = array( 'sms.getByTelephone' => 'missing' );
		$response      = $this->run_exporter( 'sms-export@example.com' );
		$this->assertCount( 1, $response['data'] );
	}

	/**
	 * The suggested privacy policy text names NewsMAN, the cookies and the NewsMAN policies.
	 */
	public function test_privacy_policy_text() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
		set_current_screen( 'dashboard' );
		global $wp_actions;
		$did_admin_init           = isset( $wp_actions['admin_init'] ) ? $wp_actions['admin_init'] : null;
		$wp_actions['admin_init'] = 1; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		( new Exporter() )->add_privacy_policy_content();

		$text = '';
		foreach ( WP_Privacy_Policy_Content::get_suggested_policy_text() as $policy ) {
			if ( 'NewsMAN' === $policy['plugin_name'] ) {
				$text = $policy['policy_text'];
			}
		}
		if ( null === $did_admin_init ) {
			unset( $wp_actions['admin_init'] );
		}
		set_current_screen( 'front' );

		$this->assertStringContainsString( 'We use NewsMAN, a service of Dazoot Software', $text );
		$this->assertStringContainsString( 'cookies whose names start with _nzm', $text );
		$this->assertStringContainsString( '<a href="https://www.newsman.com/privacy">NewsMAN privacy policy</a>', $text );
		$this->assertStringContainsString( '<a href="https://www.newsman.com/cookies">NewsMAN cookie policy</a>', $text );
		$this->assertStringContainsString( 'class="privacy-policy-tutorial"', $text );
		$this->assertStringContainsString( 'or when your account is deleted, NewsMAN replaces your email address', $text );

		update_option( 'newsman_anonymise_on_delete', '' );
		$text = $this->policy_text();
		$this->assertStringContainsString( 'When you ask us to erase your personal data, NewsMAN replaces your email address', $text );
		$this->assertStringContainsString( 'When your account is deleted, we unsubscribe your email address from our NewsMAN lists.', $text );
		update_option( 'newsman_anonymise_on_delete', 'on' );
	}

	/**
	 * The NewsMAN suggested privacy policy text, added now.
	 *
	 * @return string
	 */
	private function policy_text() {
		set_current_screen( 'dashboard' );
		global $wp_actions;
		$did_admin_init           = isset( $wp_actions['admin_init'] ) ? $wp_actions['admin_init'] : null;
		$wp_actions['admin_init'] = 1; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		( new Exporter() )->add_privacy_policy_content();

		$text = '';
		foreach ( WP_Privacy_Policy_Content::get_suggested_policy_text() as $policy ) {
			if ( 'NewsMAN' === $policy['plugin_name'] ) {
				$text = $policy['policy_text'];
			}
		}
		if ( null === $did_admin_init ) {
			unset( $wp_actions['admin_init'] );
		}
		set_current_screen( 'front' );
		return $text;
	}
}
