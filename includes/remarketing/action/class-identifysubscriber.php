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

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$js .= $this->get_checkout_guest_identify_js();
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

	/**
	 * Identify guest checkout visitors when they enter an email address.
	 *
	 * @return string
	 */
	protected function get_checkout_guest_identify_js() {
		return <<<'JS'
(function() {
	var lastIdentifiedEmail = '';
	var emailRegex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}(\.[0-9]{1,3}){3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
	var selector = [
		'input[type="email"]',
		'input[name="email"]',
		'input[name="billing_email"]',
		'#email',
		'#billing_email',
		'input[autocomplete="email"]'
	].join(',');

	function normalizeEmail(value) {
		return String(value || '').trim().toLowerCase();
	}

	function identifyFromInput(input) {
		if (!input || !window._nzm || typeof window._nzm.identify !== 'function') {
			return;
		}

		var email = normalizeEmail(input.value);
		if (!email || email === lastIdentifiedEmail || !emailRegex.test(email)) {
			return;
		}

		lastIdentifiedEmail = email;
		window._nzm.identify({ email: email });
	}

	function bindInput(input) {
		if (!input || input.getAttribute('data-nzm-checkout-identify') === '1') {
			return;
		}

		input.setAttribute('data-nzm-checkout-identify', '1');
		input.addEventListener('change', function(event) {
			identifyFromInput(event.currentTarget);
		});
		input.addEventListener('focusout', function(event) {
			identifyFromInput(event.currentTarget);
		});
		identifyFromInput(input);
	}

	function bindCheckoutEmailInputs() {
		var inputs = document.querySelectorAll(selector);
		for (var i = 0; i < inputs.length; i++) {
			bindInput(inputs[i]);
		}
	}

	bindCheckoutEmailInputs();

	if ('MutationObserver' in window) {
		var observer = new MutationObserver(bindCheckoutEmailInputs);
		observer.observe(document.body || document.documentElement, {
			childList: true,
			subtree: true
		});
	}
})();
JS;
	}
}
