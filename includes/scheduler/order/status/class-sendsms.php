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

namespace Newsman\Scheduler\Order\Status;

use Newsman\Config\Sms as SmsConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send SMS about order status.
 *
 * @class \Newsman\Scheduler\Order\Status\SendSms
 */
class SendSms extends AbstractStatus {
	/**
	 * Background event hook notify order SMS
	 */
	public const BACKGROUND_HOOK_EVENT = 'newsman_order_notify_sms';

	/**
	 * Wait in micro seconds before retry notify order SMS
	 */
	public const WAIT_RETRY_TIMEOUT = 5000000;

	/**
	 * SMS config
	 *
	 * @var SmsConfig
	 */
	protected $sms_config;

	/**
	 * Class constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->sms_config = SmsConfig::init();
	}

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! ( $this->config->is_enabled_with_api() && $this->sms_config->is_enabled_with_api() ) ) {
			return;
		}

		foreach ( $this->config->get_order_status_to_name() as $status => $name ) {
			if ( ! $this->sms_config->is_valid_sms_by_order_status( $status ) ) {
				continue;
			}
			if ( method_exists( $this, $name ) ) {
				add_action( 'woocommerce_order_status_' . $status, array( $this, $name ) );
			}
		}

		if ( ! $this->action_scheduler->is_allowed_single() ) {
			return;
		}

		add_action( self::BACKGROUND_HOOK_EVENT, array( $this, 'send_sms' ), 10, 3 );
	}

	/**
	 * Notify SMS via Newsman about order status
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return void
	 */
	public function notify( $order_id, $status ) {
		if ( ! $this->action_scheduler->is_allowed_single() ) {
			$this->send_sms( $order_id, $status );
			return;
		}

		$config_name = $this->config->get_order_config_name_by_status( $status );
		// Create action if is enabled.
		if ( false !== $config_name && $this->sms_config->is_order_sms_active_by_name( $config_name ) ) {
			as_schedule_single_action(
				time(),
				self::BACKGROUND_HOOK_EVENT,
				array(
					$order_id,
					$status,
					true,
				),
				$this->action_scheduler->get_group_order_change()
			);
		}
	}

	/**
	 * Send SMS for order status via Newsman API
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $status       Order status.
	 * @param string $is_scheduled Is action scheduled.
	 * @return bool Sent SMS successfully
	 * @throws \Exception On API errors or other.
	 */
	public function send_sms( $order_id, $status, $is_scheduled = false ) {
		if ( ! $this->sms_config->is_enabled_with_api() ) {
			return false;
		}

		if ( ! $this->sms_config->is_valid_sms_by_order_status( $status ) ) {
			return false;
		}

		$list_id     = $this->sms_config->get_list_id();
		$is_test     = $this->sms_config->is_test_mode();
		$test_phone  = $this->sms_config->get_test_phone_number();
		$config_name = $this->config->get_order_config_name_by_status( $status );
		$message     = $this->sms_config->get_order_sms_text_by_name( $config_name );

		if ( empty( $message ) ) {
			return false;
		}

		try {
			$order = wc_get_order( $order_id );

			do_action( 'newsman_order_status_send_sms_before', $order, $status, $is_scheduled );

			$message   = $this->sms_replace_placeholders( $message, $order );
			$item_data = $order->get_data();
			$phone     = $this->telephone->clean( $item_data['billing']['phone'] );
			$phone     = $this->telephone->add_ro_prefix( $phone );

			if ( $is_test ) {
				$phone = $this->telephone->clean( $test_phone );
				$phone = $this->telephone->add_ro_prefix( $phone );
			}

			if ( empty( $phone ) ) {
				return false;
			}

			$context = new \Newsman\Service\Context\Sms\SendOne();
			$context->set_list_id( $list_id )
				->set_text( $message )
				->set_to( $phone );
			$context = apply_filters( 'newsman_order_status_send_sms_context', $context, $order, $status, $is_scheduled );

			try {
				$send_one = new \Newsman\Service\Sms\SendOne();
				$result   = $send_one->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT );

					$send_one = new \Newsman\Service\Sms\SendOne();
					$result   = $send_one->execute( $context );
				} else {
					throw $e;
				}
			}

			do_action( 'newsman_order_status_send_sms_after', $order, $status, $is_scheduled );

			return ! empty( $result );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$this->logger->log_exception( $e );

			return false;
		}
	}

	/**
	 * Replace message placeholders
	 *
	 * @param string    $message SMS message.
	 * @param \WC_Order $order Order instance.
	 * @return string
	 */
	public function sms_replace_placeholders( $message, $order ) {
		$item_data = $order->get_data();
		$date      = $order->get_date_created()->date( 'F j, Y' );

		$message = apply_filters( 'newsman_order_status_send_sms_message_before', $message, $order );

		$message = str_replace( '{{billing_first_name}}', $item_data['billing']['first_name'], $message );
		$message = str_replace( '{{billing_last_name}}', $item_data['billing']['last_name'], $message );
		$message = str_replace( '{{shipping_first_name}}', $item_data['shipping']['first_name'], $message );
		$message = str_replace( '{{shipping_last_name}}', $item_data['shipping']['last_name'], $message );
		$message = str_replace( '{{email}}', $item_data['billing']['email'], $message );
		$message = str_replace( '{{order_number}}', $item_data['id'], $message );
		$message = str_replace( '{{order_date}}', $date, $message );
		$message = str_replace( '{{order_total}}', $item_data['total'], $message );

		$message = trim( $message );

		return apply_filters( 'newsman_order_status_send_sms_message_after', $message, $order );
	}
}
