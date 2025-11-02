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

namespace Newsman\Service\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service Context Export CSV subscribers
 *
 * @class \Newsman\Service\Context\ExportCsvSubscribers
 */
class ExportCsvSubscribers extends Blog {
	/**
	 * CSV data
	 *
	 * @var array
	 */
	protected $csv_data;

	/**
	 * WP Blog IDs
	 *
	 * @var array
	 */
	protected $blog_ids = array();

	/**
	 * Additional fields
	 *
	 * @var array
	 */
	protected $additional_fields = array();

	/**
	 * Set CSV data
	 *
	 * @param array $data CSV data.
	 * @return $this
	 */
	public function set_csv_data( $data ) {
		$this->csv_data = $data;
		return $this;
	}

	/**
	 * Get CSV data
	 *
	 * @return array
	 */
	public function get_csv_data() {
		return $this->csv_data;
	}

	/**
	 * Set WP blog IDs
	 *
	 * @param array $blog_ids WP blog IDs.
	 * @return $this
	 */
	public function set_blog_ids( $blog_ids ) {
		$this->blog_ids = $blog_ids;
		return $this;
	}

	/**
	 * Get store IDs
	 *
	 * @return array
	 */
	public function get_store_ids() {
		return $this->blog_ids;
	}

	/**
	 * Set additional fields
	 *
	 * @param array $data Additional fields.
	 * @return $this
	 */
	public function set_additional_fields( $data ) {
		$this->additional_fields = $data;
		return $this;
	}

	/**
	 * Get additional fields
	 *
	 * @return array
	 */
	public function get_additional_fields() {
		return $this->additional_fields;
	}
}
