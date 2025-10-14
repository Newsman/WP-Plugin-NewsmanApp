<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman info banner class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Remarketing;

use Newsman\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays a message after install (if not dismissed and GA is not already configured) about how to configure the analytics plugin.
 *
 * @class \Newsman\Remarketing\InfoBanner
 */
class InfoBanner {

	/**
	 * Self singleton instance
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Is banner dismissed
	 *
	 * @var bool
	 */
	private $is_dismissed = false;

	/**
	 * Get the class instance.
	 *
	 * @param bool   $dismissed Is banner dismissed.
	 * @param string $ga_id GA ID.
	 * @return self
	 */
	public static function get_instance( $dismissed = false, $ga_id = '' ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $dismissed, $ga_id );
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @param bool   $dismissed Is banner dismissed.
	 * @param string $ga_id GA ID.
	 */
	public function __construct( $dismissed = false, $ga_id = '' ) {
		$this->is_dismissed = (bool) $dismissed;
		if ( ! empty( $ga_id ) ) {
			$this->is_dismissed = true;
		}

		// Don't bother setting anything else up if we are not going to show the notice.
		if ( true === $this->is_dismissed ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'banner' ) );
		add_action( 'admin_init', array( $this, 'dismiss_banner' ) );
	}

	/**
	 * Displays a info banner on WooCommerce settings pages
	 */
	public function banner() {
		$screen = get_current_screen();

		if ( ! in_array( $screen->base, array( 'woocommerce_page_wc-settings', 'plugins' ), true ) || $screen->is_network || $screen->action ) {
			return;
		}

		$integration_url = esc_url( admin_url( 'admin.php?page=wc-settings&tab=integration&section=newsman_remarketing' ) );
		$dismiss_url     = $this->dismiss_url();

		// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		$heading = __( 'Newsman Remarketing &amp; WooCommerce', 'newsman' );
		// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		$configure = sprintf( __( '<a href="%s">Connect your Newsman Remarketing ID</a> to finish setting up this integration.', 'newsman' ), $integration_url );

		// Display the message.
		echo '<div class="updated fade"><p><strong>' . esc_html( $heading ) . '</strong> ';
		echo '<a href="' . esc_url( $dismiss_url ) . '" title="' . esc_attr( __( 'Dismiss this notice.', 'newsman' ) ) . '"> ' . esc_html( __( '(Dismiss)', 'newsman' ) ) . '</a>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p>' . $configure . "</p></div>\n";
	}

	/**
	 * Returns the url that the user clicks to remove the info banner
	 *
	 * @return (string)
	 */
	public function dismiss_url() {
		$url = admin_url( 'admin.php' );

		$url = add_query_arg(
			array(
				'page'      => 'wc-settings',
				'tab'       => 'integration',
				'wc-notice' => 'dismiss-info-banner',
			),
			$url
		);

		return wp_nonce_url( $url, 'woocommerce_info_banner_dismiss' );
	}

	/**
	 * Handles the dismiss action so that the banner can be permanently hidden
	 *
	 * @return void
	 */
	public function dismiss_banner() {
		if ( ! isset( $_GET['wc-notice'] ) ) {
			return;
		}

		if ( 'dismiss-info-banner' !== $_GET['wc-notice'] ) {
			return;
		}

		if ( ! check_admin_referer( 'woocommerce_info_banner_dismiss' ) ) {
			return;
		}

		update_option( 'woocommerce_dismissed_info_banner', true, Config::AUTOLOAD_OPTIONS );

		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=integration' ) );
		}
	}
}
