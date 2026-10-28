<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman subscribe form block submit handler.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Form\Block;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Subscribe\Helper as SubscribeHelper;
use Newsman\Subscribe\Segments;
use Newsman\User\IpAddress;
use Newsman\User\RemoteIpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the subscribe form block submit: admin-ajax.php from the block view
 * script, admin-post.php when JavaScript is not available.
 *
 * There is no nonce, as cached pages would serve expired ones. The form settings
 * are signed (SignedSettings), bots are filtered with a honeypot field and the
 * submits are rate limited per visitor IP.
 *
 * @class \Newsman\Form\Block\SubscribeProcessor
 */
class SubscribeProcessor {
	/**
	 * Request action name.
	 */
	public const ACTION = 'newsman_subscribe_form';

	/**
	 * Rate limit transient prefix.
	 */
	public const RATE_LIMIT_TRANSIENT_PREFIX = 'newsman_subscribe_form_rl_';

	/**
	 * Result codes.
	 */
	public const CODE_SUCCESS       = 'success';
	public const CODE_INVALID_EMAIL = 'invalid_email';
	public const CODE_INVALID_PHONE = 'invalid_phone';
	public const CODE_REQUIRED      = 'required';
	public const CODE_CONSENT       = 'consent';
	public const CODE_RATE_LIMIT    = 'rate_limit';
	public const CODE_ERROR         = 'error';

