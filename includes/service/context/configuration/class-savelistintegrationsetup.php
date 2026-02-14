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

namespace Newsman\Service\Context\Configuration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service Context Configuration SaveListIntegrationSetup
 *
 * @class \Newsman\Service\Context\Configuration\SaveListIntegrationSetup
 */
class SaveListIntegrationSetup extends EmailList {
	/**
	 * Integration name
	 *
	 * @var string
	 */
	protected $integration = 'wordpress'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- API value.

	/**
	 * Payload key-value pairs
	 *
	 * @var array
	 */
	protected $payload = array();

	/**
	 * Set integration name
	 *
	 * @param string $integration Integration name.
	 * @return $this
	 */
	public function set_integration( $integration ) {
		$this->integration = $integration;
		return $this;
	}

	/**
	 * Get integration name
	 *
	 * @return string
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * Set payload
	 *
	 * @param array $payload Payload key-value pairs.
	 * @return $this
	 */
	public function set_payload( array $payload ) {
		$this->payload = $payload;
		return $this;
	}

	/**
	 * Get payload
	 *
	 * @return array
	 */
	public function get_payload() {
		return $this->payload;
	}
}
