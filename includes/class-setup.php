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
 * Activate, deactivate and uninstall plugin.
 *
 * @class Newsman_Setup
 */
class Setup {
	/**
	 * Current version of setup in database
	 *
	 * @var string|null
	 */
	protected static $current_version;

	/**
	 * On activate plugin
	 *
	 * @param string|false $network_wide Network wide.
	 * @return void
	 */
	public static function on_activation( $network_wide = false ) {
		// phpcs:ignore Generic.ControlStructures.InlineControlStructure.NotAllowed
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		check_admin_referer( 'activate-plugin_' . $plugin );

		self::setup( $network_wide );
	}

	/**
	 * On upgrade plugin
	 *
	 * @param \WP_Upgrader $upgrader Network wide.
	 * @param array        $options Upgrader options.
	 * @return void
	 */
	public static function on_upgrade( $upgrader, $options ) {
		// phpcs:ignore Generic.ControlStructures.InlineControlStructure.NotAllowed
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$update = false;
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] && isset( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( stripos( $plugin, \WP_Newsman::NZ_PLUGIN_PATH ) !== false ) {
					$update = true;
					break;
				}
			}
		}
		if ( ! $update ) {
			return;
		}

		// Determine if this is a network-wide update.
		$network_wide = is_multisite() && isset( $options['network_wide'] ) && $options['network_wide'];

		self::setup( $network_wide );
	}

	/**
	 * Plugin uninstallation handler
	 *
	 * @return void
	 */
	public static function on_uninstall() {
		self::remove_scheduled_action();
	}

	/**
	 * Plugin deactivate handler
	 *
	 * @return void
	 */
	public static function on_deactivate() {
		self::remove_scheduled_action();
	}

	/**
	 * One time setup to make sure the initial setup was run.
	 * This can happen when the plugin is installed or updated with various tools outside WP admin.
	 *
	 * @return void
	 */
	public static function one_time_setup() {
		if ( ! empty( self::get_current_version() ) ) {
			return;
		}
		$network_wide = is_multisite() && isset( $options['network_wide'] ) && $options['network_wide'];
		self::setup( $network_wide );
	}

	/**
	 * Perform setup install or upgrade.
	 *
	 * @param bool $network_wide Is network wide.
	 * @return void
	 */
	protected static function setup( $network_wide = false ) {
		self::$current_version = self::get_current_version();

		if ( is_multisite() && $network_wide ) {
			// Network activation - run for each site.
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::create_tables();
				self::upgrade_newsman_options();
				self::upgrade_rewrites();
				self::upgrade_options();
				restore_current_blog();
			}
		} else {
			// Single site activation.
			self::create_tables();
			self::upgrade_newsman_options();
			self::upgrade_rewrites();
			self::upgrade_options();
		}
	}

	/**
	 * Create plugin tables
	 *
	 * @return void
	 */
	protected static function create_tables() {
		if ( version_compare( self::$current_version, '1.0.0', '<' ) ) {
			self::create_tables_one_zero_zero();
		}
	}

	/**
	 * Create plugin tables 1.0.0
	 *
	 * @return void
	 */
	protected static function create_tables_one_zero_zero() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Get the correct table prefix for the current site.
		$table_name = self::get_current_site_prefix() . 'newsman_options';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
            option_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            option_name varchar(191) NOT NULL DEFAULT '',
            option_value longtext NOT NULL,
            autoload varchar(20) NOT NULL DEFAULT 'on',
            PRIMARY KEY  (option_id),
            UNIQUE KEY option_name (option_name),
            KEY autoload (autoload)
    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Upgrade admin options
	 *
	 * @return void
	 */
	protected static function upgrade_newsman_options() {
		if ( version_compare( self::$current_version, '1.0.0', '<' ) ) {
			self::upgrade_newsman_options_one_zero_zero();
		}

		// Hotfix in 3.0.1 .
		if ( version_compare( self::$current_version, '2.0.0', '<' ) ) {
			// Run 1.0.0 again as a hotfix to a bug fixed here.
			self::upgrade_newsman_options_one_zero_zero();
		}
	}

	/**
	 * Upgrade admin options 1.0.0
	 *
	 * @return void
	 */
	protected static function upgrade_newsman_options_one_zero_zero() {
		$options = new \Newsman\Options();
		$options->add_option(
			'newsman_api',
			'https://ssl.newsman.app/api/'
		);
		$options->add_option(
			'newsman_trackingscripturl',
			'https://retargeting.newsmanapp.com/js/retargeting/track.js'
		);
		$options->add_option(
			'newsman_httpresourceurl',
			'https://retargeting.newsmanapp.com/'
		);
		$options->add_option(
			'newsman_httptrackingurl',
			'https://rtrack.newsmanapp.com/'
		);

		$options->add_option(
			'newsman_httprequiredfilepatterns',
			'js/retargeting/track.js
js/retargeting/nzm_custom_{{api_key}}.js
js/retargeting/ecommerce.js
js/retargeting/modal_{{api_key}}.js'
		);

		$options->add_option(
			'newsman_scriptjs',
			"var _nzm = _nzm || [],
    _nzm_config = _nzm_config || [];

{{nzmConfigJs}}

(function(w, d, e, u, f, c, l, n, a, m) {
    w[f] = w[f] || [],
    w[c] = w[c] || [],
    a=function(x) {
        return function() {
            w[f].push([x].concat(Array.prototype.slice.call(arguments, 0)));
        }
    },
    m = [\"identify\", \"track\", \"run\"];
    if ({{conditionTunnelScript}}) {
        w[c].js_prefix = '{{resourcesBaseUrl}}';
        w[c].tr_prefix = '{{trackingBaseUrl}}';
    }
    for(var i = 0; i < m.length; i++) {
        w[f][m[i]] = a(m[i]);
    }
    l = d.createElement(e),
    l.async = 1,
    l.src = u,
    l.id=\"nzm-tracker\",
    l.setAttribute(\"data-site-id\", '{{remarketingId}}'),
    n = d.getElementsByTagName(e)[0],
    n.parentNode.insertBefore(l, n);

})(window, document, 'script', '{{trackingScriptUrl}}', '_nzm', '_nzm_config');"
		);

		$options->add_option(
			'newsman_jstrackrunfunc',
			'_nzm.run'
		);
	}

	/**
	 * Run upgrade rewrites on all version
	 *
	 * @return void
	 */
	public static function upgrade_rewrites() {
		if ( version_compare( self::$current_version, '3.0.0', '<' ) ) {
			self::upgrade_rewrites_three_zero_zero();
		}
	}

	/**
	 * Upgrade rewrites 3.0.0
	 *
	 * @return void
	 */
	public static function upgrade_rewrites_three_zero_zero() {
		$account_processor = new \Newsman\Form\Account\Processor();
		$account_processor->add_endpoint();
		flush_rewrite_rules();
	}

	/**
	 * Upgrade admin options for the first time with add_option insert only function.
	 *
	 * @note This function should be run last because newsman_setup_version option is updated.
	 * @return void
	 */
	protected static function upgrade_options() {
		if ( version_compare( self::$current_version, '1.0.0', '<' ) ) {
			self::upgrade_options_one_zero_zero();
			update_option( 'newsman_setup_version', '1.0.0', true );
		}

		if ( version_compare( self::$current_version, '2.0.0', '<' ) ) {
			update_option( 'newsman_setup_version', '2.0.0', true );
		}

		if ( version_compare( self::$current_version, '3.0.0', '<' ) ) {
			self::upgrade_options_three_zero_zero();
			update_option( 'newsman_setup_version', '3.0.0', true );
		}
	}

	/**
	 * Version 1.0.0 options update
	 *
	 * @return void
	 */
	protected static function upgrade_options_one_zero_zero() {
		add_option( 'newsman_api', 'on' );
		add_option( 'newsman_useremarketing', 'on' );
		add_option( 'newsman_remarketingsendtelephone', 'on' );
		add_option(
			'newsman_remarketingordersave',
			array(
				'wc-completed',
				'wc-processing',
				'wc-on-hold',
				'wc-cancelled',
				'wc-refunded',
				'wc-failed',
			)
		);
		add_option( 'newsman_checkoutnewsletter', 'on' );
		add_option( 'newsman_senduserip', 'on' );
		add_option( 'newsman_developeractiveuserip', '' );
		add_option( 'newsman_developeruserip', '' );
		add_option( 'newsman_developerpluginlazypriority', \WP_Newsman::PLUGIN_PRIORITY_LAZY_LOAD );
		add_option( 'newsman_developer_use_action_scheduler', 'on' );
		add_option( 'newsman_developer_use_as_subscribe', 'on' );
		add_option( 'newsman_remarketingexportorders', 'on' );

		add_option( 'newsman_remarketingexportwordpresssubscribers_recurring_short_days', '7' );
		add_option( 'newsman_remarketingexportwordpresssubscribers_recurring_long_days', '90' );
		add_option( 'newsman_remarketingexportwoocommercesubscribers_recurring_short_days', '7' );
		add_option( 'newsman_remarketingexportwoocommercesubscribers_recurring_long_days', '90' );
		add_option( 'newsman_remarketingexportorders_recurring_short_days', '7' );
		add_option( 'newsman_remarketingexportorders_recurring_long_days', '90' );

		$current_date = new \DateTime();
		$current_date->modify( '-5 years' );
		add_option( 'newsman_remarketingorderdate', $current_date->format( 'Y-m-d' ) );
	}

	/**
	 * Version 3.0.0 options update
	 *
	 * @return void
	 */
	protected static function upgrade_options_three_zero_zero() {
		add_option( 'newsman_checkoutnewslettermessage', 'Subscribe to our newsletter' );

		add_option( 'newsman_myaccountnewsletter', 'on' );
		add_option( 'newsman_myaccountnewsletter_menu_label', 'Newsletter' );
		add_option( 'newsman_myaccountnewsletter_page_title', 'Newsletter Subscription' );
		add_option( 'newsman_myaccountnewsletter_checkbox_label', 'Subscribe to our newsletter' );

		add_option( 'newsman_checkout_order_status', 'on' );
		add_option( 'newsman_checkout_order_status_label', 'I want to receive notifications about the status of my order on my phone (SMS messages)' );

		$deprecated = get_option( 'newsman_checkoutnewslettertype' );
		if ( ! empty( $deprecated ) ) {
			add_option( 'newsman_newslettertype', $deprecated );
		} else {
			add_option( 'newsman_newslettertype', 'save' );
		}
	}

	/**
	 * Get current site prefix
	 *
	 * @return string
	 */
	protected static function get_current_site_prefix() {
		global $wpdb;

		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
			return $wpdb->get_blog_prefix( $blog_id );
		}

		return $wpdb->prefix;
	}

	/**
	 * Remove scheduled actions
	 *
	 * @return void
	 */
	public static function remove_scheduled_action() {
		if ( ! ( class_exists( \ActionScheduler::class ) &&
			\ActionScheduler::is_initialized() &&
			function_exists( 'as_unschedule_all_actions' )
		) ) {
			return;
		}

		$hooks = array(
			'newsman_recurring_export_orders_short',
			'newsman_recurring_export_orders_long',
			'newsman_export_orders',
			'newsman_recurring_export_woocommerce_subscribers_short',
			'newsman_recurring_export_woocommerce_subscribers_long',
			'newsman_export_woocommerce_subscribers',
			'newsman_recurring_export_wordpress_subscribers_short',
			'newsman_recurring_export_wordpress_subscribers_long',
			'newsman_export_wordpress_subscribers',
			'newsman_order_save',
			'newsman_order_notify_sms',
			'newsman_order_notify_status',
			'newsman_subscribe_email',
			'newsman_subscribe_phone',
		);
		foreach ( $hooks as $hook ) {
			as_unschedule_all_actions( $hook );
		}
	}

	/**
	 * Get current version of setup from wp_options table.
	 *
	 * @return false|mixed|null
	 */
	public static function get_current_version() {
		return get_option( 'newsman_setup_version' );
	}
}
