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

namespace Newsman\Remarketing\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remarketing action send page view
 *
 * @class \Newsman\Remarketing\Action\PageView
 */
class PageView extends AbstractAction {
	/**
	 * Allow mark page view sent flag
	 */
	public const MARK_PAGE_VIEW_SENT_FLAG = 'mark_page_view_sent_flag';

	/**
	 * Is action page view sent
	 *
	 * @var bool
	 */
	public static $is_page_view_sent = false;

	/**
	 * Get JS code
	 *
	 * @return string
	 */
	public function get_js() {
		if ( ! $this->is_tracking_allowed() ) {
			return '';
		}

		$js = '';
		if ( false === self::get_page_view_sent() ) {
			$js = $this->remarketing_config->get_js_track_run_func() . "( 'send', 'pageview' ); ";
			if ( ! empty( $this->data[ self::MARK_PAGE_VIEW_SENT_FLAG ] ) ) {
				self::set_page_view_sent();
			}
		}
		return apply_filters( 'newsman_remarketing_action_page_view_js', $js );
	}

	/**
	 * Is tracking allowed.
	 * This action can be run on WordPress without WooCommerce.
	 *
	 * @return bool
	 */
	public function is_tracking_allowed() {
		return $this->remarketing_config->is_tracking_allowed();
	}

	/**
	 * Sets the page view sent status to true.
	 *
	 * @return void
	 */
	public static function set_page_view_sent() {
		self::$is_page_view_sent = true;
		self::$is_page_view_sent = apply_filters( 'newsman_remarketing_action_page_view_js', self::$is_page_view_sent );
	}

	/**
	 * Gets the page view sent status.
	 *
	 * @return bool
	 */
	public static function get_page_view_sent() {
		return self::$is_page_view_sent;
	}
}
