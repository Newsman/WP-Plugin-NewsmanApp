<?php
/**
 * Tests for the subscribe form and embedded form blocks.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Form\Block\SignedSettings;
use Newsman\Form\Block\SubscribeForm;
use Newsman\Form\Block\SubscribeProcessor;

/**
 * Subscribe form block tests: registration, render, signed settings and the submit handler.
 */
class SubscribeFormBlockTest extends NewsmanTestCase {
	/**
	 * Arguments of the subscribes that reached Helper::subscribe_with_props().
	 *
	 * @var array
	 */
	private $subscribes = array();

	/**
	 * Whether capture_subscribe() lets the subscribe reach the (faked) API.
	 *
	 * @var bool
	 */
	private $let_api_calls = false;

	/**
	 * Requests seen by fake_newsman_api().
	 *
	 * @var array
	 */
	private $api_requests = array();

	/**
	 * Whether fake_newsman_api() answers every call with an API error.
	 *
	 * @var bool
	 */
	private $api_fail = false;

	/**
	 * Capture the subscribes and stop them before any Newsman API call.
	 */
	public function set_up() {
		parent::set_up();
		$this->subscribes = array();
		add_filter( 'newsman_subscribe_optin_mode', array( $this, 'capture_optin_mode' ), 10, 3 );
		add_filter( 'newsman_subscribe_names', array( $this, 'capture_names' ), 10, 1 );
		add_filter( 'newsman_subscribe_segment_id', array( $this, 'capture_segment_id' ), 10, 1 );
		add_filter( 'newsman_subscribe_newsletter', array( $this, 'capture_newsletter' ), 10, 1 );
		add_filter( 'newsman_should_subscribe', array( $this, 'capture_subscribe' ), 10, 5 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 254 );
	}

	/**
	 * Restore the request globals.
	 */
	public function tear_down() {
		unset( $_SERVER['REMOTE_ADDR'] );
		$_GET = array();
		parent::tear_down();
	}

	/**
	 * Filter callback: remember the opt-in mode.
	 *
	 * @param string $mode    Opt-in mode.
	 * @param int    $blog_id Blog ID.
	 * @param string $list_id List ID.
	 * @return string
	 */
	public function capture_optin_mode( $mode, $blog_id, $list_id ) {
		unset( $blog_id, $list_id );
		$this->subscribes['pending']['optin'] = $mode;
		return $mode;
	}

	/**
	 * Filter callback: remember the names.
	 *
	 * @param array $names Names.
	 * @return array
	 */
	public function capture_names( $names ) {
		$this->subscribes['pending']['names'] = $names;
		return $names;
	}

	/**
	 * Filter callback: remember the segment.
	 *
	 * @param string $segment_id Segment ID.
	 * @return string
	 */
	public function capture_segment_id( $segment_id ) {
		$this->subscribes['pending']['segment'] = $segment_id;
		return $segment_id;
	}

	/**
	 * Filter callback: remember whether the subscribe is a newsletter subscription.
	 *
	 * @param bool $newsletter Newsletter subscription.
	 * @return bool
	 */
	public function capture_newsletter( $newsletter ) {
		$this->subscribes['pending']['newsletter'] = $newsletter;
		return $newsletter;
	}

	/**
	 * Filter callback: record the subscribe and skip the API calls, unless a test wants them.
	 *
	 * @param bool   $should     Whether to subscribe.
	 * @param int    $blog_id    Blog ID.
	 * @param string $list_id    List ID.
	 * @param string $email      Email.
	 * @param array  $properties Properties.
	 * @return bool
	 */
	public function capture_subscribe( $should, $blog_id, $list_id, $email, $properties ) {
		unset( $blog_id );
		$pending            = isset( $this->subscribes['pending'] ) ? $this->subscribes['pending'] : array();
		$this->subscribes[] = array_merge(
			$pending,
			array(
				'list'       => $list_id,
				'email'      => $email,
				'properties' => $properties,
			)
		);
		unset( $this->subscribes['pending'] );
		return ! empty( $this->let_api_calls ) ? $should : false;
	}

	/**
	 * Request data for a block with the given attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $fields     Submitted fields.
	 * @return array
	 */
	private function submit_data( $attributes, $fields ) {
		$signed = SignedSettings::encode( ( new SubscribeForm() )->get_settings( $attributes ) );
		return array_merge(
			array(
				'nzm_settings'  => $signed['payload'],
				'nzm_signature' => $signed['signature'],
			),
			$fields
		);
	}

