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
	 * Perform setup install or upgrade.
	 *
	 * @param bool $network_wide Is network wide.
	 * @return void
	 */
	protected static function setup( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// Network activation - run for each site.
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::create_tables();
				self::init_options();
				self::init_newsman_options();
				restore_current_blog();
			}
		} else {
			// Single site activation.
			self::create_tables();
			self::init_options();
			self::init_newsman_options();
		}
	}

	/**
	 * Create plugin tables
	 *
	 * @return void
	 */
	protected static function create_tables() {
		if ( get_option( 'newsman_setup_version', '1.0.0', '<' ) ) {
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
	 * Init admin options
	 *
	 * @return void
	 */
	protected static function init_newsman_options() {
		if ( get_option( 'newsman_setup_version', '1.0.0', '<' ) ) {
			self::init_newsman_options_one_zero_zero();
		}
	}

	/**
	 * Init admin options 1.0.0
	 *
	 * @return void
	 */
	protected static function init_newsman_options_one_zero_zero() {
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
	 * Init admin options for the first time with add_option insert only function.
	 *
	 * @return void
	 */
	protected static function init_options() {
		if ( get_option( 'newsman_setup_version', '1.0.0', '<' ) ) {
			self::init_options_one_zero_zero();
		}
	}

	/**
	 * Version 1.0.0 options update
	 *
	 * @return void
	 */
	protected static function init_options_one_zero_zero() {
		update_option( 'newsman_setup_version', '1.0.0', true );
		add_option( 'newsman_api', 'on' );
		add_option( 'newsman_useremarketing', 'on' );
		add_option( 'newsman_senduserip', 'on' );
		add_option( 'newsman_developeractiveuserip', '' );
		add_option( 'newsman_developeruserip', '' );
		add_option( 'newsman_developerpluginlazypriority', \WP_Newsman::PLUGIN_PRIORITY_LAZY_LOAD );
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
}
