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

namespace Newsman\Admin\Action;

use Newsman\Config;
use Newsman\Remarketing\Config as RemarketingConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin abstract action
 *
 * @class \Newsman\Admin\Action\AbstractAction
 */
class AbstractAction {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Remarketing Config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
	}
}
