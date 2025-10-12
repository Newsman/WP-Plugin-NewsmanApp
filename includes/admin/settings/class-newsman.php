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
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend.php';
	}
}
