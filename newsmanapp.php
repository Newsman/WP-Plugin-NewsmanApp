<?php
/**
 * Plugin Name: NewsmanApp for WordPress
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Description: NewsmanApp for WordPress (sign up widget, subscribers sync, create and send newsletters from blog posts)
 * Version: 3.0.0
 * Author: Newsman
 * Author URI: https://www.newsman.com
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NEWSMAN_VERSION', '3.0.0' );

// Included before autoload.php and checks for dependencies in vendor.
require_once __DIR__ . '/includes/class-newsmanphp.php';

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'all_admin_notices', '\Newsman\NewsmanPhp::notify_missing_vendor_composer' );
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

// For single site and per-site activation.
register_activation_hook( __FILE__, array( '\Newsman\Setup', 'on_activation' ) );
add_action( 'upgrader_process_complete', array( '\Newsman\Setup', 'on_upgrade' ), 10, 2 );

// For network-wide activation.
add_action(
	'wpmu_new_blog',
	function ( $blog_id ) {
		switch_to_blog( $blog_id );
		\Newsman\Setup::on_activation();
		restore_current_blog();
	}
);

/**
 * Newsman WP main class
 */
class WP_Newsman {
	/**
	 * Plugin path relative to plugins directory
	 */
	public const NZ_PLUGIN_PATH = 'newsmanapp/newsmanapp.php';

	/**
	 * Default lazy load plugin priority
	 */
	public const PLUGIN_PRIORITY_LAZY_LOAD = 20;

	/**
	 * Newsman config
	 *
	 * @var \Newsman\Config
	 */
	protected $config;

	/**
	 * Array containing the names of the html files found in the templates directory.
	 * (as defined by the templates_dir constant).
	 *
	 * @var array
	 */
	public $templates = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->config = \Newsman\Config::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self WP_Newsman
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new WP_Newsman();
		}

		return $instance;
	}

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded_lazy' ), $this->config->get_plugin_lazy_priority() );
		add_action( 'init', array( new \Newsman\Export\Router(), 'execute' ) );
		// Widget auto init.
		add_action( 'init', array( $this, 'init_widgets' ) );
		// Deactivate old Remarketing plugin.
		add_action( 'admin_init', '\Newsman\Util\DeprecatedRemarketing::notify_and_deactivate_old_plugin' );
		if ( class_exists( '\WC_Newsman_Remarketing' ) ) {
			add_action( 'all_admin_notices', '\Newsman\Util\DeprecatedRemarketing::notify_old_plugin_exist' );
		}

		/**
		 * Declare compatibility with custom_order_tables.
		 *
		 * @return void
		 */
		function before_woocommerce_hpos() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					__FILE__,
					true
				);
			}
		}
		add_action( 'before_woocommerce_init', 'before_woocommerce_hpos' );

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
	 * Init Newsman plugin after most of other plugins are loaded.
	 *
	 * @return void
	 */
	public function plugins_loaded_lazy() {
		if ( class_exists( 'WC_Logger' ) ) {
			\Newsman\Logger::$is_wc_logging = true;
		}

		$exist = new \Newsman\Util\WooCommerceExist();
		if ( $exist->exist() ) {
			$remarketing_config = \Newsman\Remarketing\Config::init();
			if ( $remarketing_config->is_active() ) {
				$remarketing = \Newsman\Remarketing::init();
				$remarketing->init_hooks();
			}
		} else {
			// Enqueue Newsman tracking script when there is WordPress without Woo Commerce.
			add_action( 'wp_head', array( new \Newsman\Remarketing\Script\Track(), 'display_script' ) );

			// Event tracking code in footer of the page.
			add_action( 'wp_footer', array( new \Newsman\Remarketing\Action\IdentifySubscriber(), 'get_script_js' ) );
			$page_view = new \Newsman\Remarketing\Action\PageView();
			$page_view->set_data( array( \Newsman\Remarketing\Action\PageView::MARK_PAGE_VIEW_SENT_FLAG => true ) );
			add_action( 'wp_footer', array( $page_view, 'get_script_js' ) );
		}
	}

	/**
	 * Init widgets, add shortcode.
	 *
	 * @return void
	 */
	public function init_widgets() {
		add_shortcode( 'newsman_subscribe_widget', array( \Newsman\Form\Widget::init(), 'generate' ) );
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
			plugin_dir_url( __FILE__ ) . 'src/img/newsman-mini.png'
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
	}
}

$wp_newsman = WP_Newsman::init();
$wp_newsman->init_hooks();
