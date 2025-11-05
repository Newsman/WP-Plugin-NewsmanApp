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
 * Newsman Admin
 * Handle admin actions, configurations and pages.
 *
 * @class   \Newsman\Admin
 */
class Admin {
	/**
	 * Get class instance
	 *
	 * @return self Admin
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Admin();
		}

		return $instance;
	}

	/**
	 * Init WordPress and Woo Commerce hooks
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Deactivate old Remarketing plugin.
		add_action( 'admin_init', '\Newsman\Util\DeprecatedRemarketing::notify_and_deactivate_old_plugin' );
		if ( class_exists( '\WC_Newsman_Remarketing' ) ) {
			add_action( 'all_admin_notices', '\Newsman\Util\DeprecatedRemarketing::notify_old_plugin_exist' );
		}

		$admin_router = \Newsman\Admin\Router::init();
		$admin_router->init_hooks();
		add_action( 'admin_init', array( $admin_router, 'execute' ) );

		// Admin menu hook.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		// Add links to plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
		// Enqueue plugin styles in admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		// Enqueue plugin scripts in admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_plugin_scripts' ) );
	}

	/**
	 * Adds a menu item for Newsman on the Admin page
	 *
	 * @return void
	 */
	public function admin_menu() {
		$exist = new \Newsman\Util\WooCommerceExist();

		add_menu_page(
			'Newsman',
			'Newsman',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'Newsman',
			array( new \Newsman\Admin\Settings\Newsman(), 'include_page' ),
			NEWSMAN_PLUGIN_URL . 'src/img/newsman-mini.png'
		);

		add_submenu_page(
			'Newsman',
			'Sync',
			'Sync',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanSync',
			array( new \Newsman\Admin\Settings\Sync(), 'include_page' )
		);

		add_submenu_page(
			'Newsman',
			'Remarketing',
			'Remarketing',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanRemarketing',
			array( new \Newsman\Admin\Settings\Remarketing(), 'include_page' )
		);

		if ( $exist->exist() ) {
			add_submenu_page(
				'Newsman',
				'SMS',
				'SMS',
				'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
				'NewsmanSMS',
				array( new \Newsman\Admin\Settings\Sms(), 'include_page' )
			);
		}

		add_submenu_page(
			'Newsman',
			'Settings',
			'Settings',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanSettings',
			array( new \Newsman\Admin\Settings\Settings(), 'include_page' )
		);

		add_submenu_page(
			'Newsman',
			'Oauth',
			'Oauth',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanOauth',
			array( new \Newsman\Admin\Settings\Oauth(), 'include_page' )
		);
	}

	/**
	 * Binds the Newsman menu item to the menu.
	 *
	 * @param array $links Array with links.
	 * @return array
	 */
	public function plugin_links( $links ) {
		$custom_links = array(
			'<a href="' . admin_url( 'admin.php?page=NewsmanSettings' ) . '">Settings</a>',
		);
		return array_merge( $links, $custom_links );
	}

	/**
	 * Register plugin custom css.
	 *
	 * @return void
	 */
	public function register_plugin_styles() {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_style( 'newsman_css', plugins_url( 'newsmanapp/src/css/style.css' ) );
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'newsman_css' );
	}

	/**
	 * Register plugin custom javascript..
	 *
	 * @return void
	 */
	public function register_plugin_scripts() {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NotInFooter
		wp_register_script( 'newsman_js', plugins_url( 'newsmanapp/src/js/script.js' ), array( 'jquery' ) );
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'newsman_js' );

        wp_localize_script( 'newsman_js', 'NEWSMAN_URLS', array( 'admin_url' => admin_url() ) );
    }
}
