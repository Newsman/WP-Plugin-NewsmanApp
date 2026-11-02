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

namespace Newsman\Admin\Settings;

use Newsman\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin configuration Newsman
 *
 * @class \Newsman\Admin\Settings\Newsman
 */
class Newsman extends Settings {
	/**
	 * Health checks shown on the page
	 *
	 * @var \Newsman\Health\Checks|null
	 */
	protected $health_checks;

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend.php';
	}

	/**
	 * Get the health checks shown in the status section of the page.
	 *
	 * @return \Newsman\Health\Checks
	 */
	public function get_health_checks() {
		if ( null === $this->health_checks ) {
			$this->health_checks = new \Newsman\Health\Checks( $this );
		}
		return $this->health_checks;
	}

	/**
	 * Whether the Site Health screen exists and the current user may open it.
	 *
	 * @return bool
	 */
	public function can_view_site_health() {
		return version_compare( get_bloginfo( 'version' ), '5.2', '>=' ) && current_user_can( 'view_site_health_checks' );
	}
}
