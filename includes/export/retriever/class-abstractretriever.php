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

namespace Newsman\Export\Retriever;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Abstract Retriever
 *
 * @class \Newsman\Export\Retriever\AbstractRetriever
 */
class AbstractRetriever {
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
	 * Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Telephone util
	 *
	 * @var Telephone
	 */
	protected $telephone;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->logger             = Logger::init();
		$this->telephone          = new Telephone();
	}

	/**
	 * Is different WP blog than current
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_different_blog( $blog_id = null ) {
		if ( ! is_multisite() ) {
			return false;
		}

		$current_blog_id = get_current_blog_id();
		if ( ( null === $current_blog_id ) || ( null === $blog_id ) ) {
			return false;
		}
		return ( (int) $blog_id !== $current_blog_id );
	}

	/**
	 * Clean telephone string
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public function clean_phone( $phone ) {
		return $this->telephone->clean( $phone );
	}
}