	/**
	 * Maximum length of a name or phone value.
	 */
	public const MAX_FIELD_LENGTH = 100;

	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Config::init();
		$this->logger = Logger::init();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_post' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_post' ) );
	}

	/**
	 * Submit from the block view script. Responds with JSON.
	 *
	 * @return void
	 */
	public function handle_ajax() {
		$result = $this->process( $this->get_request_data() );

		if ( self::CODE_SUCCESS === $result['code'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}

		wp_send_json_error(
			array(
				'code'    => $result['code'],
				'message' => $result['message'],
			),
			$result['status']
		);
	}

	/**
	 * Submit without JavaScript. Redirects back to the page with the result code.
	 *
	 * @return void
	 */
	public function handle_post() {
		$data   = $this->get_request_data();
		$result = $this->process( $data );

		$return = isset( $data['nzm_return'] ) && is_string( $data['nzm_return'] ) ? esc_url_raw( $data['nzm_return'] ) : '';
		$return = wp_validate_redirect( $return, home_url( '/' ) );
		$key    = isset( $data['nzm_signature'] ) && is_string( $data['nzm_signature'] ) ? substr( sanitize_key( $data['nzm_signature'] ), 0, 12 ) : '';

		$url = add_query_arg(
			array(
				SubscribeForm::RESULT_QUERY_VAR => $result['code'],
				SubscribeForm::FORM_QUERY_VAR   => $key,
			),
			$return
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Unslashed POST data.
	 *
	 * @return array
	 */
	protected function get_request_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public form on cacheable pages; signed settings, honeypot and rate limit instead of a nonce.
		return wp_unslash( $_POST );
	}

	/**
	 * Validate the submit and subscribe the email.
	 *
	 * @param array $data Unslashed request data.
	 * @return array `[ 'code' => string, 'message' => string, 'status' => int ]`.
	 */
	public function process( $data ) {
		$double_optin = $this->config->is_newsletter_double_optin();

		$settings = SignedSettings::decode(
			isset( $data['nzm_settings'] ) ? $data['nzm_settings'] : null,
			isset( $data['nzm_signature'] ) ? $data['nzm_signature'] : null
		);
		if ( null === $settings ) {
			$this->logger->warning( 'Newsman subscribe form: the form settings signature is not valid.' );
			return $this->result( self::CODE_ERROR, 400 );
		}

		// Bots fill the hidden field. Pretend success so they do not retry.
		if ( ! empty( $data['nzm_website'] ) ) {
			$this->logger->info( 'Newsman subscribe form: honeypot field filled, submit ignored.' );
			return $this->result( self::CODE_SUCCESS, 200, $double_optin );
		}

		$email = isset( $data['nzm_email'] ) && is_string( $data['nzm_email'] ) ? sanitize_email( $data['nzm_email'] ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			return $this->result( self::CODE_INVALID_EMAIL, 400 );
		}

		$fields = isset( $settings['fields'] ) && is_array( $settings['fields'] ) ? $settings['fields'] : array();
		$values = array();
		foreach ( array( 'firstname', 'lastname', 'phone' ) as $field ) {
			$mode             = isset( $fields[ $field ] ) ? (int) $fields[ $field ] : SubscribeForm::FIELD_HIDDEN;
			$values[ $field ] = '';
			if ( SubscribeForm::FIELD_HIDDEN === $mode ) {
				continue;
			}
			$value = isset( $data[ 'nzm_' . $field ] ) && is_string( $data[ 'nzm_' . $field ] ) ? sanitize_text_field( $data[ 'nzm_' . $field ] ) : '';
			$value = trim( $this->truncate( $value, self::MAX_FIELD_LENGTH ) );
			if ( '' === $value && SubscribeForm::FIELD_REQUIRED === $mode ) {
				return $this->result( self::CODE_REQUIRED, 400 );
			}
			$values[ $field ] = $value;
		}

		if ( '' !== $values['phone'] && ! preg_match( '/^\+?[0-9 ().\-]{6,20}$/', $values['phone'] ) ) {
			return $this->result( self::CODE_INVALID_PHONE, 400 );
		}

		if ( ! empty( $settings['consent'] ) && ( ! isset( $data['nzm_consent'] ) || '1' !== $data['nzm_consent'] ) ) {
			return $this->result( self::CODE_CONSENT, 400 );
		}

		if ( ! $this->config->is_enabled_with_api() ) {
			$this->logger->error( 'Newsman subscribe form: the plugin is not connected to Newsman.' );
			return $this->result( self::CODE_ERROR, 503 );
		}

		$blog_id = get_current_blog_id();
		$list_id = isset( $settings['list'] ) ? trim( (string) $settings['list'] ) : '';
		if ( '' === $list_id ) {
			$list_id    = trim( (string) $this->config->get_list_id( $blog_id ) );
			$segment_id = trim( (string) $this->config->get_segment_id( $blog_id ) );
		} else {
			$segment_id = isset( $settings['segment'] ) ? trim( (string) $settings['segment'] ) : '';
			if ( '' !== $segment_id && ! Segments::belongs_to_list( $blog_id, $list_id, $segment_id ) ) {
				$segment_id = '';
			}
		}
		if ( '' === $list_id ) {
			$this->logger->error( 'Newsman subscribe form: no list is selected in the block or in Newsman - Sync.' );
			return $this->result( self::CODE_ERROR, 503 );
		}

		if ( $this->is_rate_limited() ) {
			$this->logger->notice( 'Newsman subscribe form: rate limit reached for ' . $email . '.' );
			return $this->result( self::CODE_RATE_LIMIT, 429 );
		}

		$properties = array();
		if ( '' !== $values['phone'] ) {
			$properties['phone'] = $values['phone'];
		}

		/**
		 * Filter the subscriber properties sent from the subscribe form block.
		 *
		 * @param array  $properties Properties.
		 * @param string $email      Subscriber email.
		 * @param array  $values     Submitted firstname, lastname and phone.
		 * @param array  $settings   Signed block settings (list, segment, fields, consent).
		 */
		$properties = apply_filters( 'newsman_subscribe_form_block_properties', $properties, $email, $values, $settings );

		$optin_mode = $double_optin ? 'double' : 'single';
		$form_id    = isset( $settings['form_id'] ) ? (string) $settings['form_id'] : '';
		$form_id    = $double_optin ? SubscribeHelper::get_confirmation_form_id( $form_id, true, $blog_id ) : '';

		try {
			SubscribeHelper::subscribe_with_props(
				$blog_id,
				$list_id,
				$email,
				$properties,
				IpAddress::init()->get_ip(),
				$optin_mode,
				$values['firstname'],
				$values['lastname'],
				$segment_id,
				$form_id,
				true
			);

			/**
			 * Fires after a subscribe form block submit was sent to Newsman.
			 *
			 * @param string $list_id    Newsman list ID.
			 * @param string $email      Subscriber email.
			 * @param array  $properties Subscriber properties.
			 * @param array  $values     Submitted firstname, lastname and phone.
			 */
			do_action( 'newsman_subscribe_form_block_processed', $list_id, $email, $properties, $values );
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );

			/**
			 * Fires when sending a subscribe form block submit to Newsman failed.
			 *
			 * @param \Exception $e       Exception.
			 * @param string     $list_id Newsman list ID.
			 * @param string     $email   Subscriber email.
			 */
			do_action( 'newsman_subscribe_form_block_process_failed', $e, $list_id, $email );

			return $this->result( self::CODE_ERROR, 500 );
		}

		return $this->result( self::CODE_SUCCESS, 200, $double_optin );
	}

	/**
	 * Visible message of a result code.
	 *
	 * @param string $code         Result code.
	 * @param bool   $double_optin Whether the subscription is double opt-in (success message only).
	 * @return string
	 */
	public static function get_message( $code, $double_optin = false ) {
		switch ( $code ) {
			case self::CODE_SUCCESS:
				return $double_optin
					? __( 'Thank you! Please check your inbox to confirm your subscription.', 'newsman' )
					: __( 'Thank you for subscribing!', 'newsman' );
			case self::CODE_INVALID_EMAIL:
				return __( 'Please enter a valid email address.', 'newsman' );
			case self::CODE_INVALID_PHONE:
				return __( 'Please enter a valid phone number.', 'newsman' );
			case self::CODE_REQUIRED:
				return __( 'Please fill in all required fields.', 'newsman' );
			case self::CODE_CONSENT:
				return __( 'Please accept the terms to subscribe.', 'newsman' );
			case self::CODE_RATE_LIMIT:
				return __( 'Too many attempts. Please try again later.', 'newsman' );
			default:
				return __( 'The subscription could not be completed. Please try again later.', 'newsman' );
		}
	}

	/**
	 * Count a submit for the visitor IP and tell whether the limit is reached.
	 *
	 * @return bool
	 */
	protected function is_rate_limited() {
		$ip = (string) RemoteIpAddress::init()->get_ip();

		/**
		 * Filter the maximum number of subscribe form block submits per visitor IP
		 * within the rate limit window. Zero disables the limit.
		 *
		 * @param int    $limit Default 5.
		 * @param string $ip    Visitor IP.
		 */
		$limit = (int) apply_filters( 'newsman_subscribe_form_block_rate_limit', 5, $ip );

		/**
		 * Filter the subscribe form block rate limit window in seconds.
		 *
		 * @param int    $window Default 10 minutes.
		 * @param string $ip     Visitor IP.
		 */
		$window = (int) apply_filters( 'newsman_subscribe_form_block_rate_limit_window', 10 * MINUTE_IN_SECONDS, $ip );

		if ( $limit <= 0 || $window <= 0 || '' === $ip ) {
			return false;
		}

		$key   = self::RATE_LIMIT_TRANSIENT_PREFIX . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, $window );

		return false;
	}

	/**
	 * Build a result.
	 *
	 * @param string $code         Result code.
	 * @param int    $status       HTTP status.
	 * @param bool   $double_optin Whether the subscription is double opt-in.
	 * @return array
	 */
	protected function result( $code, $status, $double_optin = false ) {
		return array(
			'code'    => $code,
			'message' => self::get_message( $code, $double_optin ),
			'status'  => (int) $status,
		);
	}

	/**
	 * Truncate a string to a number of characters.
	 *
	 * @param string $value  Value.
	 * @param int    $length Maximum length.
	 * @return string
	 */
	protected function truncate( $value, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}
		return substr( $value, 0, $length );
	}
}
