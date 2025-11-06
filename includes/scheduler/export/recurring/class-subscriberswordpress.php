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

namespace Newsman\Scheduler\Export\Recurring;

use Newsman\Scheduler\AbstractScheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scheduler Export Users with Role Subscriber from WordPress Recurring
 *
 * @class \Newsman\Scheduler\Export\Recurring\SubscribersWordpress
 */
class SubscribersWordpress extends AbstractScheduler {
	/**
	 * Background event hook recurring export users with role subscriber from WordPress short period of time
	 */
	public const BACKGROUND_EVENT_HOOK_SHORT = 'newsman_recurring_export_wordpress_subscribers_short';

	/**
	 * Background event hook recurring export users with role subscriber from WordPress long period of time
	 */
	public const BACKGROUND_EVENT_HOOK_LONG = 'newsman_recurring_export_wordpress_subscribers_long';

	/**
	 * Is allow action to run
	 *
	 * @return bool
	 */
	public function is_allow() {
		if ( ! $this->action_scheduler->is_allowed_recurring() ) {
			return false;
		}
		if ( ! $this->remarketing_config->is_export_wordpress_subscribers() ) {
			return false;
		}

		return true;
	}

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! $this->is_allow() ) {
			return;
		}

		add_action( self::BACKGROUND_EVENT_HOOK_SHORT, array( $this, 'process_recurring' ), 10, 2 );
		add_action( self::BACKGROUND_EVENT_HOOK_LONG, array( $this, 'process_recurring' ), 10, 2 );

		$this->schedule_short();
		$this->schedule_long();
	}

	/**
	 * Init WordPress and Woo Commerce hooks for admin.
	 *
	 * @return void
	 */
	public function init_admin_hooks() {
		if ( ! $this->is_allow() ) {
			return;
		}

		add_action( 'action_scheduler_ensure_recurring_actions', array( $this, 'schedule_short' ), 10 );
		add_action( 'action_scheduler_ensure_recurring_actions', array( $this, 'schedule_long' ), 10 );
	}

	/**
	 * Process scheduler
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function process_recurring( $data = array(), $blog_id = null ) {
		if ( ! $this->is_allow() ) {
			return false;
		}

		try {
			$exporter     = new \Newsman\Export\Retriever\SubscribersWordpress();
			$limit        = $data['limit'];
			$date_created = $data['date_created'];
			$count        = $exporter->get_count_subscribers( $blog_id, $date_created );
			if ( 0 >= $count ) {
				$this->logger->info( 'No users with role subscriber to export' );
				return false;
			}

			$export_scheduler = new \Newsman\Scheduler\Export\SubscribersWordpress();
			$steps            = ceil( $count / $limit );
			for ( $i = 0; $i < $steps; $i++ ) {
				$export_scheduler->schedule( $i * $limit, $limit, $blog_id, $date_created, $count );
			}

			return true;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
	}

	/**
	 * Schedule short interval recurring.
	 * Export only latest subscribers from orders on a given frame of time.
	 *
	 * @return bool
	 */
	public function schedule_short() {
		if ( ! $this->is_allow() ) {
			return false;
		}

		$date = new \DateTime();
		$date->sub( new \DateInterval( 'P7D' ) );

		$this->schedule( self::BACKGROUND_EVENT_HOOK_SHORT, 7 * 24, $date->format( 'Y-m-d' ) );

		return true;
	}

	/**
	 * Schedule long interval recurring.
	 * Export all subscribers.
	 *
	 * @return bool
	 */
	public function schedule_long() {
		if ( ! $this->is_allow() ) {
			return false;
		}

		$this->schedule( self::BACKGROUND_EVENT_HOOK_LONG, 30 * 24 );

		return true;
	}

	/**
	 * Schedule action
	 *
	 * @param string   $hook Hook even for action scheduler.
	 * @param int      $interval_hours Hours for recurring scheduled action.
	 * @param int      $date_created Consider subscribers from orders after this date.
	 * @param int      $limit Pagination limit.
	 * @param null|int $blog_id WP blog ID.
	 *
	 * @return bool
	 */
	public function schedule( $hook, $interval_hours = 24, $date_created = null, $limit = 1000, $blog_id = null ) {
		if ( ! $this->is_allow() ) {
			return false;
		}

		if ( ! as_has_scheduled_action( $hook ) ) {
			as_schedule_recurring_action(
				time(),
				$interval_hours * HOUR_IN_SECONDS,
				$hook,
				array(
					array(
						'limit'        => $limit,
						'date_created' => $date_created,
					),
					'blog_id' => $blog_id,
				),
				$this->action_scheduler->get_group_mass_export_subscribers_wordpress()
			);
		}

		return true;
	}

	/**
	 * Get hooks events
	 *
	 * @return string[]
	 */
	public function get_hooks_events() {
		return array(
			self::BACKGROUND_EVENT_HOOK_SHORT,
			self::BACKGROUND_EVENT_HOOK_LONG,
		);
	}
}
