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

namespace Newsman\Scheduler;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Util\ActionScheduler as NewsmanActionScheduler;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Abstract Scheduler
 *
 * @class \Newsman\Scheduler\AbstractScheduler
 */
class AbstractScheduler {
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
	 *  Action Scheduler Util
	 *
	 * @var NewsmanActionScheduler
	 */
	protected $action_scheduler;

	/**
	 * Telephone util
	 *
	 * @var Telephone
	 */
	protected $telephone;

	/**
	 * Newsman logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->action_scheduler   = new NewsmanActionScheduler();
		$this->telephone          = new Telephone();
		$this->logger             = Logger::init();
	}

	/**
	 * Unschedule all actions of hook
	 *
	 * @return void
	 */
	public function unschedule_all_actions() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( $this->get_hooks_events() as $hook ) {
				as_unschedule_all_actions( $hook );
			}
		}
	}

	/**
	 * Get hooks events
	 *
	 * @return array
	 */
	public function get_hooks_events() {
		return array();
	}
}
