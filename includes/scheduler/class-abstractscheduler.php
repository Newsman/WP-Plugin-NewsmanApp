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

use Newsman\Logger;
use Newsman\Util\ActionScheduler as NewsmanActionScheduler;

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
	 *  Action Scheduler Util
	 *
	 * @var NewsmanActionScheduler
	 */
	protected $action_scheduler;

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
		$this->action_scheduler = new NewsmanActionScheduler();
		$this->logger           = Logger::init();
	}
}
