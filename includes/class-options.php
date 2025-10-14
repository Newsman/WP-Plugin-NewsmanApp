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
 * Newsman WP options class
 *
 * @class \Newsman\Options
 */
class Options {
	/**
	 * Single instance of this class
	 *
	 * @var $this|null
	 */
	private static $instance = null;

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * WordPress database object
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Last error message
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Get the single instance
	 *
	 * @return Options Instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct class
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'newsman_options';
	}

	/**
	 * Get a single option from the database
	 *
	 * @param string $option_name The name of the option to retrieve.
	 * @param mixed  $default_value Default value if option doesn't exist.
	 *
	 * @return mixed The option value
	 */
	public function get_option( $option_name, $default_value = false ) {
		try {
			$query = $this->wpdb->prepare(
				'SELECT option_value FROM %s WHERE option_name = %s LIMIT 1',
				$this->table_name,
				$option_name
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $this->wpdb->get_var( $query );

			if ( $this->wpdb->last_error ) {
				$this->last_error = $this->wpdb->last_error;
				$this->log_error( "Error getting option '{$option_name}': {$this->last_error}" );

				return $default_value;
			}

			if ( null === $result ) {
				return $default_value;
			}

			return maybe_unserialize( $result );
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			$this->log_error( "Exception getting option '{$option_name}': {$e->getMessage()}" );

			return $default_value;
		}
	}

	/**
	 * Add an option in the database or do not do anything if already exists.
	 *
	 * @param string $option_name The name of the option.
	 * @param mixed  $option_value The value of the option.
	 * @param string $autoload Whether to autoload the option.
	 *
	 * @return bool Success or failure
	 */
	public function add_option( $option_name, $option_value, $autoload = 'on' ) {
		try {
			$serialized_value = maybe_serialize( $option_value );

			$result = false;
			if ( ! $this->option_exists( $option_name ) ) {
				$result = $this->wpdb->insert(
					$this->table_name,
					array(
						'option_name'  => $option_name,
						'option_value' => $serialized_value,
						'autoload'     => $autoload,
					),
					array( '%s', '%s', '%s' ),
				);
			}

			if ( $this->wpdb->last_error ) {
				$this->last_error = $this->wpdb->last_error;
				$this->log_error( "Error updating option '{$option_name}': {$this->last_error}" );

				return false;
			}

			return false !== $result;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			$this->log_error( "Exception updating option '{$option_name}': {$e->getMessage()}" );

			return false;
		}
	}

	/**
	 * Update or add an option in the database
	 *
	 * @param string $option_name The name of the option.
	 * @param mixed  $option_value The value of the option.
	 * @param string $autoload Whether to autoload the option.
	 *
	 * @return bool Success or failure
	 */
	public function update_option( $option_name, $option_value, $autoload = 'on' ) {
		try {
			$serialized_value = maybe_serialize( $option_value );

			if ( $this->option_exists( $option_name ) ) {
				$result = $this->wpdb->update(
					$this->table_name,
					array(
						'option_value' => $serialized_value,
						'autoload'     => $autoload,
					),
					array( 'option_name' => $option_name ),
					array( '%s', '%s' ),
					array( '%s' ),
				);
			} else {
				$result = $this->wpdb->insert(
					$this->table_name,
					array(
						'option_name'  => $option_name,
						'option_value' => $serialized_value,
						'autoload'     => $autoload,
					),
					array( '%s', '%s', '%s' ),
				);
			}

			if ( $this->wpdb->last_error ) {
				$this->last_error = $this->wpdb->last_error;
				$this->log_error( "Error updating option '{$option_name}': {$this->last_error}" );

				return false;
			}

			return false !== $result;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			$this->log_error( "Exception updating option '{$option_name}': {$e->getMessage()}" );

			return false;
		}
	}

	/**
	 * Delete an option from the database
	 *
	 * @param string $option_name The name of the option.
	 * @return bool Success or failure
	 */
	public function delete_option( $option_name ) {
		try {
			$result = $this->wpdb->delete(
				$this->table_name,
				array( 'option_name' => $option_name ),
				array( '%s' ),
			);

			if ( $this->wpdb->last_error ) {
				$this->last_error = $this->wpdb->last_error;
				$this->log_error( "Error deleting option '{$option_name}': {$this->last_error}" );

				return false;
			}

			return false !== $result;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			$this->log_error( "Exception deleting option '{$option_name}': {$e->getMessage()}" );

			return false;
		}
	}

	/**
	 * Check if an option exists
	 *
	 * @param string $option_name The name of the option.
	 * @return bool Whether the option exists
	 */
	public function option_exists( $option_name ) {
		try {
			$query = $this->wpdb->prepare(
				'SELECT COUNT(*) FROM %s WHERE option_name = %s',
				$this->table_name,
				$option_name
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count = (int) $this->wpdb->get_var( $query );

			if ( $this->wpdb->last_error ) {
				$this->last_error = $this->wpdb->last_error;
				$this->log_error( "Error checking if option '{$option_name}' exists: {$this->last_error}" );

				return false;
			}

			return $count > 0;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			$this->log_error( "Exception checking if option '{$option_name}' exists: {$e->getMessage()}" );

			return false;
		}
	}

	/**
	 * Get all options from the database
	 *
	 * @param bool $autoload_only Whether to get only autoloaded options.
	 * @return array Associative array of options
	 */
	public function get_all_options( $autoload_only = false ) {
		try {
			if ( $autoload_only ) {
				$query = $this->wpdb->prepare(
					'SELECT option_name, option_value FROM %s WHERE autoload = %s',
					$this->table_name,
					'on'
				);
			} else {
				$query = $this->wpdb->prepare(
					'SELECT option_name, option_value FROM %s',
					$this->table_name
				);
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $this->wpdb->get_results( $query );

			if ( $this->wpdb->last_error ) {
				$this->last_error = $this->wpdb->last_error;
				$this->log_error( "Error getting all options: {$this->last_error}" );

				return array();
			}

			if ( ! $results ) {
				return array();
			}

			$options = array();

			foreach ( $results as $result ) {
				$options[ $result->option_name ] = maybe_unserialize( $result->option_value );
			}

			return $options;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			$this->log_error( "Exception getting all options: {$e->getMessage()}" );

			return array();
		}
	}

	/**
	 * Get last error message
	 *
	 * @return string Last error message
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Log an error
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( esc_html( 'Newsman Options Error: ' . $message ) );
		}
	}

	/**
	 * Delete the database table
	 *
	 * @return bool Success or failure
	 */
	public function drop_table() {
		try {
			$query = $this->wpdb->prepare(
				'DROP TABLE IF EXISTS %s',
				$this->table_name
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->query( $query );

			if ( $this->wpdb->last_error ) {
				$this->last_error = $this->wpdb->last_error;
				$this->log_error( "Error dropping table: {$this->last_error}" );

				return false;
			}

			return true;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			$this->log_error( "Exception dropping table: {$e->getMessage()}" );

			return false;
		}
	}
}
