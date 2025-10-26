<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman PHP checks class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notify old plugin wc-newsman-remarketing exist.
 *
 * @class \Newsman\Util\DeprecatedRemarketing
 */
class DeprecatedRemarketing {
	/**
	 * Notify old plugin wc-newsman-remarketing exist.
	 *
	 * @return void
	 */
	public static function notify_old_plugin_exist() {
		print( '<div id="newsman-deprecated-remarketing-plugin" class="notice notice-error"><p>Old plugin <strong>NewsmanApp Remarketing</strong> is active. Please <strong>deactivate</strong> it or <strong>delete</strong> it. All features are now in the main NewsmanApp plugin. The old NewsmanApp Remarketing plugin <strong>conflicts</strong> with this version. To prevent errors and data inconsistencies, please deactivate the older plugin.</p></div>' );
	}

	/**
	 * Notify and deactivate old plugin wc-newsman-remarketing if admin is logged in.
	 *
	 * @return void
	 */
	public static function notify_and_deactivate_old_plugin() {
		if ( ! ( is_admin() && current_user_can( 'activate_plugins' ) ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			} else {
				return;
			}
		}

		$plugin_path = 'newsmanapp/wc-newsman-remarketing.php';

		if ( is_plugin_active( $plugin_path ) ) {
			deactivate_plugins( $plugin_path, true );

			add_action(
				'admin_notices',
				function () {
					print( '<div id="newsman-deprecated-remarketing-plugin" class="notice notice-warning"><p>The outdated NewsmanApp Remarketing plugin has been automatically deactivated. All features are now included in the main NewsmanApp plugin.</p></div>' );
				}
			);
		}
	}
}
