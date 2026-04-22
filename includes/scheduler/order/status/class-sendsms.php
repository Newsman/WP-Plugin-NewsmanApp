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

		if ( ! $this->config->is_checkout_order_status() ) {
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

		if ( ! $this->config->is_checkout_order_status() ) {
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

			$is_send_order_status = ( '1' === (string) ( (int) $order->get_meta( '_nzm_send_order_status' ) ) );
			if ( ! $is_send_order_status ) {
				return true;
			}

			do_action( 'newsman_order_status_send_sms_before', $order, $status, $is_scheduled );

			$message_order_processor = new \Newsman\Util\Sms\Message\OrderProcessor();
			$message                 = $message_order_processor->process( $order, $message );
			$item_data               = $order->get_data();
			$phone                   = $this->telephone->clean( $item_data['billing']['phone'] );
			$phone                   = $this->telephone->add_ro_prefix( $phone );

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
					$this->logger->notice( 'Wait ' . number_format( self::WAIT_RETRY_TIMEOUT / 1000000, 2 ) . ' seconds before retry' );
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
	 * Get hooks events
	 *
	 * @return string[]
	 */
	public function get_hooks_events() {
		return array(
			self::BACKGROUND_HOOK_EVENT,
		);
	}
}
