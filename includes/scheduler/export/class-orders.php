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

namespace Newsman\Scheduler\Export;

use Newsman\Scheduler\AbstractScheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scheduler Export WooCommerce Orders
 *
 * @class \Newsman\Scheduler\Export\Orders
 */
class Orders extends AbstractScheduler {
	/**
	 * Background event hook export WooCommerce orders
	 */
	public const BACKGROUND_EVENT_HOOK = 'newsman_export_orders';

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! $this->action_scheduler->is_allowed_single() ) {
			return;
		}

		add_action( self::BACKGROUND_EVENT_HOOK, array( $this, 'process' ), 10, 2 );
	}

	/**
	 * Process scheduler
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function process( $data = array(), $blog_id = null ) {
		try {
			$exporter = new \Newsman\Export\Retriever\SendOrders();
			$exporter->process( $data, $blog_id );
			return true;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
	}

	/**
	 * Schedule all action
	 *
	 * @param int      $limit Pagination limit.
	 * @param null|int $blog_id WP blog ID.
	 *
	 * @return bool
	 */
	public function schedule_all( $limit = 200, $blog_id = null ) {
		$exporter = new \Newsman\Export\Retriever\SendOrders();
		$count    = $exporter->get_count_orders( $blog_id );
		if ( 0 >= $count ) {
			$this->logger->info( 'No orders to export' );
			return false;
		}

		$steps = ceil( $count / $limit );
		for ( $i = 0; $i < $steps; $i++ ) {
			$this->schedule( $i * $limit, $limit, $blog_id );
		}
		$this->logger->info( 'Scheduled ' . $steps . ' batches of orders to export, limit ' . $limit );
		return true;
	}

	/**
	 * Schedule action
	 *
	 * @param int      $start Pagination start.
	 * @param int      $limit Pagination limit.
	 * @param null|int $blog_id WP blog ID.
	 *
	 * @return void
	 */
	public function schedule( $start, $limit = 200, $blog_id = null ) {
		as_schedule_single_action(
			time(),
			self::BACKGROUND_EVENT_HOOK,
			array(
				array(
					'start'    => $start,
					'limit'    => $limit,
					'cronlast' => true,
				),
				$blog_id,
			),
			$this->action_scheduler->get_group_mass_export_orders()
		);
		$this->logger->info(
			'Scheduled export orders ' . $start . ',' . $limit . ' Site ID=' . $blog_id
		);
	}
}
