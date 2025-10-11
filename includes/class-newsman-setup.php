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
 * Activate, deactivate and uninstall plugin.
 *
 * @class Newsman_Setup
 */
class Newsman_Setup {
	/**
	 * On activate plugin
	 *
	 * @return void
	 */
	public static function on_activation() {
		// phpcs:ignore Generic.ControlStructures.InlineControlStructure.NotAllowed
		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		check_admin_referer( 'activate-plugin_' . $plugin );

		// Init admin options for the first time with add_option insert only function.
		add_option( 'newsman_api', 'on' );
		add_option( 'newsman_senduserip', 'on' );
		add_option( 'newsman_developeractiveuserip', '' );
		add_option( 'newsman_developeruserip', '' );
	}
}
