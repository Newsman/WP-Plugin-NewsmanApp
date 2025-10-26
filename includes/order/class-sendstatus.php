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
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Util\ActionScheduler as NewsmanActionScheduler;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * - Synchronize order status by email list (remarketing).
 * - Send SMS about order status.
 *
 * @class \Newsman\Order\Synchronize
 */
class SendStatus {
	/**
	 * Background event hook notify order status
	 */
	public const ORDER_NOTIFY_STATUS = 'newsman_order_notify_status';

	/**
	 * Background event hook notify order SMS
	 */
	public const ORDER_NOTIFY_SMS = 'newsman_order_notify_sms';

	/**
	 * Wait in micro seconds before retry notify order status
	 */
	public const WAIT_RETRY_TIMEOUT_NOTIFY_STATUS = 5000000;

	/**
	 * Wait in micro seconds before retry notify order SMS
	 */
	public const WAIT_RETRY_TIMEOUT_NOTIFY_SMS = 5000000;

	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;
	/**
	 * Newsman config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

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
	 *  Action Scheduler Util
	 *
	 * @var NewsmanActionScheduler
	 */
	protected $action_scheduler;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->sms_config         = SmsConfig::init();
		$this->logger             = Logger::init();
		$this->telephone          = new Telephone();
		$this->action_scheduler   = new NewsmanActionScheduler();
	}

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Must send complete order status for remarketing to work properly.
		if ( ! $this->config->is_enabled_with_api() && ! $this->remarketing_config->is_active() ) {
			return;
		}

		foreach ( $this->config->get_order_status_to_name() as $status => $name ) {
			$send_status = new \Newsman\Order\SendStatus();
			if ( method_exists( $send_status, $name ) ) {
				add_action( 'woocommerce_order_status_' . $status, array( $send_status, $name ) );
			}
		}

		if ( ! $this->action_scheduler->is_allowed_single() ) {
			return;
		}

		add_action( self::ORDER_NOTIFY_STATUS, array( $this, 'send_order_status' ), 10, 3 );
		if ( $this->sms_config->is_enabled_with_api() ) {
			add_action( self::ORDER_NOTIFY_SMS, array( $this, 'send_sms' ), 10, 3 );
		}
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
		if ( ! $this->action_scheduler->is_allowed_single() ) {
			$this->send_order_status( $order_id, $status );
			$this->send_sms( $order_id, $status );
			return;
		}

		// Must send complete order status for remarketing to work properly.
		as_schedule_single_action(
			time(),
			self::ORDER_NOTIFY_STATUS,
			array(
				$order_id,
				$status,
				true,
			),
			$this->action_scheduler->get_group_order_change()
		);

		if ( $this->sms_config->is_enabled_with_api() ) {
			$config_name = $this->config->get_order_config_name_by_status( $status );
			// Create action if is enabled.
			if ( false !== $config_name && $this->sms_config->is_order_sms_active_by_name( $config_name ) ) {
				as_schedule_single_action(
					time(),
					self::ORDER_NOTIFY_SMS,
					array(
						$order_id,
						$status,
						true,
					),
					$this->action_scheduler->get_group_order_change()
				);
			}
		}
	}

	/**
	 * Send order status to Newsman API
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $status       Order status.
	 * @param string $is_scheduled Is action scheduled.
	 * @return bool Sent successfully
	 * @throws \Exception On API errors or other.
	 */
	public function send_order_status( $order_id, $status, $is_scheduled = false ) {
		if ( ! $this->config->is_enabled_with_api() ) {
			return false;
		}

		try {
			$context = new \Newsman\Service\Context\SetPurchaseStatus();
			$context->set_list_id( $this->config->get_list_id() )
				->set_order_id( $order_id )
				->set_order_status( $status );

			try {
				$purchase = new \Newsman\Service\SetPurchaseStatus();
				$result   = $purchase->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT_NOTIFY_STATUS . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT_NOTIFY_STATUS );

					$purchase = new \Newsman\Service\SetPurchaseStatus();
					$result   = $purchase->execute( $context );
				} else {
					throw $e;
				}
			}
			return true === $result;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
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

			try {
				$send_one = new \Newsman\Service\Sms\SendOne();
				$result   = $send_one->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT_NOTIFY_SMS . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT_NOTIFY_SMS );

					$send_one = new \Newsman\Service\Sms\SendOne();
					$result   = $send_one->execute( $context );
				} else {
					throw $e;
				}
			}

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
