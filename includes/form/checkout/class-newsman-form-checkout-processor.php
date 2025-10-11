<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman remarketing class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add checkbox subscribe to newsletter in checkout
 *
 * @class Newsman_Form_Checkout_Processor
 */
class Newsman_Form_Checkout_Processor {
	/**
	 * Config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * Config SMS
	 *
	 * @var Newsman_Config_Sms
	 */
	protected $sms_config;

	/**
	 * User IP address
	 *
	 * @var Newsman_User_IpAddress
	 */
	protected $user_ip;

	/**
	 * Logger
	 *
	 * @var Newsman_WC_Logger
	 */
	protected $logger;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config     = Newsman_Config::init();
		$this->sms_config = Newsman_Config_Sms::init();
		$this->user_ip    = Newsman_User_IpAddress::init();
		$this->logger     = Newsman_WC_Logger::init();
	}

	/**
	 * Process checkout action.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function process( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! ( ! empty( $_POST['newsmanCheckoutNewsletter'] ) && 1 === (int) $_POST['newsmanCheckoutNewsletter'] ) ) {
			return;
		}

		$order      = wc_get_order( $order_id );
		$order_data = $order->get_data();

		$properties = array();

		try {
			$metadata = $order->get_meta_data();

			foreach ( $metadata as $_metadata ) {
				if ( '_billing_functia' === $_metadata->key || 'billing_functia' === $_metadata->key ) {
					$properties['functia'] = $_metadata->value;
				}
				if ( '_billing_sex' === $_metadata->key || 'billing_sex' === $_metadata->key ) {
					$properties['sex'] = $_metadata->value;
				}
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( Exception $e ) {
			// Custom fields not found.
		}

		$email     = $order_data['billing']['email'];
		$firstname = $order_data['billing']['first_name'];
		$lastname  = $order_data['billing']['last_name'];
		$telephone = ( ! empty( $order_data['billing']['phone'] ) ) ? $order_data['billing']['phone'] : '';

		$properties['phone'] = $telephone;

		$options    = array();
		$segment_id = $this->config->get_segment_id();
		if ( ! empty( $segment_id ) ) {
			$options['segments'] = array( $segment_id );
		}

		$form_id = $this->config->get_newsman_form_id();
		if ( ! empty( $form_id ) ) {
			$options['form_id'] = $form_id;
		}

		try {
			$this->subscribe_email( $email, $firstname, $lastname, $properties, $options );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e->getMessage() );
		}

		$this->sms_subscribe( $telephone, $firstname, $lastname, $properties );
	}

	/**
	 * Subscribe email to list
	 *
	 * @param string $email Email address.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array.
	 * @param array  $options Options array, additional fields.
	 * @return void
	 * @throws Exception Error exceptions or other.
	 */
	public function subscribe_email( $email, $firstname, $lastname, $properties = array(), $options = array() ) {
		if ( empty( $email ) ) {
			return;
		}
		if ( ! $this->config->is_checkout_newsletter() ) {
			return;
		}
		if ( ! $this->config->is_enabled_with_api() ) {
			return;
		}

		if ( $this->config->is_checkout_newsletter_double_optin() ) {
			$context = new Newsman_Service_Context_InitSubscribeEmail();
			$context->set_list_id( $this->config->get_list_id() )
				->set_email( $email )
				->set_firstname( $firstname )
				->set_lastname( $lastname )
				->set_ip( $this->user_ip->get_ip() )
				->set_properties( $properties )
				->set_options( $options );

			$init_subscribe = new Newsman_Service_InitSubscribeEmail();
			$init_subscribe->execute( $context );
		} else {
			$context = new Newsman_Service_Context_SubscribeEmail();
			$context->set_list_id( $this->config->get_list_id() )
				->set_email( $email )
				->set_firstname( $firstname )
				->set_lastname( $lastname )
				->set_ip( $this->user_ip->get_ip() )
				->set_properties( $properties );

			$subscribe     = new Newsman_Service_SubscribeEmail();
			$subscriber_id = $subscribe->execute( $context );

			if ( ! empty( $this->config->get_segment_id() ) ) {
				$context = new Newsman_Service_Context_Segment_AddSubscriber();
				$context->set_segment_id( $this->config->get_segment_id() )
					->set_subscriber_id( $subscriber_id );
			}
		}
	}

	/**
	 * Subscribe telephone number to SMS list
	 *
	 * @param string $telephone Telephone number.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array, additional fields.
	 * @return void
	 * @throws Exception Error exceptions or other.
	 */
	public function sms_subscribe( $telephone, $firstname, $lastname, $properties = array() ) {
		if ( ! $this->config->is_checkout_sms() ) {
			return;
		}

		if ( ! $this->sms_config->is_enabled_with_api() ) {
			return;
		}

		if ( empty( $telephone ) ) {
			return;
		}

		$context = new Newsman_Service_Context_Sms_Subscribe();
		$context->set_list_id( $this->sms_config->get_list_id() )
			->set_telephone( $telephone )
			->set_firstname( $firstname )
			->set_lastname( $lastname )
			->set_ip( $this->user_ip->get_ip() )
			->set_properties( $properties );

		try {
			$subscribe = new Newsman_Service_Sms_Subscribe();
			$subscribe->execute( $context );
		} catch ( Exception $e ) {
			$this->logger->log_exception( $e );
		}
	}
}
