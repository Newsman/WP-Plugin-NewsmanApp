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
	 * Get JS code
	 *
	 * @return string
	 */
	public function get_js() {
		$js = $this->remarketing_config->get_js_track_run_func() . "( 'send', 'pageview' ); ";
		return apply_filters( 'newsman_remarketing_action_page_view_js', $js );
	}
}
