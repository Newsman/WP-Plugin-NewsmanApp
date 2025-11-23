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

namespace Newsman\Scheduler\Order\Awb\Cargus;

use Newsman\Config\Sms as SmsConfig;
use Newsman\Scheduler\AbstractScheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send SMS for order with Cargus AWB.
 *
 * @class \Newsman\Scheduler\Order\Awb\Cargus\SendSms
 */
class SendSms extends AbstractScheduler {

	/**
	 * Background event hook send order SMS with Cargus AWB
	 */
	public const BACKGROUND_HOOK_EVENT = 'newsman_order_sms_cargus_awb';

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

		if ( ! $this->config->is_cargus_plugin_active() ) {
			return;
		}

		if ( ! ( $this->sms_config->is_sms_send_cargus_awb() && ! empty( $this->sms_config->get_sms_cargus_awb_message() ) ) ) {
			return;
		}

		if ( ! $this->action_scheduler->is_allowed_single() ) {
			return;
		}

		add_action( self::BACKGROUND_HOOK_EVENT, array( $this, 'send_sms' ), 10, 3 );
	}

	/**
	 * Notify SMS via Newsman with order Cargus AWB
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function notify( $order_id ) {
		$awb = get_post_meta( $order_id, '_cargus_awb', true );
		if ( empty( $awb ) ) {
			return;
		}

		if ( ! $this->action_scheduler->is_allowed_single() ) {
			$this->send_sms( $order_id, $awb );
			return;
		}

		as_schedule_single_action(
			time(),
			self::BACKGROUND_HOOK_EVENT,
			array(
				$order_id,
				$awb,
				true,
			),
			$this->action_scheduler->get_group_order_sms_awb()
		);
	}

	/**
	 * Send SMS for order with Cargus AWB via Newsman API
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $awb          Order Cargus AWB.
	 * @param string $is_scheduled Is action scheduled.
	 * @return bool Sent SMS successfully
	 * @throws \Exception On API errors or other.
	 */
	public function send_sms( $order_id, $awb, $is_scheduled = false ) {
		if ( ! $this->sms_config->is_enabled_with_api() ) {
			return false;
		}

		if ( ! $this->config->is_cargus_plugin_active() ) {
			return false;
		}

		if ( ! ( $this->sms_config->is_sms_send_cargus_awb() && ! empty( $this->sms_config->get_sms_cargus_awb_message() ) ) ) {
			return false;
		}

		if ( empty( $awb ) ) {
			return false;
		}

		$list_id    = $this->sms_config->get_list_id();
		$is_test    = $this->sms_config->is_test_mode();
		$test_phone = $this->sms_config->get_test_phone_number();
		$message    = $this->sms_config->get_sms_cargus_awb_message();

		if ( empty( $message ) ) {
			return false;
		}

		try {
			$order = wc_get_order( $order_id );

			do_action( 'newsman_order_sms_awb_cargus_send_sms_before', $order, $awb, $is_scheduled );

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
			$context = apply_filters( 'newsman_order_sms_awb_cargus_send_sms_context', $context, $order, $awb, $is_scheduled );

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

			do_action( 'newsman_order_sms_awb_cargus_send_sms_after', $order, $awb, $is_scheduled );

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
