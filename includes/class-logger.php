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

namespace Newsman;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman plugin logger.
 *
 * Writes to the WooCommerce logger when WooCommerce is active. Without WooCommerce
 * it writes one line per entry to the PHP error log (wp-content/debug.log when
 * WP_DEBUG_LOG is enabled), with the same Logging level setting.
 *
 * @class \Newsman\Logger
 */
class Logger {
	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Whether WooCommerce logging exists
	 *
	 * @var bool
	 */
	public static $is_wc_logging = false;

	/**
	 * Maximum length of an entry written to the PHP error log
	 */
	const FALLBACK_MAX_LENGTH = 2000;

	/**
	 * Severity of each level for the PHP error log, the same values as WC_Log_Levels.
	 * WooCommerce classes are not used here, so the fallback works without WooCommerce.
	 *
	 * @var int[]
	 */
	protected static $fallback_severities = array(
		'emergency' => 800,
		'alert'     => 700,
		'critical'  => 600,
		'error'     => 500,
		'warning'   => 400,
		'notice'    => 300,
		'info'      => 200,
		'debug'     => 100,
	);

	/**
	 * Whether an entry is being written to the PHP error log, to stop a loop
	 * if a filter used while writing logs again.
	 *
	 * @var bool
	 */
	protected static $is_fallback_logging = false;

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
		$this->config = Config::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self \Newsman\Logger
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Logger();
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function log( $level, $message, $context = array() ) {
		do_action( 'newsman_logger_before_log', $level, $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( $level, $message, $context );
			return;
		}

		$found           = false;
		$config_severity = $this->config->get_log_severity();
		foreach ( \WC_Log_Levels::get_all_level_severities() as $a_level => $severity ) {
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function debug( $message, $context = array() ) {
		do_action( 'newsman_logger_before_debug', $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( 'debug', $message, $context );
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function info( $message, $context = array() ) {
		do_action( 'newsman_logger_before_info', $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( 'info', $message, $context );
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function notice( $message, $context = array() ) {
		do_action( 'newsman_logger_before_notice', $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( 'notice', $message, $context );
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function warning( $message, $context = array() ) {
		do_action( 'newsman_logger_before_warning', $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( 'warning', $message, $context );
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function error( $message, $context = array() ) {
		do_action( 'newsman_logger_before_error', $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( 'error', $message, $context );
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function critical( $message, $context = array() ) {
		do_action( 'newsman_logger_before_critical', $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( 'critical', $message, $context );
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function alert( $message, $context = array() ) {
		do_action( 'newsman_logger_before_alert', $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( 'alert', $message, $context );
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
	 * @see \WC_Log_Levels::$level_to_severity
	 */
	public function emergency( $message, $context = array() ) {
		do_action( 'newsman_logger_before_emergency', $message, $context );
		if ( ! self::$is_wc_logging ) {
			$this->fallback_log( 'emergency', $message, $context );
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
	 * @param \Exception $e \Exception to log.
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

	/**
	 * Write an entry to the PHP error log when the WooCommerce logger is not available.
	 * It never throws: a failure to log is ignored.
	 *
	 * @param string $level Log level.
	 * @param mixed  $message Logging message.
	 * @param array  $context Logger context.
	 * @return void
	 */
	protected function fallback_log( $level, $message, $context = array() ) {
		if ( self::$is_fallback_logging ) {
			return;
		}
		self::$is_fallback_logging = true;
		try {
			if ( $this->can_fallback_log( $level, $message, $context ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log destination when WooCommerce is not active.
				error_log( $this->format_fallback_entry( $level, $message, $context ) );
			}
		} catch ( \Throwable $e ) {
			// Logging must never break the page.
			unset( $e );
		} finally {
			self::$is_fallback_logging = false;
		}
	}

	/**
	 * Whether an entry goes to the PHP error log.
	 * Only after all plugins are loaded and only when WooCommerce is not active, so
	 * stores with WooCommerce keep logging only to the WooCommerce logger.
	 *
	 * @param string $level Log level.
	 * @param mixed  $message Logging message.
	 * @param array  $context Logger context.
	 * @return bool
	 */
	protected function can_fallback_log( $level, $message, $context ) {
		if ( ! is_string( $level ) || ! isset( self::$fallback_severities[ $level ] ) ) {
			return false;
		}
		if ( ! function_exists( 'did_action' ) || ! did_action( 'plugins_loaded' ) ) {
			return false;
		}
		if ( $this->has_wc_logger() ) {
			return false;
		}
		if ( ! function_exists( 'error_log' ) || ! $this->config ) {
			return false;
		}
		if ( $this->config->get_log_severity() > self::$fallback_severities[ $level ] ) {
			return false;
		}

		/**
		 * Whether to write a NewsMAN log entry to the PHP error log on sites without WooCommerce.
		 *
		 * @param bool   $write   Whether to write the entry. Default true.
		 * @param string $level   Log level.
		 * @param mixed  $message Logging message.
		 * @param array  $context Logger context.
		 */
		return (bool) apply_filters( 'newsman_logger_fallback', true, $level, $message, $context );
	}

	/**
	 * Whether the WooCommerce logger class exists.
	 *
	 * @return bool
	 */
	protected function has_wc_logger() {
		return class_exists( '\WC_Logger' );
	}

	/**
	 * Format one line for the PHP error log: "[NewsMAN] LEVEL message {context}".
	 *
	 * @param string $level Log level.
	 * @param mixed  $message Logging message.
	 * @param array  $context Logger context.
	 * @return string
	 */
	protected function format_fallback_entry( $level, $message, $context ) {
		if ( ! is_scalar( $message ) ) {
			$message = function_exists( 'wp_json_encode' ) ? wp_json_encode( $message ) : '';
		}
		$entry = '[NewsMAN] ' . strtoupper( $level );
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_current_blog_id' ) ) {
			$entry .= ' (blog ' . get_current_blog_id() . ')';
		}
		$entry .= ' ' . $message;

		if ( is_array( $context ) && ! empty( $context ) && function_exists( 'wp_json_encode' ) ) {
			$json = wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( is_string( $json ) ) {
				$entry .= ' ' . $json;
			}
		}

		$entry = str_replace( array( "\r\n", "\r", "\n" ), ' ', $entry );
		$entry = $this->mask_fallback_secrets( $entry );
		if ( strlen( $entry ) > self::FALLBACK_MAX_LENGTH ) {
			$entry = ( function_exists( 'mb_strcut' ) ? mb_strcut( $entry, 0, self::FALLBACK_MAX_LENGTH, 'UTF-8' ) : substr( $entry, 0, self::FALLBACK_MAX_LENGTH ) ) . '...';
		}
		return $entry;
	}

	/**
	 * Replace the NewsMAN API key, authenticate token and export authorization key
	 * with **** in an entry for the PHP error log, which can be less protected
	 * than the WooCommerce logs.
	 *
	 * @param string $entry Log entry.
	 * @return string
	 */
	protected function mask_fallback_secrets( $entry ) {
		$secrets = array(
			$this->config->get_api_key(),
			$this->config->get_authenticate_token(),
			$this->config->get_export_authorize_header_key(),
		);
		foreach ( $secrets as $secret ) {
			if ( is_string( $secret ) && strlen( $secret ) >= 6 ) {
				$entry = str_replace( $secret, '****', $entry );
			}
		}
		return $entry;
	}
}
