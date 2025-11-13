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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronize order status for an email list (remarketing).
 *
 * @class \Newsman\Scheduler\Order\Status\SendStatus
 */
class SendStatus extends AbstractStatus {
	/**
	 * Background event hook notify order status
	 */
	public const BACKGROUND_HOOK_EVENT = 'newsman_order_notify_status';

	/**
	 * Wait in micro seconds before retry notify order status
	 */
	public const WAIT_RETRY_TIMEOUT = 5000000;

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Must send complete order status for remarketing to work properly.
		if ( ! $this->config->has_api_access() && ! $this->remarketing_config->is_active() ) {
			return;
		}

		foreach ( $this->config->get_order_status_to_name() as $status => $name ) {
			if ( method_exists( $this, $name ) ) {
				add_action( 'woocommerce_order_status_' . $status, array( $this, $name ) );
			}
		}

		if ( ! $this->action_scheduler->is_allowed_single() ) {
			return;
		}

		add_action( self::BACKGROUND_HOOK_EVENT, array( $this, 'send_order_status' ), 10, 3 );
	}

	/**
	 * Notify to Newsman order status
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return void
	 */
	public function notify( $order_id, $status ) {
		if ( ! $this->action_scheduler->is_allowed_single() ) {
			$this->send_order_status( $order_id, $status );
			return;
		}

		// Must send complete order status for remarketing to work properly.
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
		if ( ! $this->config->has_api_access() ) {
			return false;
		}

		do_action( 'newsman_order_status_send_status_before', $order_id, $status, $is_scheduled );

		try {
			$context = new \Newsman\Service\Context\Remarketing\SetPurchaseStatus();
			$context->set_list_id( $this->config->get_list_id() )
				->set_order_id( $order_id )
				->set_order_status( $status );
			$context = apply_filters( 'newsman_order_status_send_status_context', $context, $order_id, $status );

			try {
				$purchase = new \Newsman\Service\Remarketing\SetPurchaseStatus();
				$result   = $purchase->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT );

					$purchase = new \Newsman\Service\Remarketing\SetPurchaseStatus();
					$result   = $purchase->execute( $context );
				} else {
					throw $e;
				}
			}

			do_action( 'newsman_order_status_send_status_after', $order_id, $status, $is_scheduled );

			return true === $result;
		} catch ( \Exception $e ) {
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
