<?php
/**
 * Tests for the PHP error log fallback of the logger on sites without WooCommerce.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Logger;

/**
 * Logger fallback tests.
 */
class LoggerFallbackTest extends NewsmanTestCase {
	/**
	 * Temporary file used as the PHP error log.
	 *
	 * @var string
	 */
	private $log_file = '';

	/**
	 * Previous PHP error_log setting.
	 *
	 * @var string|false
	 */
	private $previous_error_log = false;

	/**
	 * Previous WooCommerce logging flag.
	 *
	 * @var bool
	 */
	private $previous_wc_logging = false;

	/**
	 * Send the PHP error log to a temporary file and turn off the WooCommerce logger.
	 */
	public function set_up() {
		parent::set_up();
		$this->log_file            = wp_tempnam( 'newsman-logger' );
		$this->previous_error_log  = ini_get( 'error_log' );
		$this->previous_wc_logging = Logger::$is_wc_logging;
		ini_set( 'error_log', $this->log_file ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		Logger::$is_wc_logging = false;
		delete_option( 'newsman_developerlogseverity' );
	}

	/**
	 * Restore the PHP error log and the WooCommerce logging flag.
	 */
	public function tear_down() {
		ini_set( 'error_log', (string) $this->previous_error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		Logger::$is_wc_logging = $this->previous_wc_logging;
		if ( file_exists( $this->log_file ) ) {
			wp_delete_file( $this->log_file );
		}
		parent::tear_down();
	}

	/**
	 * Logger with a switch for the WooCommerce logger, so the fallback is tested in
	 * the WooCommerce and the WordPress-only jobs.
	 *
	 * @param bool $wc_logger Whether the WooCommerce logger class exists.
	 * @return Logger
	 */
	private function make_logger( $wc_logger = false ) {
		$logger            = new class() extends Logger {
			/**
			 * Whether the WooCommerce logger class exists.
			 *
			 * @var bool
			 */
			public $wc_logger = false;

			/**
			 * Whether the WooCommerce logger class exists.
			 *
			 * @return bool
			 */
			protected function has_wc_logger() {
				return $this->wc_logger;
			}
		};
		$logger->wc_logger = $wc_logger;
		return $logger;
	}

	/**
	 * Lines written to the PHP error log, without the date PHP adds.
	 *
	 * @return string[]
	 */
	private function logged_lines() {
		clearstatcache();
		if ( ! file_exists( $this->log_file ) ) {
			return array();
		}
		$lines = file( $this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		return array_map(
			function ( $line ) {
				return preg_replace( '/^\[[^\]]+\] /', '', $line );
			},
			$lines ? $lines : array()
		);
	}

	/**
	 * An error is written as one line with the prefix, the level and the context.
	 */
	public function test_error_is_written_with_prefix_and_context() {
		$logger = $this->make_logger();
		$logger->error( 'Something failed', array( 'list_id' => 14794 ) );

		$this->assertSame( array( '[NewsMAN] ERROR Something failed {"list_id":14794}' ), $this->logged_lines() );
	}

	/**
	 * The Logging level setting applies: error by default, debug when chosen, nothing with No Logging.
	 */
	public function test_logging_level_applies() {
		$logger = $this->make_logger();
		$logger->debug( 'debug at default' );
		$logger->warning( 'warning at default' );
		$logger->critical( 'critical at default' );
		$this->assertSame( array( '[NewsMAN] CRITICAL critical at default' ), $this->logged_lines() );

		update_option( 'newsman_developerlogseverity', '100' );
		$logger->debug( 'debug at debug' );
		$logger->log( 'info', 'info through log()' );
		$this->assertSame(
			array( '[NewsMAN] CRITICAL critical at default', '[NewsMAN] DEBUG debug at debug', '[NewsMAN] INFO info through log()' ),
			$this->logged_lines()
		);

		update_option( 'newsman_developerlogseverity', '2000' );
		$logger->emergency( 'emergency with no logging' );
		$this->assertCount( 3, $this->logged_lines() );
	}

	/**
	 * Every level method writes its own level.
	 */
	public function test_every_level_is_written() {
		update_option( 'newsman_developerlogseverity', '100' );
		$logger = $this->make_logger();
		$levels = array( 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' );
		foreach ( $levels as $level ) {
			$logger->$level( 'message ' . $level );
		}
		$expected = array_map(
			function ( $level ) {
				return '[NewsMAN] ' . strtoupper( $level ) . ' message ' . $level;
			},
			$levels
		);
		$this->assertSame( $expected, $this->logged_lines() );
	}

	/**
	 * An unknown level through log() writes nothing.
	 */
	public function test_unknown_level_is_ignored() {
		$logger = $this->make_logger();
		$logger->log( 'bogus', 'unknown level' );
		$logger->log( 'error', 'known level' );
		$this->assertSame( array( '[NewsMAN] ERROR known level' ), $this->logged_lines() );
	}

	/**
	 * Stores with WooCommerce keep logging only to the WooCommerce logger.
	 */
	public function test_nothing_is_written_when_the_woocommerce_logger_exists() {
		$logger = $this->make_logger( true );
		$logger->error( 'error on a WooCommerce store' );
		$this->assertSame( array(), $this->logged_lines() );
	}

	/**
	 * Nothing is written before all plugins are loaded, when it is not yet known whether WooCommerce is active.
	 */
	public function test_nothing_is_written_before_plugins_loaded() {
		global $wp_actions;
		$previous = isset( $wp_actions['plugins_loaded'] ) ? $wp_actions['plugins_loaded'] : null;
		unset( $wp_actions['plugins_loaded'] );
		try {
			$this->make_logger()->error( 'too early' );
		} finally {
			if ( null !== $previous ) {
				$wp_actions['plugins_loaded'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
		$this->assertSame( array(), $this->logged_lines() );
	}

	/**
	 * The API key, the authenticate token and the export authorization key are replaced with ****.
	 */
	public function test_secrets_are_masked() {
		update_option( 'newsman_apikey', 'apikey1234567890' );
		update_option( 'newsman_authenticate_token', 'token1234567890' );
		update_option( 'newsman_export_authorize_header_key', 'header1234567890' );

		$logger = $this->make_logger();
		$logger->error(
			'https://example.com/?nzmhash=apikey1234567890',
			array(
				'token' => 'token1234567890',
				'key'   => 'header1234567890',
			)
		);

		$this->assertSame( array( '[NewsMAN] ERROR https://example.com/?nzmhash=**** {"token":"****","key":"****"}' ), $this->logged_lines() );
	}

	/**
	 * A long entry with line breaks becomes one line of at most 2000 characters plus "...".
	 */
	public function test_long_entry_is_one_short_line() {
		$logger = $this->make_logger();
		$logger->log_exception( new \Exception( "first line\nsecond line " . str_repeat( 'x', 5000 ) ) );

		$lines = $this->logged_lines();
		$this->assertCount( 1, $lines );
		$this->assertStringStartsWith( '[NewsMAN] ERROR first line second line xxx', $lines[0] );
		$this->assertSame( Logger::FALLBACK_MAX_LENGTH + 3, strlen( $lines[0] ) );
		$this->assertStringEndsWith( '...', $lines[0] );
	}

	/**
	 * The newsman_logger_fallback filter can turn the fallback off.
	 */
	public function test_filter_turns_the_fallback_off() {
		add_filter( 'newsman_logger_fallback', '__return_false' );
		$this->make_logger()->error( 'filtered out' );
		remove_filter( 'newsman_logger_fallback', '__return_false' );

		$this->assertSame( array(), $this->logged_lines() );
	}

	/**
	 * A failure while logging never reaches the caller, and logging again from inside the logger writes nothing.
	 */
	public function test_failures_and_loops_never_break_the_caller() {
		$logger = $this->make_logger();
		$throw  = function () {
			throw new \RuntimeException( 'broken filter' );
		};
		add_filter( 'newsman_logger_fallback', $throw );
		$logger->error( 'with a broken filter' );
		remove_filter( 'newsman_logger_fallback', $throw );
		$this->assertSame( array(), $this->logged_lines() );

		$again = function ( $write ) use ( $logger ) {
			$logger->error( 'logged from the filter' );
			return $write;
		};
		add_filter( 'newsman_logger_fallback', $again );
		$logger->error( 'outer entry' );
		remove_filter( 'newsman_logger_fallback', $again );
		$this->assertSame( array( '[NewsMAN] ERROR outer entry' ), $this->logged_lines() );

		$logger->error( 'after the failures' );
		$this->assertSame( array( '[NewsMAN] ERROR outer entry', '[NewsMAN] ERROR after the failures' ), $this->logged_lines() );
	}

	/**
	 * The real logger on a site without WooCommerce (WordPress-only job).
	 */
	public function test_real_logger_without_woocommerce() {
		if ( class_exists( '\WC_Logger' ) ) {
			$this->markTestSkipped( 'WooCommerce is loaded.' );
		}
		Logger::init()->error( 'real logger' );
		$this->assertSame( array( '[NewsMAN] ERROR real logger' ), $this->logged_lines() );
	}

	/**
	 * The real logger on a store with WooCommerce writes nothing to the PHP error log.
	 */
	public function test_real_logger_with_woocommerce_writes_nothing() {
		if ( ! class_exists( '\WC_Logger' ) ) {
			$this->markTestSkipped( 'WooCommerce is not loaded.' );
		}
		Logger::init()->error( 'real logger on a WooCommerce store, flag not set yet' );
		$this->assertSame( array(), $this->logged_lines() );
	}
}
