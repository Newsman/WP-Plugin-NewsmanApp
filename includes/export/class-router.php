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

namespace Newsman\Export;

use Newsman\Config;
use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Request
 *
 * @class \Newsman\Export\Router
 */
class Router {
	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Newsman logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config = Config::init();
		$this->logger = Logger::init();
	}

	/**
	 * Export data action.
	 * Used by newsman.app to fetch data from store.
	 *
	 * @return void
	 * @throws \Exception Throws standard exception on errors.
	 */
	public function execute() {
		$export_request = new \Newsman\Export\Request();
		if ( ! $export_request->is_export_request() ) {
			return;
		}

		if ( ! $this->config->is_enabled_with_api() ) {
			$result = array(
				'status'  => 403,
				'message' => 'API setting is not enabled in plugin',
			);
			$page   = new \Newsman\Page\Renderer();
			$page->display_json( $result );
		}

		try {
			$parameters = $export_request->get_request_parameters();
			$processor  = new \Newsman\Export\Retriever\Processor();
			$result     = $processor->process(
				$processor->get_code_by_data( $parameters ),
				get_current_blog_id(),
				$parameters
			);

			$page = new \Newsman\Page\Renderer();
			$page->display_json( $result );
		} catch ( \OutOfBoundsException $e ) {
			$this->logger->log_exception( $e );
			$result = array(
				'status'  => 403,
				'message' => $e->getMessage(),
			);

			$page = new \Newsman\Page\Renderer();
			$page->display_json( $result );
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// wp_die('Access Forbidden', 'Forbidden', array('response' => 403)); .
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			$result = array(
				'status'  => 0,
				'message' => $e->getMessage(),
			);

			$page = new \Newsman\Page\Renderer();
			$page->display_json( $result );
		}
	}
}
