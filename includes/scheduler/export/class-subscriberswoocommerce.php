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
 * Class Scheduler Export WooCommerce Subscribers
 *
 * @class \Newsman\Scheduler\Export\SubscribersWoocommerce
 */
class SubscribersWoocommerce extends AbstractScheduler {
	/**
	 * Background event hook export WooCommerce subscribers
	 */
	public const BACKGROUND_EVENT_HOOK = 'newsman_export_woocommerce_subscribers';

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
			$exporter = new \Newsman\Export\Retriever\SubscribersWoocommerce();
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
	public function schedule_all( $limit = 1000, $blog_id = null ) {
		$exporter = new \Newsman\Export\Retriever\SubscribersWoocommerce();
		$count    = $exporter->get_count_subscribers( $blog_id );
		if ( 0 >= $count ) {
			$this->logger->info( 'No subscribers to export' );
			return false;
		}

		$steps = ceil( $count / $limit );
		for ( $i = 0; $i < $steps; $i++ ) {
			$this->schedule( $i * $limit, $limit, $blog_id, null, $count );
		}
		$this->logger->info( 'Scheduled ' . $steps . ' batches of subscribers from orders to export, limit ' . $limit );
		return true;
	}

	/**
	 * Schedule action
	 *
	 * @param int         $start Pagination start.
	 * @param int         $limit Pagination limit.
	 * @param null|int    $blog_id WP blog ID.
	 * @param null|string $date_created Consider orders after date.
	 * @param null|int    $pre_count Pre count of orders.
	 *
	 * @return void
	 */
	public function schedule( $start, $limit = 1000, $blog_id = null, $date_created = null, $pre_count = null ) {
		as_schedule_single_action(
			time(),
			self::BACKGROUND_EVENT_HOOK,
			array(
				array(
					'start'        => $start,
					'limit'        => $limit,
					'cronlast'     => true,
					'date_created' => $date_created,
					'pre_count'    => $pre_count,
				),
				$blog_id,
			),
			$this->action_scheduler->get_group_mass_export_subscribers_woocommerce()
		);
		$this->logger->info(
			'Scheduled export WooCommerce subscribers from orders ' . $start . ',' . $limit . ' Site ID=' . $blog_id .
				' date=' . $date_created . ' pre count=' . $pre_count
		);
	}

	/**
	 * Get hooks events
	 *
	 * @return string[]
	 */
	public function get_hooks_events() {
		return array(
			self::BACKGROUND_EVENT_HOOK,
		);
	}
}
