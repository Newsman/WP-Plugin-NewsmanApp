<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Main plugin file, renaming would break existing installations.
/**
 * Plugin Name: NewsmanApp
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Description: NewsmanApp for WordPress (sign up widget, subscribers sync, create and send newsletters from blog posts)
 * Version: 3.7.43
 * Author: Newsman
 * Author URI: https://www.newsman.com
 * Text Domain: newsman
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NEWSMAN_VERSION', '3.7.43' );
define( 'NEWSMAN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEWSMAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NEWSMAN_JS_SCRIPT_VERSION', '20261102000000' );
define( 'NEWSMAN_CSS_SCRIPT_VERSION', '20261102120000' );

// Included before autoload.php and checks for dependencies in vendor.
require_once __DIR__ . '/includes/class-newsmanphp.php';

// If there isn't already in place no autoload with composer.
if ( ! ( class_exists( '\Newsman\Admin' ) && class_exists( '\Newsman\Remarketing' ) && class_exists( '\Newsman\Config' ) ) ) {
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

// Network-wide activation: set up each new site of the network. No activation nonce is
// checked here, the request is the one that adds the site.
$newsman_setup_new_site = static function ( $blog_id ) {
	$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
	if ( ! isset( $network_plugins[ plugin_basename( __FILE__ ) ] ) ) {
		return;
	}
	global $wpdb;
	switch_to_blog( (int) $blog_id );
	// The Action Scheduler tables of a new site are created on its first request; the
	// plugin sets the site up on that request then (Setup::is_setup_executed()).
	$as_table = $wpdb->prefix . 'actionscheduler_actions';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check, must not be cached.
	if ( ! class_exists( '\ActionScheduler' ) || $as_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $as_table ) ) ) ) {
		\Newsman\Setup::is_setup_executed();
	}
	restore_current_blog();
};
if ( version_compare( $GLOBALS['wp_version'], '5.1', '>=' ) ) {
	// After WordPress created the tables of the new site (priority 10).
	add_action(
		'wp_initialize_site',
		static function ( $site ) use ( $newsman_setup_new_site ) {
			$newsman_setup_new_site( $site->blog_id );
		},
		100
	);
} else {
	add_action( 'wpmu_new_blog', $newsman_setup_new_site );
}

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
	 * @return self \WP_Newsman
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new \WP_Newsman();
		}

		return $instance;
	}

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded_lazy' ), $this->config->get_plugin_lazy_priority() );
		add_action( 'init', array( new \Newsman\Export\Router(), 'execute' ) );

		// Widget auto init.
		add_action( 'init', array( $this, 'init_widgets' ) );

		// Check URL rewrites endpoints was run.
		add_action( 'init', array( $this, 'check_setup_rewrite_endpoints_run' ) );

		$admin = \Newsman\Admin::init();
		$admin->init_hooks();

		// Tools > Site Health tests and information.
		( new \Newsman\Health\SiteHealth() )->init_hooks();

		// Remarketing scripts check from the NewsMAN admin page.
		( new \Newsman\Remarketing\StorefrontCheck() )->init_hooks();

		// Unsubscribe from NewsMAN on Tools > Erase Personal Data and on deleted users.
		if ( class_exists( '\Newsman\Privacy\Eraser' ) ) {
			( new \Newsman\Privacy\Eraser() )->init_hooks();
		}

		// NewsMAN data in Tools > Export Personal Data and the suggested privacy policy text.
		if ( class_exists( '\Newsman\Privacy\Exporter' ) ) {
			( new \Newsman\Privacy\Exporter() )->init_hooks();
		}

		// The wp newsman commands. WP-CLI fires cli_init, web requests never do.
		add_action( 'cli_init', array( $this, 'register_cli_commands' ) );

		// Declare compatibility with custom_order_tables (HPOS).
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'custom_order_tables',
						__FILE__,
						true
					);
				}
			}
		);
	}

	/**
	 * Register the wp newsman commands.
	 *
	 * WP-CLI ends the whole command with an error when the class of a command cannot
	 * be loaded, so a missing class (a stale Composer classmap) would break every wp
	 * command on the site. Without the class the commands are simply not added.
	 *
	 * @return void
	 */
	public function register_cli_commands() {
		if ( ! class_exists( '\WP_CLI' ) || ! class_exists( '\Newsman\Cli\Command' ) ) {
			return;
		}
		\WP_CLI::add_command( 'newsman', '\Newsman\Cli\Command' );
	}

	/**
	 * Init Newsman plugin after most of other plugins are loaded.
	 *
	 * @return void
	 */
	public function plugins_loaded_lazy() {
		if ( class_exists( '\WC_Logger' ) ) {
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

		$elementor_exist = new \Newsman\Util\ElementorExist();
		if ( $elementor_exist->exist() && $this->config->use_elementor() ) {
			( new \Newsman\Elementor\Integration() )->init_hooks();
		}

		$contact_form_7_exist = new \Newsman\Util\ContactForm7Exist();
		if ( $contact_form_7_exist->exist() && $this->config->use_contact_form_7() ) {
			( new \Newsman\ContactForm7\Integration() )->init_hooks();
		}

		$wpforms_exist = new \Newsman\Util\WPFormsExist();
		if ( $wpforms_exist->exist() && $this->config->use_wpforms() ) {
			( new \Newsman\WPForms\Integration() )->init_hooks();
		}

		$gravity_forms_exist = new \Newsman\Util\GravityFormsExist();
		if ( $gravity_forms_exist->exist() && $this->config->use_gravity_forms() ) {
			( new \Newsman\GravityForms\Integration() )->init_hooks();
		}
	}

	/**
	 * Init widgets, add shortcode.
	 *
	 * @return void
	 */
	public function init_widgets() {
		add_shortcode( 'newsman_subscribe_widget', array( \Newsman\Form\Widget::init(), 'generate' ) );

		// Blocks registered from block.json need WordPress 5.9 (viewScript).
		if ( function_exists( 'register_block_type' ) && version_compare( get_bloginfo( 'version' ), '5.9', '>=' ) ) {
			( new \Newsman\Form\Block\SubscribeForm() )->init_hooks();
			( new \Newsman\Form\Block\EmbeddedForm() )->init_hooks();
		}
		( new \Newsman\Form\Block\SubscribeProcessor() )->init_hooks();
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
			// phpcs:disable Squiz.PHP.CommentedOutCode.Found
			// '\Newsman\Scheduler\Export\Recurring\Orders',
			// phpcs:enable Squiz.PHP.CommentedOutCode.Found
			// '\Newsman\Scheduler\Export\Orders',
			// phpcs:enable Squiz.PHP.CommentedOutCode.Found
			// '\Newsman\Scheduler\Export\Recurring\SubscribersWordpress',
			// phpcs:enable Squiz.PHP.CommentedOutCode.Found
			// '\Newsman\Scheduler\Export\SubscribersWordpress',
			// phpcs:enable Squiz.PHP.CommentedOutCode.Found
			// '\Newsman\Scheduler\Export\Recurring\SubscribersWoocommerce',
			// phpcs:enable Squiz.PHP.CommentedOutCode.Found
			// '\Newsman\Scheduler\Export\SubscribersWoocommerce',
			'\Newsman\Scheduler\Subscribe\Email',
			'\Newsman\Scheduler\Subscribe\Phone',
			'\Newsman\Scheduler\Unsubscribe\Email',
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
		if ( class_exists( '\Newsman\Setup' ) ) {
			\Newsman\Setup::is_setup_executed();
		}
	}

	/**
	 * Verify that the setup was run at least one time.
	 * This can happen when the plugin is installed or updated with various tools outside WP admin.
	 *
	 * @return void
	 */
	public function check_setup_rewrite_endpoints_run() {
		if ( class_exists( '\Newsman\Setup' ) ) {
			\Newsman\Setup::is_myaccount_newsletter_rewrite_endpoint();
		}
	}

	/**
	 * Load translation
	 *
	 * @return void
	 */
	public function load_text_domain() {
		$plugin_rel_path = basename( __DIR__ ) . '/languages';
		load_plugin_textdomain( 'newsman', false, $plugin_rel_path );
	}
}

\WP_Newsman::init()->init_hooks();
