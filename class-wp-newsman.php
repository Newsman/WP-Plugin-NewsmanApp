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
require_once __DIR__ . '/includes/class-wp-newsman-php.php';

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'all_admin_notices', 'WP_Newsman_PHP::vendor_check_and_notify' );
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

register_activation_hook( __FILE__, array( 'Newsman_Setup', 'on_activation' ) );

/**
 * Newsman WP main class
 */
class WP_Newsman {
	/**
	 * Newsman config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * Newsman logger
	 *
	 * @var Newsman_WC_Logger
	 */
	protected $logger;

	/**
	 * First element in array is the type of message (success or error).
	 * Second element in array is the message string.
	 *
	 * @var array
	 */
	public $message;

	/**
	 * Array containing the names of the html files found in the templates directory.
	 * (as defined by the templates_dir constant).
	 *
	 * @var array
	 */
	public $templates = array();

	/**
	 * Retargeting JS endpoint
	 *
	 * @var string
	 */
	public static $endpoint = 'https://retargeting.newsmanapp.com/js/retargeting/track.js';

	/**
	 * Newsman endpoint host.
	 *
	 * @var string
	 */
	public static $endpoint_host = 'https://retargeting.newsmanapp.com';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->config = Newsman_Config::init();
		$this->logger = Newsman_WC_Logger::init();
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
	 * Encode array or object and set headers.
	 *
	 * @param array|Object $obj Array or object to encode.
	 * @return void
	 */
	public function display_json_as_page( $obj ) {
		// Prevent WordPress from loading the theme.
		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', false );
		}

		header( 'Content-Type: application/json' );

		// Disable caching.
		nocache_headers();