	/**
	 * Both blocks are registered.
	 */
	public function test_blocks_are_registered() {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'newsman/subscribe-form' ) );
		$this->assertTrue( $registry->is_registered( 'newsman/embedded-form' ) );
	}

	/**
	 * The form is not rendered while the plugin is not connected to Newsman.
	 */
	public function test_render_is_empty_when_not_connected() {
		$this->assertSame( '', trim( do_blocks( '<!-- wp:newsman/subscribe-form /-->' ) ) );
	}

	/**
	 * The rendered form has the fields chosen in the block, with signed settings.
	 */
	public function test_render_shows_selected_fields() {
		$this->enable_api();
		$html = do_blocks( '<!-- wp:newsman/subscribe-form {"showFirstname":false,"showPhone":true,"requirePhone":true,"buttonText":"Join <now>"} /-->' );

		$this->assertStringContainsString( 'name="nzm_email"', $html );
		$this->assertStringNotContainsString( 'name="nzm_firstname"', $html );
		$this->assertStringContainsString( 'name="nzm_lastname"', $html );
		$this->assertMatchesRegularExpression( '/name="nzm_phone"\s+autocomplete="tel"\s+required/', $html );
		$this->assertStringContainsString( 'name="nzm_consent"', $html );
		$this->assertStringContainsString( 'name="nzm_website"', $html );
		$this->assertStringContainsString( 'Join &lt;now&gt;', $html );

		$this->assertSame( 1, preg_match( '/name="nzm_settings" value="([^"]+)"/', $html, $payload ) );
		$this->assertSame( 1, preg_match( '/name="nzm_signature" value="([^"]+)"/', $html, $signature ) );
		$settings = SignedSettings::decode( $payload[1], $signature[1] );
		$this->assertSame(
			array(
				'firstname' => SubscribeForm::FIELD_HIDDEN,
				'lastname'  => SubscribeForm::FIELD_OPTIONAL,
				'phone'     => SubscribeForm::FIELD_REQUIRED,
			),
			$settings['fields']
		);
	}

	/**
	 * The consent text keeps links but drops scripts.
	 */
	public function test_render_consent_text_is_filtered() {
		$this->enable_api();
		$html = do_blocks(
			serialize_block(
				array(
					'blockName'    => 'newsman/subscribe-form',
					'attrs'        => array( 'consentText' => 'I agree to the <a href="https://example.com/terms">terms</a><script>alert(1)</script>' ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			)
		);

		$this->assertStringContainsString( '<a href="https://example.com/terms">terms</a>', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * A changed payload, a changed signature and non-string values are rejected.
	 */
	public function test_signed_settings_reject_tampering() {
		$signed = SignedSettings::encode( array( 'list' => '5' ) );
		$this->assertSame( array( 'list' => '5' ), SignedSettings::decode( $signed['payload'], $signed['signature'] ) );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$other = base64_encode( '{"list":"6"}' );
		$this->assertNull( SignedSettings::decode( $other, $signed['signature'] ) );
		$this->assertNull( SignedSettings::decode( $signed['payload'], str_repeat( 'a', 64 ) ) );
		$this->assertNull( SignedSettings::decode( array( $signed['payload'] ), $signed['signature'] ) );
		$this->assertNull( SignedSettings::decode( $signed['payload'], null ) );
	}

	/**
	 * A submit with a forged signature never subscribes.
	 */
	public function test_process_rejects_bad_signature() {
		$this->enable_api();
		$data                  = $this->submit_data( array(), array( 'nzm_email' => 'a@example.com' ) );
		$data['nzm_signature'] = str_repeat( '0', 64 );

		$result = ( new SubscribeProcessor() )->process( $data );
		$this->assertSame( SubscribeProcessor::CODE_ERROR, $result['code'] );
		$this->assertSame( 400, $result['status'] );
		$this->assertEmpty( $this->subscribes );
	}

	/**
	 * A filled honeypot field looks like a success but subscribes nobody.
	 */
	public function test_process_honeypot_pretends_success() {
		$this->enable_api();
		$result = ( new SubscribeProcessor() )->process(
			$this->submit_data(
				array(),
				array(
					'nzm_email'   => 'bot@example.com',
					'nzm_website' => 'https://spam.example',
				)
			)
		);
		$this->assertSame( SubscribeProcessor::CODE_SUCCESS, $result['code'] );
		$this->assertEmpty( $this->subscribes );
	}

	/**
	 * Validation: email, required fields, phone format, consent.
	 */
	public function test_process_validation() {
		$this->enable_api();
		$processor  = new SubscribeProcessor();
		$attributes = array(
			'showFirstname'    => true,
			'requireFirstname' => true,
			'showPhone'        => true,
			'showConsent'      => true,
		);
		$valid      = array(
			'nzm_email'     => 'ana@example.com',
			'nzm_firstname' => 'Ana',
			'nzm_phone'     => '+40 721 000 111',
			'nzm_consent'   => '1',
		);

		$cases = array(
			SubscribeProcessor::CODE_INVALID_EMAIL => array( 'nzm_email' => 'not-an-email' ),
			SubscribeProcessor::CODE_REQUIRED      => array( 'nzm_firstname' => '  ' ),
			SubscribeProcessor::CODE_INVALID_PHONE => array( 'nzm_phone' => 'call me maybe' ),
			SubscribeProcessor::CODE_CONSENT       => array( 'nzm_consent' => '' ),
		);
		foreach ( $cases as $code => $override ) {
			$result = $processor->process( $this->submit_data( $attributes, array_merge( $valid, $override ) ) );
			$this->assertSame( $code, $result['code'], 'Case ' . $code );
			$this->assertSame( 400, $result['status'] );
		}
		$this->assertEmpty( $this->subscribes );

		// Fields hidden in the block are ignored even when posted.
		$result = $processor->process(
			$this->submit_data(
				array(),
				array(
					'nzm_email' => 'ana@example.com',
					'nzm_phone' => 'not a phone',
				)
			)
		);
		$this->assertSame( SubscribeProcessor::CODE_SUCCESS, $result['code'] );
		$this->assertSame( array(), $this->subscribes[0]['properties'] );
	}

	/**
	 * Without a list in the block the Sync list and segment are used, with the plugin opt-in type.
	 */
	public function test_process_uses_sync_list_and_segment_by_default() {
		$this->enable_api();
		update_option( 'newsman_list', '11' );
		update_option( 'newsman_segments', '22' );
		update_option( 'newsman_newslettertype', 'save' );

		$result = ( new SubscribeProcessor() )->process(
			$this->submit_data(
				array(
					'showFirstname' => true,
					'showLastname'  => true,
					'showPhone'     => true,
				),
				array(
					'nzm_email'     => 'Ana@Example.com',
					'nzm_firstname' => 'Ana',
					'nzm_lastname'  => 'Pop',
					'nzm_phone'     => '0721 000 111',
				)
			)
		);

		$this->assertSame( SubscribeProcessor::CODE_SUCCESS, $result['code'] );
		$this->assertSame( 'Thank you for subscribing!', $result['message'] );
		$this->assertCount( 1, $this->subscribes );
		$this->assertSame( '11', $this->subscribes[0]['list'] );
		$this->assertSame( '22', $this->subscribes[0]['segment'] );
		$this->assertSame( 'single', $this->subscribes[0]['optin'] );
		$this->assertTrue( $this->subscribes[0]['newsletter'], 'The block is a newsletter subscription (unsubscribed emails are subscribed again).' );
		$this->assertSame( 'Ana@Example.com', $this->subscribes[0]['email'] );
		$this->assertSame( 'Ana', $this->subscribes[0]['names']['firstname'] );
		$this->assertSame( 'Pop', $this->subscribes[0]['names']['lastname'] );
		$this->assertSame( array( 'phone' => '0721 000 111' ), $this->subscribes[0]['properties'] );
	}

	/**
	 * A list chosen in the block wins; a segment of another list is dropped.
	 */
	public function test_process_uses_block_list_and_checks_segment() {
		$this->enable_api();
		update_option( 'newsman_segments', '22' );
		$segments = function () {
			return array( '5' => array( '9' => 'Block segment' ) );
		};
		add_filter( 'newsman_segments_by_list', $segments );
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );

		$processor = new SubscribeProcessor();
		$processor->process(
			$this->submit_data(
				array(
					'listId'    => '5',
					'segmentId' => '9',
				),
				array( 'nzm_email' => 'a@example.com' )
			)
		);
		$processor->process(
			$this->submit_data(
				array(
					'listId'    => '5',
					'segmentId' => '22',
				),
				array( 'nzm_email' => 'b@example.com' )
			)
		);

		remove_filter( 'newsman_segments_by_list', $segments );
		$this->assertSame( '5', $this->subscribes[0]['list'] );
		$this->assertSame( '9', $this->subscribes[0]['segment'] );
		$this->assertSame( '5', $this->subscribes[1]['list'] );
		$this->assertSame( '', $this->subscribes[1]['segment'] );
	}

	/**
	 * Double opt-in sends initSubscribe with the confirmation email form ID and the segment.
	 */
	public function test_process_double_optin_sends_form_id() {
		$this->enable_api();
		update_option( 'newsman_list', '11' );
		update_option( 'newsman_segments', '22' );
		update_option( 'newsman_newslettertype', 'init' );
		update_option( 'newsman_form_id', 'confirm-form-7' );
		$this->let_api_calls = true;
		$this->api_requests  = array();
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );

		$result = ( new SubscribeProcessor() )->process( $this->submit_data( array(), array( 'nzm_email' => 'new@example.com' ) ) );

		$this->assertSame( SubscribeProcessor::CODE_SUCCESS, $result['code'] );
		$this->assertSame( 'Thank you! Please check your inbox to confirm your subscription.', $result['message'] );
		$this->assertSame( 'double', $this->subscribes[0]['optin'] );

		$init = null;
		foreach ( $this->api_requests as $request ) {
			if ( false !== strpos( $request['url'], 'subscriber.initSubscribe' ) ) {
				$init = json_decode( $request['body'], true );
			}
		}
		$this->assertNotNull( $init, 'initSubscribe was not called.' );
		$this->assertSame( '11', (string) $init['list_id'] );
		$this->assertSame( 'new@example.com', $init['email'] );
		$this->assertSame(
			array(
				'segments' => array( 22 ),
				'form_id'  => 'confirm-form-7',
			),
			$init['options']
		);
	}

	/**
	 * A failed Newsman call is reported as a generic error.
	 */
	public function test_process_reports_api_failure() {
		$this->enable_api();
		update_option( 'newsman_newslettertype', 'save' );
		$this->let_api_calls = true;
		$this->api_fail      = true;
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );

		$result = ( new SubscribeProcessor() )->process( $this->submit_data( array(), array( 'nzm_email' => 'new@example.com' ) ) );

		$this->assertSame( SubscribeProcessor::CODE_ERROR, $result['code'] );
		$this->assertSame( 500, $result['status'] );
	}

	/**
	 * The submits are rate limited per visitor IP.
	 */
	public function test_process_rate_limit() {
		$this->enable_api();
		$limit = function () {
			return 2;
		};
		add_filter( 'newsman_subscribe_form_block_rate_limit', $limit );

		$processor = new SubscribeProcessor();
		$codes     = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$codes[] = $processor->process( $this->submit_data( array(), array( 'nzm_email' => 'r' . $i . '@example.com' ) ) )['code'];
		}

		remove_filter( 'newsman_subscribe_form_block_rate_limit', $limit );
		$this->assertSame( array( 'success', 'success', 'rate_limit' ), $codes );
		$this->assertCount( 2, $this->subscribes );
	}

	/**
	 * After a submit without JavaScript only the submitted form shows the result.
	 */
	public function test_no_js_result_is_shown_in_the_submitted_form_only() {
		$this->enable_api();
		$first  = '<!-- wp:newsman/subscribe-form /-->';
		$second = '<!-- wp:newsman/subscribe-form {"showConsent":false,"successMessage":"Welcome aboard"} /-->';
		preg_match( '/name="nzm_signature" value="([^"]+)"/', do_blocks( $second ), $signature );

		$_GET = array(
			SubscribeForm::RESULT_QUERY_VAR => 'success',
			SubscribeForm::FORM_QUERY_VAR   => substr( $signature[1], 0, 12 ),
		);
		$this->assertStringNotContainsString( 'is-success', do_blocks( $first ) );
		$this->assertStringContainsString( 'is-success" role="status" aria-live="polite">Welcome aboard', do_blocks( $second ) );
	}

	/**
	 * The embedded form block renders the same embed as the shortcode.
	 */
	public function test_embedded_form_matches_shortcode() {
		$html = do_blocks( '<!-- wp:newsman/embedded-form {"formId":"abc123"} /-->' );
		$this->assertStringContainsString( do_shortcode( '[newsman_subscribe_widget formid="abc123"]' ), $html );
		$this->assertStringContainsString( 'data-nzmform="abc123"', $html );

		// Form IDs with two dashes are the old embed format: a container the Newsman script fills.
		$html = do_blocks( '<!-- wp:newsman/embedded-form {"formId":"nzm-container-xyz"} /-->' );
		$this->assertStringContainsString( do_shortcode( '[newsman_subscribe_widget formid="nzm-container-xyz"]' ), $html );
		$this->assertStringContainsString( '<div id="nzm-container-xyz"></div>', $html );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:newsman/embedded-form /-->' ) ) );
	}

	/**
	 * Fake Newsman API: getByEmail finds nobody, every other call succeeds (or fails with api_fail).
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array
	 */
	public function fake_newsman_api( $pre, $args, $url ) {
		unset( $pre );
		$this->api_requests[] = array(
			'url'  => $url,
			'body' => isset( $args['body'] ) ? $args['body'] : '',
		);
		$response             = array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => '"12345"',
		);
		if ( $this->api_fail || false !== strpos( $url, 'subscriber.getByEmail' ) ) {
			$response['response'] = array(
				'code'    => 500,
				'message' => 'Error',
			);
			$response['body']     = '{"err":"Error","message":"Subscriber not found","code":404}';
		}
		return $response;
	}
}
