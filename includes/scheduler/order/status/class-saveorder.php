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

use Newsman\Export\Order\Mapper as OrderMapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send order to Newsman on order status changes
 *
 * @class \Newsman\Scheduler\Order\Status\SaveOrder
 */
class SaveOrder extends AbstractStatus {
	/**
	 * Background event hook order save
	 */
	public const BACKGROUND_HOOK_EVENT = 'newsman_order_save';

	/**
	 * Wait in micro seconds before retry order save
	 */
	public const WAIT_RETRY_TIMEOUT = 5000000;

	/**
	 * Order Mapper
	 *
	 * @var OrderMapper
	 */
	protected $order_mapper;

	/**
	 * Class construct
	 */
	public function __construct() {
		parent::__construct();
		$this->order_mapper = new OrderMapper();
	}

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! ( $this->config->is_enabled_with_api() && $this->remarketing_config->is_active() ) ) {
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

		add_action( self::BACKGROUND_HOOK_EVENT, array( $this, 'order_save' ), 10, 3 );
	}

	/**
	 * Notify an order Newsman on order status change
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return void
	 */
	public function notify( $order_id, $status ) {
		if ( ! $this->action_scheduler->is_allowed_single() ) {
			$this->order_save( $order_id, $status );
			return;
		}

		if ( $this->config->is_enabled_with_api() && $this->remarketing_config->is_active() ) {
			as_schedule_single_action(
				time(),
				self::BACKGROUND_HOOK_EVENT,
				array(
					$order_id,
					$status,
					true,
				),
				$this->action_scheduler->get_group_order_save()
			);
		}
	}

	/**
	 * Send order save to Newsman API
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $status       Order status.
	 * @param string $is_scheduled Is action scheduled.
	 * @return bool Sent successfully
	 * @throws \Exception On API errors or other.
	 */
	public function order_save( $order_id, $status, $is_scheduled = false ) {
		if ( ! ( $this->config->is_enabled_with_api() && $this->remarketing_config->is_active() ) ) {
			return false;
		}
		try {
			$order = wc_get_order( $order_id );
			do_action( 'newsman_order_status_order_save_before', $order, $status, $is_scheduled );
			$order_data = $this->order_mapper->to_array( $order );

			$context = new \Newsman\Service\Context\Remarketing\SaveOrder();
			$context->set_list_id( $this->config->get_list_id() )
				->set_order_details( $order_data['details'] )
				->set_order_products( $order_data['products'] );
			$context = apply_filters( 'newsman_order_status_order_save_context', $context, $order, $status );

			try {
				$save_order = new \Newsman\Service\Remarketing\SaveOrder();
				$result     = $save_order->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT );

					$save_order = new \Newsman\Service\Remarketing\SaveOrder();
					$result     = $save_order->execute( $context );
				} else {
					throw $e;
				}
			}

			do_action( 'newsman_order_status_order_save_after', $order, $status, $is_scheduled );

			return true === $result;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
	}
}
