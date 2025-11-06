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

namespace Newsman\Admin\Action\Export;

use Newsman\Admin\Action\AbstractAction;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin action schedule export WooCommerce orders
 *
 * @class \Newsman\Admin\Action\Export\Orders
 */
class Orders extends AbstractAction {
	/**
	 * Action completion notice parameter
	 */
	public const COMPLETION_PARAM = 'export_orders_scheduled';

	/**
	 * Get class instance
	 *
	 * @return self Orders
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Orders();
		}

		return $instance;
	}

	/**
	 * Execute action
	 *
	 * @return void
	 */
	public function execute() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect = isset( $_GET['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_GET['redirect_to'] ) ) :
			admin_url( 'admin.php?page=Newsman' );
		$redirect = add_query_arg( self::COMPLETION_PARAM, 'true', $redirect );

		$scheduler = new \Newsman\Scheduler\Export\Orders();
		$scheduler->schedule_all();

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Check for completion of action. Used by admin notice.
	 *
	 * @return void
	 */
	public static function is_success_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::COMPLETION_PARAM ] ) && 'true' === $_GET[ self::COMPLETION_PARAM ] ) {
			set_transient( self::COMPLETION_PARAM . '_completed', true, 60 );
		}
	}

	/**
	 * Display admin notice about action completion
	 *
	 * @return void
	 */
	public static function display_success_notice() {
		if ( get_transient( self::COMPLETION_PARAM . '_completed' ) ) {
			delete_transient( self::COMPLETION_PARAM . '_completed' );

			$html  = '<div class="notice notice-success is-dismissible"><p>';
			$html .= sprintf(
				/* translators: 1: After date export orders */
				esc_html__( 'All orders after date %s were scheduled for export.', 'newsman' ),
				self::init()->remarketing_config->get_order_date()
			);
			$html .= '</p></div>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
		}
	}
}
