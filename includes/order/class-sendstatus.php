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

namespace Newsman\Order;

use Newsman\Config;
use Newsman\Config\Sms as SmsConfig;
use Newsman\Logger;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronize order status with Newsman
 *
 * @class \Newsman\Order\Synchronize
 */
class SendStatus {

	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * SMS config
	 *
	 * @var SmsConfig
	 */
	protected $sms_config;

	/**
	 * Newsman logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Telephone util
	 *
	 * @var Telephone
	 */
	protected $telephone;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config     = Config::init();
		$this->sms_config = SmsConfig::init();
		$this->logger     = Logger::init();
		$this->telephone  = new Telephone();
	}

	/**
	 * Send to Newsman order status:
	 * - SMS
	 * - order status for email list notification
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return void
	 */
	public function notify( $order_id, $status ) {
		$this->send_order_status( $order_id, $status );
		$this->send_sms( $order_id, $status );
	}

	/**
	 * Send order status to Newsman API
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return bool Sent successfully
	 */
	public function send_order_status( $order_id, $status ) {
		if ( ! $this->config->is_enabled_with_api() ) {
			return false;
		}

		try {
			$context = new \Newsman\Service\Context\SetPurchaseStatus();
			$context->set_list_id( $this->config->get_list_id() )
				->set_order_id( $order_id )
				->set_order_status( $status );
			$purchase = new \Newsman\Service\SetPurchaseStatus();
			$result   = $purchase->execute( $context );
			return true === $result;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
	}

	/**
	 * Send SMS for order status via Newsman API
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return bool Sent SMS successfully
	 */
	public function send_sms( $order_id, $status ) {
		if ( ! $this->sms_config->is_enabled_with_api() ) {
			return false;
		}

		$list_id    = $this->sms_config->get_list_id();
		$is_test    = $this->sms_config->is_test_mode();
		$test_phone = $this->sms_config->get_test_phone_number();

		$config_name = $this->config->get_order_config_name_by_status( $status );
		if ( false === $config_name ) {
			return false;
		}

		if ( ! $this->sms_config->is_order_sms_active_by_name( $config_name ) ) {
			return false;
		}

		$message = $this->sms_config->get_order_sms_text_by_name( $config_name );
		if ( empty( $message ) ) {
			return false;
		}

		try {
			$order   = wc_get_order( $order_id );
			$message = $this->sms_replace_placeholders( $message, $order );

			$item_data = $order->get_data();
			$phone     = $this->telephone->add_ro_prefix( $item_data['billing']['phone'] );

			if ( $is_test ) {
				$phone = $this->telephone->add_ro_prefix( $test_phone );
			}

			if ( empty( $phone ) ) {
				return false;
			}

			$context = new \Newsman\Service\Context\Sms\SendOne();
			$context->set_list_id( $list_id )
				->set_text( $message )
				->set_to( $phone );
			$send_one = new \Newsman\Service\Sms\SendOne();
			$result   = $send_one->execute( $context );

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

		$date = $order->get_date_created()->date( 'F j, Y' );

		$message = str_replace( '{{billing_first_name}}', $item_data['billing']['first_name'], $message );
		$message = str_replace( '{{billing_last_name}}', $item_data['billing']['last_name'], $message );
		$message = str_replace( '{{shipping_first_name}}', $item_data['shipping']['first_name'], $message );
		$message = str_replace( '{{shipping_last_name}}', $item_data['shipping']['last_name'], $message );
		$message = str_replace( '{{email}}', $item_data['billing']['email'], $message );
		$message = str_replace( '{{order_number}}', $item_data['id'], $message );
		$message = str_replace( '{{order_date}}', $date, $message );
		$message = str_replace( '{{order_total}}', $item_data['total'], $message );

		return trim( $message );
	}

	/**
	 * Send to Newsman order status pending.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function pending( $order_id ) {
		$this->notify( $order_id, 'pending' );
	}

	/**
	 * Send to Newsman order status failed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function failed( $order_id ) {
		$this->notify( $order_id, 'failed' );
	}

	/**
	 * Send to Newsman order status on hold.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function onhold( $order_id ) {
		$this->notify( $order_id, 'on-hold' );
	}

	/**
	 * Send to Newsman order status processing.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function processing( $order_id ) {
		$this->notify( $order_id, 'processing' );
	}

	/**
	 * Send to Newsman order status completed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function completed( $order_id ) {
		$this->notify( $order_id, 'completed' );
	}

	/**
	 * Send to Newsman order status refunded.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function refunded( $order_id ) {
		$this->notify( $order_id, 'refunded' );
	}

	/**
	 * Send to Newsman order status canceled.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function cancelled( $order_id ) {
		$this->notify( $order_id, 'cancelled' );
	}
}
