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
 * Get user host / server IP address
 *
 * @class Newsman_User_HostIpAddress
 */
class Newsman_User_HostIpAddress {
	/**
	 * Not found value
	 */
	public const NOT_FOUND = 'not found';

	/**
	 * Config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * IP address
	 *
	 * @var string|null
	 */
	protected $ip;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Newsman_Config::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self Newsman_User_HostIpAddress
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Newsman_User_HostIpAddress();
		}

		return $instance;
	}

	/**
	 * Get the host ip address.
	 *
	 * @return string The ip address.
	 */
	public function get_ip() {
		if ( null !== $this->ip ) {
			return $this->ip;
		}

		$ip = $this->config->get_server_ip();
		if ( ! empty( $ip ) ) {
			if ( self::NOT_FOUND === $ip ) {
				$this->ip = '';
			} else {
				$this->ip = $ip;
			}
			return $this->ip;
		}

		$url = $this->get_url();
		$ip  = '';
		if ( false !== $url ) {
			$ip = $this->lookup_ip( $url );
		}

		if ( empty( $ip ) ) {
			$ip = self::NOT_FOUND;
		}

		update_option( 'newsman_serverip', $ip, Newsman_Config::AUTOLOAD_OPTIONS );

		if ( self::NOT_FOUND === $ip ) {
			$this->ip = '';
		} else {
			$this->ip = $ip;
		}

		return $this->ip;
	}

	/**
	 * Fetch am asset URL from current WordPress website to get the server IP address.
	 *
	 * @param string $url URL to fetch.
	 * @return string
	 */
	protected function lookup_ip( $url ) {
		// @codingStandardsIgnoreStart
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_exec( $ch );
		$ip = curl_getinfo ($ch, CURLINFO_PRIMARY_IP );
		curl_close( $ch );
		// @codingStandardsIgnoreEnd

		if ( empty( $ip ) || '127.0.0.1' === $ip ) {
			return '';
		}

		return $ip;
	}

	/**
	 * Get URL to request.
	 *
	 * @return string|false
	 */
	public function get_url() {
		return $this->get_first_file_from_uploads();
	}

	/**
	 * Get the first file (excluding .htaccess and hidden files) from the uploads directory
	 *
	 * @param string $subdir Optional. Subdirectory within uploads to search. Default is empty (root uploads dir).
	 * @return string|false The URL of the first file found, or false if no files were found.
	 */
	protected function get_first_file_from_uploads( $subdir = '' ) {
		// Get the upload directory information.
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];

		// Append subdirectory if provided.
		if ( ! empty( $subdir ) ) {
			$dir_path = trailingslashit( $base_dir ) . trailingslashit( trim( $subdir, '/' ) );
		} else {
			$dir_path = trailingslashit( $base_dir );
		}

		// Check if directory exists.
		if ( ! is_dir( $dir_path ) ) {
			return false;
		}

		// Open the directory.
		$dir = opendir( $dir_path );
		if ( ! $dir ) {
			return false;
		}

		$fail_safe = 0;
		// Loop through directory entries.
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( ( false !== ( $file = readdir( $dir ) ) ) && ( ++$fail_safe < 10 ) ) {
			// Skip directories, .htaccess, and other hidden files.
			if ( is_dir( $dir_path . $file ) || '.htaccess' === $file || '.' === $file
				|| '..' === $file || 0 === strpos( $file, '.' ) ) {
				continue;
			}

			// Found a file, close directory and return its URL.
			closedir( $dir );

			// Convert the file path to URL.
			$file_url = $upload_dir['baseurl'] . ( empty( $subdir ) ? '' : '/' . trim( $subdir, '/' ) ) . '/' . $file;

			return $file_url;
		}

		// No files found, close directory.
		closedir( $dir );
		return false;
	}
}
