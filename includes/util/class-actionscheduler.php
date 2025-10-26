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

namespace Newsman\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Woo Commerce action scheduler util class
 *
 * @class \Newsman\Util\ActionScheduler
 */
class ActionScheduler {
	/**
	 * Action scheduler group for order change
	 */
	public const GROUP_ORDER_CHANGE = 'newsman_order_change';

	/**
	 * Actions scheduler exist
	 *
	 * @return bool
	 */
	public function exist() {
		$exists = class_exists( '\ActionScheduler' );
		if ( function_exists( 'apply_filters' ) ) {
			$exists = apply_filters( 'newsman_action_scheduler_exist', $exists );
		}
		return $exists;
	}

	/**
	 * Is as_schedule_single_action exist
	 *
	 * @return bool
	 */
	public function is_single_action() {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Get action scheduler group for order change
	 *
	 * @return string
	 */
	public function get_group_order_change() {
		return self::GROUP_ORDER_CHANGE;
	}
}
