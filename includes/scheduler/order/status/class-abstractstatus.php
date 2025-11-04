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

use Newsman\Scheduler\AbstractScheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Abstract Scheduler Order Status
 *
 * @class \Newsman\Scheduler\Order\Status\AbstractStatus
 */
class AbstractStatus extends AbstractScheduler {
	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		$this->logger->error( 'Method init_hooks not implemented by ' . get_class( $this ) );
	}

	/**
	 * Notify to Newsman about order status changes:
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return void
	 */
	public function notify( $order_id, $status ) {
		$this->logger->error( 'Method notify not implemented by ' . get_class( $this ) );
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
