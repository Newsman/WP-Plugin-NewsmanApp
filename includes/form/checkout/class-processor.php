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

namespace Newsman\Form\Checkout;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add checkbox subscribe to newsletter in checkout
 *
 * @class \Newsman\Form\Checkout\Processor
 */
class Processor {
	/**
	 * Background event hook subscribe email to list
	 *
	 * @deprecated since 3.1.0
	 */
	public const SUBSCRIBE_EMAIL = 'newsman_subscribe_email';

	/**
	 * Background event hook subscribe telephone to SMS list
	 *
	 * @deprecated since 3.1.0
	 */
	public const SUBSCRIBE_PHONE = 'newsman_subscribe_phone';

	/**
	 * Wait in micro seconds before retry subscribe email
	 *
	 * @deprecated since 3.1.0
	 */
	public const WAIT_RETRY_TIMEOUT_SUBSCRIBE_EMAIL = 5000000;

	/**
	 * Wait in micro seconds before retry subscribe telephone number to SMS list
	 *
	 * @deprecated since 3.1.0
	 */
	public const WAIT_RETRY_TIMEOUT_SUBSCRIBE_PHONE = 5000000;

	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Remarketing Config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Telephone
	 *
	 * @var Telephone
	 */
	protected $telephone;

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
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->telephone          = new Telephone();
		$this->logger             = Logger::init();
	}

	/**
	 * Class constructor
	 */
	public function init() {
		if ( $this->is_hook_enabled() ) {
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'process' ), 10, 2 );
		}
	}

	/**
	 * Is hook enabled
	 *
	 * @return bool
	 */
	public function is_hook_enabled() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$is_checkout_newsletter = ( ! empty( $_POST['newsmanCheckoutNewsletter'] ) && ( 1 === (int) sanitize_text_field( wp_unslash( $_POST['newsmanCheckoutNewsletter'] ) ) ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$is_checkout_send_order_status = ( ! empty( $_POST['nzm_send_order_status'] ) && ( 1 === (int) sanitize_text_field( wp_unslash( $_POST['nzm_send_order_status'] ) ) ) );
		// Is checkout page.
		$is_checkout = function_exists( '\is_checkout' ) && is_checkout() && ! is_wc_endpoint_url();

		if ( ! $is_checkout_newsletter && ! $is_checkout_send_order_status && ! $is_checkout ) {
			return false;
		}
		if ( ! $this->config->is_enabled_with_api() ) {
			return false;
		}
		if ( ! $this->config->is_checkout_newsletter() && ! $this->config->is_checkout_sms() ) {
			return false;
		}
		return true;
	}

	/**
	 * Process checkout action.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 * @throws \Exception Exceptions.
	 */
	public function process( $order_id ) {
		$order                 = wc_get_order( $order_id );
		$order_data            = $order->get_data();
		$is_subscribe_sms_list = ( '1' === (string) ( (int) $order->get_meta( '_nzm_send_order_status' ) ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$is_subscribe_newsletter = ( ! empty( $_POST['newsmanCheckoutNewsletter'] ) && ( 1 === (int) sanitize_text_field( wp_unslash( $_POST['newsmanCheckoutNewsletter'] ) ) ) );

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
		} catch ( \Exception $e ) {
			// Custom fields not found.
		}

		foreach ( $this->remarketing_config->get_customer_attributes() as $attribute ) {
			if ( strpos( $attribute, 'billing_' ) === 0 || strpos( $attribute, 'shipping_' ) === 0 ) {
				$getter = 'get_' . $attribute;
				if ( method_exists( $order, $getter ) ) {
					$properties[ $attribute ] = $order->$getter();
				}
			}
		}

		$email     = $order_data['billing']['email'];
		$firstname = $order_data['billing']['first_name'];
		$lastname  = $order_data['billing']['last_name'];
		$telephone = ( ! empty( $order_data['billing']['phone'] ) ) ? $order_data['billing']['phone'] : '';
		$telephone = $this->telephone->clean( $telephone );

		$email_properties = $properties;
		if ( $this->remarketing_config->is_send_telephone() ) {
			if ( ! empty( $telephone ) ) {
				$email_properties['tel']               = $telephone;
				$email_properties['phone']             = $telephone;
				$email_properties['telephone']         = $telephone;
				$email_properties['billing_telephone'] = $telephone;
			}

			if ( ! empty( $order_data['shipping']['phone'] ) ) {
				$shipping_telephone = $order_data['shipping']['phone'];
				$shipping_telephone = $this->telephone->clean( $shipping_telephone );
				if ( ! empty( $shipping_telephone ) ) {
					$email_properties['shipping_telephone'] = $shipping_telephone;
				}
			}
		}

		$options    = array();
		$segment_id = $this->config->get_segment_id();
		if ( ! empty( $segment_id ) ) {
			$options['segments'] = array( $segment_id );
		}

		$form_id = $this->config->get_newsman_form_id();
		if ( ! empty( $form_id ) ) {
			$options['form_id'] = $form_id;
		}

		$filter           = apply_filters(
			'newsman_checkout_newsletter_process_params',
			array(
				'email'            => $email,
				'firstname'        => $firstname,
				'lastname'         => $lastname,
				'telephone'        => $telephone,
				'properties'       => $properties,
				'options'          => $options,
				'email_properties' => $email_properties,
			),
			array(
				'order_id' => $order_id,
				'order'    => $order,
			)
		);
		$email            = isset( $filter['email'] ) ? $filter['email'] : $email;
		$firstname        = isset( $filter['firstname'] ) ? $filter['firstname'] : $firstname;
		$lastname         = isset( $filter['lastname'] ) ? $filter['lastname'] : $lastname;
		$telephone        = isset( $filter['telephone'] ) ? $filter['telephone'] : $telephone;
		$properties       = isset( $filter['properties'] ) ? $filter['properties'] : $properties;
		$options          = isset( $filter['options'] ) ? $filter['options'] : $options;
		$email_properties = isset( $filter['email_properties'] ) ? $filter['email_properties'] : $email_properties;

		if ( $this->config->is_checkout_newsletter() && $is_subscribe_newsletter ) {
			try {
				$scheduler = new \Newsman\Scheduler\Subscribe\Email();
				$scheduler->execute( $email, $firstname, $lastname, $email_properties, $options );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		if ( $this->config->is_checkout_sms() || $is_subscribe_sms_list ) {
			$scheduler = new \Newsman\Scheduler\Subscribe\Phone();
			$scheduler->execute( $telephone, $firstname, $lastname, $properties );
		}
	}

	/**
	 * Subscribe email to list
	 *
	 * @param string $email Email address.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array.
	 * @param array  $options Options array, additional fields.
	 * @param array  $is_scheduled Is action scheduled.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 * @deprecated
	 */
	public function subscribe_email( $email, $firstname, $lastname, $properties = array(), $options = array(), $is_scheduled = false ) {
		$scheduler = new \Newsman\Scheduler\Subscribe\Email();
		$scheduler->subscribe( $email, $firstname, $lastname, $properties, $options, $is_scheduled );
	}

	/**
	 * Subscribe telephone number to SMS list
	 *
	 * @param string $telephone Telephone number.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array, additional fields.
	 * @param array  $is_scheduled Is action scheduled.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function subscribe_phone( $telephone, $firstname, $lastname, $properties = array(), $is_scheduled = false ) {
		$scheduler = new \Newsman\Scheduler\Subscribe\Phone();
		$scheduler->subscribe( $telephone, $firstname, $lastname, $properties, $is_scheduled );
	}
}
