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
 * Admin configuration widget
 *
 * @class Newsman_Admin_Settings_Widget
 */
class Newsman_Admin_Settings_Widget extends Newsman_Admin_Settings {
	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings-widget';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = 'newsman_widget';

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend-widget.php';
	}
}
