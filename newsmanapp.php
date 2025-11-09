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
define( 'NEWSMAN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEWSMAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NEWSMAN_JS_SCRIPT_VERSION', '20251107010000' );

// Included before autoload.php and checks for dependencies in vendor.
require_once __DIR__ . '/includes/class-newsmanphp.php';

// If there isn't already in place no autoload with composer.
if ( ! ( class_exists( 'Newsman\Admin' ) && class_exists( 'Newsman\Remarketing' ) && class_exists( 'Newsman\Config' ) ) ) {
	// Composer autoload from newsmanapp/vendor/ .
	if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		add_action( 'all_admin_notices', '\Newsman\NewsmanPhp::notify_missing_vendor_composer' );
		return;
	}

	require_once __DIR__ . '/vendor/autoload.php';
}

if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

// For single site and per-site activation.
register_activation_hook( __FILE__, array( '\Newsman\Setup', 'on_activation' ) );
add_action( 'upgrader_process_complete', array( '\Newsman\Setup', 'on_upgrade' ), 10, 2 );
register_uninstall_hook( __FILE__, array( '\Newsman\Setup', 'on_uninstall' ) );
register_deactivation_hook( __FILE__, array( '\Newsman\Setup', 'on_deactivate' ) );

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
		$this->check_setup_not_run();
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

		$admin = \Newsman\Admin::init();
		$admin->init_hooks();

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

			$this->init_scheduled_hooks();
		} else {
			// Enqueue Newsman tracking script when there is WordPress without Woo Commerce.
			add_action( 'wp_head', array( new \Newsman\Remarketing\Script\Track(), 'display_script' ) );

			// Event tracking code in footer of the page.
			add_action( 'wp_footer', array( new \Newsman\Remarketing\Action\IdentifySubscriber(), 'display_script_js' ) );
			$page_view = new \Newsman\Remarketing\Action\PageView();
			$page_view->set_data( array( \Newsman\Remarketing\Action\PageView::MARK_PAGE_VIEW_SENT_FLAG => true ) );
			add_action( 'wp_footer', array( $page_view, 'display_script_js' ) );
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
	 * Init Action Scheduler hooks.
	 *
	 * @return void
	 */
	public function init_scheduled_hooks() {
		foreach ( $this->get_known_scheduled_classes() as $class ) {
			if ( method_exists( $class, 'init_hooks' ) ) {
				$scheduled_class = new $class();
				$scheduled_class->init_hooks();
			}
		}
	}

	/**
	 * Get known action scheduler classes.
	 *
	 * @return array
	 */
	public function get_known_scheduled_classes() {
		$classes = array(
			'\Newsman\Scheduler\Export\Recurring\Orders',
			'\Newsman\Scheduler\Export\Orders',
			'\Newsman\Scheduler\Export\Recurring\SubscribersWordpress',
			'\Newsman\Scheduler\Export\SubscribersWordpress',
			'\Newsman\Scheduler\Export\Recurring\SubscribersWoocommerce',
			'\Newsman\Scheduler\Export\SubscribersWoocommerce',
		);

		return apply_filters( 'newsman_known_scheduled_classes', $classes );
	}

	/**
	 * Verify that the setup was run at least one time.
	 * This can happen when the plugin is installed or updated with various tools outside WP admin.
	 *
	 * @return void
	 */
	public function check_setup_not_run() {
		if ( class_exists( 'Newsman\Setup' ) ) {
			\Newsman\Setup::one_time_setup();
		}
	}
}

\WP_Newsman::init()->init_hooks();
