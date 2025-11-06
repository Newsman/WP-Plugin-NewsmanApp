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

use Newsman\Config;

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
	 * Action scheduler group for order save
	 */
	public const GROUP_ORDER_SAVE = 'newsman_order_save';
	/**
	 * Action scheduler group for subscribe to email and SMS lists
	 */
	public const GROUP_SUBSCRIBE = 'newsman_subscribe';


	/**
	 * Action scheduler group for mass export WordPress subscribers
	 */
	public const GROUP_MASS_EXPORT_SUBSCRIBERS_WORDPRESS = 'newsman_mass_export_subscribers_wordpress';

	/**
	 * Action scheduler group for mass export Woo Commerce orders subscribers
	 */
	public const GROUP_MASS_EXPORT_SUBSCRIBERS_WOOCOMMERCE = 'newsman_mass_export_subscribers_woocommerce';

	/**
	 * Action scheduler group for mass export orders
	 */
	public const GROUP_MASS_EXPORT_ORDERS = 'newsman_mass_export_orders';

	/**
	 * Newsman Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Construct class
	 */
	public function __construct() {
		$this->config = Config::init();
	}

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
	 * Is action scheduler allowed to run single action
	 *
	 * @return bool
	 */
	public function is_allowed_single() {
		return $this->exist() &&
			$this->config->use_action_scheduler() &&
			$this->is_single_action();
	}

	/**
	 * Get action scheduler group for order change
	 *
	 * @return string
	 */
	public function get_group_order_change() {
		return self::GROUP_ORDER_CHANGE;
	}

	/**
	 * Get action scheduler group for order save
	 *
	 * @return string
	 */
	public function get_group_order_save() {
		return self::GROUP_ORDER_SAVE;
	}

	/**
	 * Get action scheduler group for subscribe to email and SMS lists
	 *
	 * @return string
	 */
	public function get_group_subscribe() {
		return self::GROUP_SUBSCRIBE;
	}

	/**
	 * Get action scheduler group for mass export WordPress subscribers
	 *
	 * @return string
	 */
	public function get_group_mass_export_subscribers_wordpress() {
		return self::GROUP_MASS_EXPORT_SUBSCRIBERS_WORDPRESS;
	}

	/**
	 * Get action scheduler group for mass export Woo Commerce orders subscribers
	 *
	 * @return string
	 */
	public function get_group_mass_export_subscribers_woocommerce() {
		return self::GROUP_MASS_EXPORT_SUBSCRIBERS_WOOCOMMERCE;
	}

	/**
	 * Get action scheduler group for mass export orders
	 *
	 * @return string
	 */
	public function get_group_mass_export_orders() {
		return self::GROUP_MASS_EXPORT_ORDERS;
	}
}
