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
	 * Repeat short action every of X hours
	 */
	public const RECURRING_SHORT_INTERVAL = 24;

	/**
	 * Repeat long action every of X hours
	 */
	public const RECURRING_LONG_INTERVAL = 720;

	/**
	 * Is allow action to run
	 *
	 * @return bool
	 */
	public function is_allow() {
		$allow = $this->action_scheduler->is_allowed_recurring();
		$allow = $allow && $this->remarketing_config->is_export_wordpress_subscribers();

		return apply_filters( 'newsman_scheduler_export_recurring_wordpress_subscribers_allow', $allow );
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

		if ( ! $this->action_scheduler->has_ensure_recurring() && is_admin() ) {
			// Fallback: runs on every admin request.
			add_action( 'init', array( $this, 'schedule_short' ) );
			add_action( 'init', array( $this, 'schedule_long' ) );
		}
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

		if ( $this->action_scheduler->has_ensure_recurring() ) {
			add_action( 'action_scheduler_ensure_recurring_actions', array( $this, 'schedule_short' ), 10 );
			add_action( 'action_scheduler_ensure_recurring_actions', array( $this, 'schedule_long' ), 10 );
		}
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
				$this->logger->info( 'No users with role subscriber to export, date=' . $date_created );
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
		$date->sub( new \DateInterval( 'P' . $this->remarketing_config->get_export_wordpress_recurring_short_days() . 'D' ) );

		$this->schedule( self::BACKGROUND_EVENT_HOOK_SHORT, $this->get_recurring_short_interval(), $date->format( 'Y-m-d' ) );

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

		$date = new \DateTime();
		$date->sub( new \DateInterval( 'P' . $this->remarketing_config->get_export_wordpress_recurring_long_days() . 'D' ) );

		$this->schedule( self::BACKGROUND_EVENT_HOOK_LONG, $this->get_recurring_long_interval(), $date->format( 'Y-m-d' ) );

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

		$has = $this->action_scheduler->has_scheduled_action(
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
		if ( null === $has ) {
			return false;
		}

		if ( ! $has ) {
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

	/**
	 * Get the recurring short interval in hours.
	 *
	 * @return int The value of the recurring short interval in hours.
	 */
	public function get_recurring_short_interval() {
		$interval = apply_filters(
			'newsman_scheduler_export_recurring_wordpress_subscribers_short_interval',
			self::RECURRING_SHORT_INTERVAL
		);
		if ( $interval < 4 ) {
			$interval = self::RECURRING_SHORT_INTERVAL;
		}
		return $interval;
	}

	/**
	 * Get the recurring long interval in hours.
	 *
	 * @return int The value of the recurring long interval in hours.
	 */
	public function get_recurring_long_interval() {
		$interval = apply_filters(
			'newsman_scheduler_export_recurring_wordpress_subscribers_long_interval',
			self::RECURRING_LONG_INTERVAL
		);
		if ( $interval < 168 ) {
			$interval = self::RECURRING_LONG_INTERVAL;
		}
		return $interval;
	}
}
