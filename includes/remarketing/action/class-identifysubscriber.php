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
 * Remarketing action identify subscriber
 *
 * @class \Newsman\Remarketing\Action\IdentifySubscriber
 */
class IdentifySubscriber extends AbstractAction {
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

		$current_user = null;
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();

			$js = '_nzm.identify({ email: "' . esc_attr( esc_html( $current_user->user_email ) ) . '", ' .
				'first_name: "' . esc_attr( esc_html( $current_user->user_firstname ) ) . '", ' .
				'last_name: "' . esc_attr( esc_html( $current_user->user_lastname ) ) . '" });';
		}

		return apply_filters(
			'newsman_remarketing_action_identify_subscriber_js',
			$js,
			array(
				'current_user' => $current_user,
			)
		);
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
}
