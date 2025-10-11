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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman plugin WooCommerce logger
 *
 * @class Newsman_WC_Logger
 */
class Newsman_WC_Logger {
	/**
	 * Newsman config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * Whether WooCommerce logging exists
	 *
	 * @var bool
	 */
	public static $is_wc_logging = false;

	/**
	 * Logger context defaults
	 *
	 * @var string[]
	 */
	protected $logger_default_context = array(
		'source' => 'newsman',
	);

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Newsman_Config::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self Newsman_WC_Logger
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Newsman_WC_Logger();
		}

		return $instance;
	}

	/**
	 * Log a message.
	 *
	 * @param string $level Log level.
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function log( $level, $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}

		$found           = false;
		$config_severity = $this->config->get_log_severity();
		foreach ( WC_Log_Levels::get_all_level_severities() as $a_level => $severity ) {
			if ( $a_level === $level ) {
				$found = true;
				if ( $config_severity > $severity ) {
					return;
				}
				break;
			}
		}
		if ( ! $found ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->log( $level, $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function debug( $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}
		if ( $this->config->get_log_severity() > 100 ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->debug( $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function info( $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}
		if ( $this->config->get_log_severity() > 200 ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->info( $message, $context );
	}

	/**
	 * Log a notice message.
	 *
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function notice( $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}
		if ( $this->config->get_log_severity() > 300 ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->notice( $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function warning( $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}
		if ( $this->config->get_log_severity() > 400 ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->warning( $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function error( $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}
		if ( $this->config->get_log_severity() > 500 ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->error( $message, $context );
	}

	/**
	 * Log a critical message.
	 *
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function critical( $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}
		if ( $this->config->get_log_severity() > 600 ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->critical( $message, $context );
	}

	/**
	 * Log an alert message.
	 *
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function alert( $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}
		if ( $this->config->get_log_severity() > 700 ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->alert( $message, $context );
	}

	/**
	 * Log an emergency message.
	 *
	 * @param string $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 * @see WC_Log_Levels::$level_to_severity
	 */
	public function emergency( $message, $context = array() ) {
		if ( ! self::$is_wc_logging ) {
			return;
		}
		if ( $this->config->get_log_severity() > 800 ) {
			return;
		}

		$context = array_merge( $this->logger_default_context, $context );

		wc_get_logger()->emergency( $message, $context );
	}

	/**
	 * Log exception
	 *
	 * @param Exception $e Exception to log.
	 * @return void
	 */
	public function log_exception( $e ) {
		$this->error(
			$e->getMessage(),
			array(
				'exception' => array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
					'trace'   => $e->getTraceAsString(),
				),
			)
		);
	}
}