		echo wp_json_encode( $obj );
		exit( 0 );
	}

	/**
	 * Export data to newsman action.
	 *
	 * @return void
	 * @throws Exception Throws standard exception on errors.
	 */
	public function newsman_export_data() {
		$export_request = new Newsman_Export_Request();
		if ( ! $export_request->is_export_request() ) {
			return;
		}

		if ( ! $this->config->is_enabled_with_api() ) {
			$result = array(
				'status'  => 403,
				'message' => 'API setting is not enabled in plugin',
			);
			$this->display_json_as_page( $result );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json( array( 'error' => 'WooCommerce is not installed' ) );
		}

		try {
			$parameters = $export_request->get_request_parameters();
			$processor  = new Newsman_Export_Retriever_Processor();
			$result     = $processor->process(
				$processor->get_code_by_data( $parameters ),
				get_current_blog_id(),
				$parameters
			);

			$this->display_json_as_page( $result );
		} catch ( \OutOfBoundsException $e ) {
			$this->logger->log_exception( $e );
			$result = array(
				'status'  => 403,
				'message' => $e->getMessage(),
			);

			$this->display_json_as_page( $result );
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// wp_die('Access Forbidden', 'Forbidden', array('response' => 403)); .
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			$result = array(
				'status'  => 0,
				'message' => $e->getMessage(),
			);

			$this->display_json_as_page( $result );
		}
	}

	/**
	 * Send to Newsman order status pending.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function pending( $order_id ) {
		$this->save_order_newsman( $order_id, 'pending' );
	}

	/**
	 * Send to Newsman order status failed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function failed( $order_id ) {
		$this->save_order_newsman( $order_id, 'failed' );
	}

	/**
	 * Send to Newsman order status on hold.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function hold( $order_id ) {
		$this->save_order_newsman( $order_id, 'on-hold' );
	}

	/**
	 * Send to Newsman order status processing.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function processing( $order_id ) {
		$this->save_order_newsman( $order_id, 'processing' );
	}

	/**
	 * Send to Newsman order status completed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function completed( $order_id ) {
		$this->save_order_newsman( $order_id, 'completed' );
	}

	/**
	 * Send to Newsman order status refunded.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function refunded( $order_id ) {
		$this->save_order_newsman( $order_id, 'refunded' );
	}

	/**
	 * Send to Newsman order status canceled.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function cancelled( $order_id ) {
		$this->save_order_newsman( $order_id, 'cancelled' );
	}

	/**
	 * Send to Newsman order status send order status to Newsman.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return void
	 */
	public function save_order_newsman( $order_id, $status ) {

		$newsman_usesms    = get_option( 'newsman_usesms' );
		$newsman_smslist   = get_option( 'newsman_smslist' );
		$newsman_smstest   = get_option( 'newsman_smstest' );
		$newsman_smstestnr = get_option( 'newsman_smstestnr' );

		$send_sms        = false;
		$newsman_smstext = '';

		$newsman_smspending = get_option( 'newsman_smspendingactivate' );
		if ( 'pending' === $status && 'on' === $newsman_smspending ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smspendingtext' );
		}
		$newsman_smsfailed = get_option( 'newsman_smsfailedactivate' );
		if ( 'failed' === $status && 'on' === $newsman_smsfailed ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsfailedtext' );
		}
		$newsman_smsonhold = get_option( 'newsman_smsonholdactivate' );
		if ( 'on-hold' === $status && 'on' === $newsman_smsonhold ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsonholdtext' );
		}
		$newsman_smsprocessing = get_option( 'newsman_smsprocessingactivate' );
		if ( 'processing' === $status && 'on' === $newsman_smsprocessing ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsprocessingtext' );
		}
		$newsman_smscompleted = get_option( 'newsman_smscompletedactivate' );
		if ( 'completed' === $status && 'on' === $newsman_smscompleted ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smscompletedtext' );
		}
		$newsman_smsrefunded = get_option( 'newsman_smsrefundedactivate' );
		if ( 'refunded' === $status && 'on' === $newsman_smsrefunded ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsrefundedtext' );
		}
		$newsman_smscancelled = get_option( 'newsman_smscancelledactivate' );
		if ( 'cancelled' === $status && 'on' === $newsman_smscancelled ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smscancelledtext' );
		}

		if ( $send_sms ) {
			try {
				if ( ! empty( $newsman_usesms ) && 'on' === $newsman_usesms && ! empty( $newsman_smslist ) ) {
					$order     = wc_get_order( $order_id );
					$item_data = $order->get_data();

					$date = $order->get_date_created()->date( 'F j, Y' );

					$newsman_smstext = str_replace( '{{billing_first_name}}', $item_data['billing']['first_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{billing_last_name}}', $item_data['billing']['last_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{shipping_first_name}}', $item_data['shipping']['first_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{shipping_last_name}}', $item_data['shipping']['last_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{email}}', $item_data['billing']['email'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_number}}', $item_data['id'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_date}}', $date, $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_total}}', $item_data['total'], $newsman_smstext );
					$phone           = '4' . $item_data['billing']['phone'];

					if ( $newsman_smstest ) {
						$phone = '4' . $newsman_smstestnr;
					}

					$context = new Newsman_Service_Context_Sms_SendOne();
					$context->set_list_id( $newsman_smslist )
						->set_text( $newsman_smstext )
						->set_to( $phone );
					$send_one = new Newsman_Service_Sms_SendOne();
					$send_one->execute( $context );
				}
			} catch ( Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$this->logger->log_exception( $e );
			}
		}

		$list_id = get_option( 'newsman_remarketingid' );
		$list_id = explode( '-', $list_id );
		$list_id = $list_id[1];

		$url = 'https://ssl.newsman.app/api/1.2/rest/' . $this->config->get_user_id() . '/'
			. $this->config->get_api_key() . '/remarketing.setPurchaseStatus.json?list_id='
			. $list_id . '&order_id=' . $order_id . '&status=' . $status;

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array()
		);
	}

	/**
	 * Init Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded_lazy' ), 20 );
		add_action( 'init', array( $this, 'newsman_export_data' ) );
		add_action( 'woocommerce_review_order_before_submit', array( new Newsman_Form_Checkout_Checkbox(), 'add_field' ) );
		add_action( 'woocommerce_checkout_order_processed', array( new Newsman_Form_Checkout_Processor(), 'process' ), 10, 2 );

		// Order status change hooks.
		add_action( 'woocommerce_order_status_pending', array( $this, 'pending' ) );
		add_action( 'woocommerce_order_status_failed', array( $this, 'failed' ) );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'hold' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'processing' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'completed' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'refunded' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancelled' ) );
		add_action( 'before_woocommerce_init', 'before_woocommerce_hpos' );

		/**
		 * Declare compatibility with feature "custom_order_tables".
		 *
		 * @return void
		 */
		function before_woocommerce_hpos() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
		// Admin menu hook.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		// Add links to plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
		// Enqueue plugin styles in admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		// Enqueue WordPress ajax library.
		add_action( 'wp_head', array( $this, 'add_ajax_library' ) );
		// Enqueue plugin scripts.
		// add_action('wp_enqueue_scripts', array($this, 'register_plugin_scripts'));
		// Enqueue plugin scripts in admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_plugin_scripts' ) );
		// Do ajax form subscribe.
		add_action( 'wp_ajax_nopriv_newsman_ajax_subscribe', array( Newsman_Form_AjaxSubscribe::init(), 'subscribe' ) );
		add_action( 'wp_ajax_newsman_ajax_subscribe', array( Newsman_Form_AjaxSubscribe::init(), 'subscribe' ) );
		// Check if plugin is active.
		add_action( 'wp_ajax_newsman_ajax_check_plugin', array( $this, 'newsman_ajax_check_plugin' ) );
		// Widget auto init.
		add_action( 'init', array( $this, 'init_widgets' ) );
	}

	/**
	 * Init Newsman plugin after most of other plugins are loaded.
	 *
	 * @return void
	 */
	public function plugins_loaded_lazy() {
		if ( class_exists( 'WC_Logger' ) ) {
			Newsman_Wc_Logger::$is_wc_logging = true;
		}
	}

	/**
	 * Init widgets, add shortcode.
	 *
	 * @return void
	 */
	public function init_widgets() {
		add_shortcode( 'newsman_subscribe_widget', array( Newsman_Form_Widget::init(), 'generate' ) );
	}

	/**
	 * Adds a menu item for Newsman on the Admin page
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_menu_page(
			'Newsman',
			'Newsman',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'Newsman',
			array( new Newsman_Admin_Settings_Newsman(), 'include_page' ),
			plugin_dir_url( __FILE__ ) . 'src/img/newsman-mini.png'
		);

		add_submenu_page(
			'Newsman',
			'Sync',
			'Sync',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanSync',
			array( new Newsman_Admin_Settings_Sync(), 'include_page' )
		);

		add_submenu_page(
			'Newsman',
			'Remarketing',
			'Remarketing',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanRemarketing',
			array( new Newsman_Admin_Settings_Remarketing(), 'include_page' )
		);

		add_submenu_page(
			'Newsman',
			'SMS',
			'SMS',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanSMS',
			array( new Newsman_Admin_Settings_Sms(), 'include_page' )
		);

		add_submenu_page(
			'Newsman',
			'Settings',
			'Settings',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanSettings',
			array( new Newsman_Admin_Settings_Settings(), 'include_page' )
		);

		add_submenu_page(
			'Newsman',
			'Oauth',
			'Oauth',
			'administrator', // phpcs:ignore WordPress.WP.Capabilities.RoleFound
			'NewsmanOauth',
			array( new Newsman_Admin_Settings_Oauth(), 'include_page' )
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

	/**
	 * Includes ajax library that WordPress uses for processing ajax requests.
	 *
	 * @return void
	 */
	public function add_ajax_library() {
		$html  = '<script type="text/javascript">';
		$html .= 'var ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '"';
		$html .= '</script>';

		if ( ! class_exists( 'WooCommerce' ) ) {
			$remarketingid = get_option( 'newsman_remarketingid' );
			if ( ! empty( $remarketingid ) ) {
				$html .= "
                    <script type='text/javascript'>
                    var _nzm = _nzm || []; var _nzm_config = _nzm_config || []; _nzm_tracking_server = '" . self::$endpoint_host . "';
                    (function() {var a, methods, i;a = function(f) {return function() {_nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
                    }};methods = ['identify', 'track', 'run'];for(i = 0; i < methods.length; i++) {_nzm[methods[i]] = a(methods[i])};
                    s = document.getElementsByTagName('script')[0];var script_dom = document.createElement('script');script_dom.async = true;
                    script_dom.id = 'nzm-tracker';script_dom.setAttribute('data-site-id', '" . esc_js( $remarketingid ) . "');
                    script_dom.src = '" . self::$endpoint . "';s.parentNode.insertBefore(script_dom, s);})();
                    </script>
                    ";
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}
}

$wp_newsman = WP_Newsman::init();
$wp_newsman->init_hooks();
