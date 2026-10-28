<?php
/**
 * Tests for the double opt-in confirmation email form ID of every subscribe path.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Form\Block\SignedSettings;
use Newsman\Form\Block\SubscribeForm;
use Newsman\Form\Block\SubscribeProcessor;
use Newsman\Subscribe\Helper as SubscribeHelper;

/**
 * Which confirmation email form ID reaches subscriber.initSubscribe: the one set on the
 * form wins; when empty, newsletter subscriptions use the Settings value and other forms none.
 */
class ConfirmationFormIdTest extends NewsmanTestCase {
	/**
	 * Confirmation email Form ID from NewsMAN > Settings.
	 */
	public const GLOBAL_FORM_ID = 'global-7';

	/**
	 * Body of the last subscriber.initSubscribe request.
	 *
	 * @var array|null
	 */
	private $init_subscribe = null;

	/**
	 * Double opt-in everywhere, a Settings Form ID and Sync segment, faked Newsman API.
	 */
	public function set_up() {
		parent::set_up();
		$this->enable_api();
		update_option( 'newsman_form_id', self::GLOBAL_FORM_ID );
		update_option( 'newsman_segments', '22' );
		update_option( 'newsman_newslettertype', 'init' );
		$this->init_subscribe = null;
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );
	}

	/**
	 * Restore the request globals.
	 */
	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Fake Newsman API: the email is not on the list, every other call succeeds.
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array
	 */
	public function fake_newsman_api( $pre, $args, $url ) {
		unset( $pre );
		$response = array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => '77',
		);
		if ( false !== strpos( $url, 'subscriber.getByEmail' ) ) {
			$response['response'] = array(
				'code'    => 500,
				'message' => 'Error',
			);
			$response['body']     = '{"err":"Error","message":"Subscriber not found","code":404}';
		}
		if ( false !== strpos( $url, 'subscriber.initSubscribe' ) ) {
			$this->init_subscribe = json_decode( $args['body'], true );
		}
		return $response;
	}

	/**
	 * Form ID sent with the last initSubscribe, '' when none.
	 *
	 * @return string
	 */
	private function sent_form_id() {
		$this->assertNotNull( $this->init_subscribe, 'initSubscribe was not called.' );
		$options = $this->init_subscribe['options'];
		return is_array( $options ) && isset( $options['form_id'] ) ? (string) $options['form_id'] : '';
	}

	/**
	 * Cases: newsletter form, Form ID set on the form, expected Form ID sent.
	 *
	 * @return array
	 */
	public function form_cases() {
		return array(
			'newsletter, empty -> Settings' => array( true, '', self::GLOBAL_FORM_ID ),
			'newsletter, own'               => array( true, 'own-1', 'own-1' ),
			'other form, empty -> none'     => array( false, '', '' ),
			'other form, own'               => array( false, 'own-1', 'own-1' ),
		);
	}

	/**
	 * The shared resolver and its filter.
	 */
	public function test_resolver() {
		$this->assertSame( self::GLOBAL_FORM_ID, SubscribeHelper::get_confirmation_form_id( '', true ) );
		$this->assertSame( 'own-1', SubscribeHelper::get_confirmation_form_id( ' own-1 ', true ) );
		$this->assertSame( '', SubscribeHelper::get_confirmation_form_id( '', false ) );
		$this->assertSame( 'own-1', SubscribeHelper::get_confirmation_form_id( 'own-1', false ) );

		$filter = function () {
			return 'filtered';
		};
		add_filter( 'newsman_subscribe_confirmation_form_id', $filter );
		$this->assertSame( 'filtered', SubscribeHelper::get_confirmation_form_id( '', false ) );
		remove_filter( 'newsman_subscribe_confirmation_form_id', $filter );
	}

	/**
	 * Gravity Forms.
	 *
	 * @dataProvider form_cases
	 * @param bool   $newsletter Newsletter form.
	 * @param string $own        Form ID set on the form.
	 * @param string $expected   Form ID expected in initSubscribe.
	 */
	public function test_gravity_forms( $newsletter, $own, $expected ) {
		$form = array(
			'id'                           => 3,
			'fields'                       => array(),
			'newsman_enable'               => '1',
			'newsman_newsletter_form'      => $newsletter ? '1' : '',
			'newsman_list_id'              => '5',
			'newsman_optin_mode'           => 'double',
			'newsman_confirmation_form_id' => $own,
			'newsman_email_field'          => '1',
			'newsman_send_fields'          => array(),
		);
		( new \Newsman\GravityForms\FormProcessor() )->process( array( '1' => 'ana@example.com' ), $form );
		$this->assertSame( $expected, $this->sent_form_id() );
	}

	/**
	 * WPForms.
	 *
	 * @dataProvider form_cases
	 * @param bool   $newsletter Newsletter form.
	 * @param string $own        Form ID set on the form.
	 * @param string $expected   Form ID expected in initSubscribe.
	 */
	public function test_wpforms( $newsletter, $own, $expected ) {
		$form_data = array(
			'id'       => 4,
			'fields'   => array(),
			'settings' => array(
				'newsman_enable'               => '1',
				'newsman_newsletter_form'      => $newsletter ? '1' : '',
				'newsman_list_id'              => '5',
				'newsman_optin_mode'           => 'double',
				'newsman_confirmation_form_id' => $own,
				'newsman_email_field'          => '1',
			),
		);
		$fields    = array(
			1 => array(
				'id'    => 1,
				'name'  => 'Email',
				'value' => 'ana@example.com',
			),
		);
		( new \Newsman\WPForms\FormProcessor() )->process( $fields, array(), $form_data, 9 );
		$this->assertSame( $expected, $this->sent_form_id() );
	}

	/**
	 * Contact Form 7.
	 *
	 * @dataProvider form_cases
	 * @param bool   $newsletter Newsletter form.
	 * @param string $own        Form ID set on the form.
	 * @param string $expected   Form ID expected in initSubscribe.
	 */
	public function test_contact_form_7( $newsletter, $own, $expected ) {
		$prop         = array(
			'enable'                 => true,
			'newsletter_form'        => $newsletter,
			'list_id'                => '5',
			'optin_mode'             => 'double',
			'confirmation_form_id'   => $own,
			'email_field'            => 'your-email',
			'send_fields'            => array(),
			'send_fields_configured' => true,
		);
		$contact_form = new class( $prop ) {
			/**
			 * Newsman property.
			 *
			 * @var array
			 */
			private $newsman;

			/**
			 * Constructor.
			 *
			 * @param array $newsman Newsman property.
			 */
			public function __construct( $newsman ) {
				$this->newsman = $newsman;
			}

			/**
			 * Form property.
			 *
			 * @param string $name Property name.
			 * @return mixed
			 */
			public function prop( $name ) {
				return 'newsman' === $name ? $this->newsman : null;
			}

			/**
			 * Form ID.
			 *
			 * @return int
			 */
			public function id() {
				return 6;
			}
		};
		$submission   = new class() {
			/**
			 * Posted value.
			 *
			 * @param string $name Field name.
			 * @return string|null
			 */
			public function get_posted_data( $name = '' ) {
				return 'your-email' === $name ? 'ana@example.com' : null;
			}
		};
		$abort        = false;
		( new \Newsman\ContactForm7\FormProcessor() )->process( $contact_form, $abort, $submission );
		$this->assertSame( $expected, $this->sent_form_id() );
	}

	/**
	 * Elementor Pro legacy Form widget.
	 *
	 * @dataProvider form_cases
	 * @param bool   $newsletter Newsletter form.
	 * @param string $own        Form ID set on the form.
	 * @param string $expected   Form ID expected in initSubscribe.
	 */
	public function test_elementor_form( $newsletter, $own, $expected ) {
		$settings = array(
			'newsman_enable'               => 'yes',
			'newsman_newsletter_form'      => $newsletter ? 'yes' : '',
			'newsman_list_id'              => '5',
			'newsman_optin_mode'           => 'double',
			'newsman_confirmation_form_id' => $own,
			'form_fields'                  => array(
				array(
					'custom_id'        => 'email',
					'newsman_is_email' => 'yes',
				),
			),
		);
		$record   = new class( $settings ) {
			/**
			 * Record data.
			 *
			 * @var array
			 */
			private $data;

			/**
			 * Constructor.
			 *
			 * @param array $settings Form settings.
			 */
			public function __construct( $settings ) {
				$this->data = array(
					'form_settings' => $settings,
					'fields'        => array( 'email' => array( 'value' => 'ana@example.com' ) ),
				);
			}

			/**
			 * Record value.
			 *
			 * @param string $key Key.
			 * @return mixed
			 */
			public function get( $key ) {
				return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
			}
		};
		( new \Newsman\Elementor\FormProcessor() )->process( $record, null );
		$this->assertSame( $expected, $this->sent_form_id() );
	}

	/**
	 * Subscribe form block: always a newsletter subscription, the block field overrides Settings.
	 */
	public function test_block() {
		foreach ( array(
			''      => self::GLOBAL_FORM_ID,
			'own-1' => 'own-1',
		) as $own => $expected ) {
			$signed = SignedSettings::encode( ( new SubscribeForm() )->get_settings( array( 'confirmationFormId' => (string) $own ) ) );
			$result = ( new SubscribeProcessor() )->process(
				array(
					'nzm_settings'  => $signed['payload'],
					'nzm_signature' => $signed['signature'],
					'nzm_email'     => 'ana@example.com',
				)
			);
			$this->assertSame( SubscribeProcessor::CODE_SUCCESS, $result['code'] );
			$this->assertSame( $expected, $this->sent_form_id() );
		}
	}

	/**
	 * My Account newsletter box sends the Settings Form ID and the Sync segment, like the checkout.
	 */
	public function test_my_account() {
		$this->require_woocommerce();
		update_option( 'newsman_myaccount_newsletter', 'on' );
		// Subscribe right away instead of queuing it in Action Scheduler.
		update_option( 'newsman_developer_use_as_subscribe', '' );
		$user_id = $this->create_user( 'customer', 'ana@example.com', 'Ana', 'Pop' );
		wp_set_current_user( $user_id );
		$original_session = WC()->session;
		WC()->session     = new class() extends WC_Session {};
		$stop             = function () {
			// Stops the handler before its exit; the final redirect is outside its try/catch.
			throw new \RuntimeException( 'newsman-test-redirect' );
		};
		add_filter( 'wp_redirect', $stop );

		$_POST = array(
			'newsman_newsletter_submit'    => '1',
			'newsman_nonce'                => wp_create_nonce( 'newsman_subscribe_newsletter' ),
			'nzmAccountNewsletter'         => '1',
			'nzmAccountNewsletterPrevious' => '0',
		);
		try {
			( new \Newsman\Form\Account\Processor() )->process();
			$this->fail( 'The handler did not redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'newsman-test-redirect', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $stop );
			WC()->session = $original_session;
		}

		$this->assertSame( self::GLOBAL_FORM_ID, $this->sent_form_id() );
		$this->assertSame( array( 22 ), array_map( 'intval', $this->init_subscribe['options']['segments'] ) );
	}
}
