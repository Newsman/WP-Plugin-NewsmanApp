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

		if ( $this->is_checkout_context() ) {
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
	 * Detect checkout pages, including WooCommerce Blocks checkout pages.
	 *
	 * @return bool
	 */
	protected function is_checkout_context() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Identify guest checkout visitors when they enter an email address.
	 *
	 * @return string
	 */
	protected function get_checkout_guest_identify_js() {
		return <<<'JS'
(function() {
	var boundAttribute = 'data-nzm-checkout-identify';
	var lastEmail = '';
	var maxAttempts = 10;
	var retryDelay = 250;
	var emailSelector = [
		'input[type="email"]',
		'input[name="email"]',
		'input[name="billing_email"]',
		'#email',
		'#billing_email',
		'input[autocomplete="email"]'
	].join(',');

	function getEmail(input) {
		return String(input && input.value || '').trim().toLowerCase();
	}

	function isEmail(email) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
	}

	function identifyEmail(email, attempt) {
		if (!email || email === lastEmail || !isEmail(email)) {
			return;
		}

		attempt = attempt || 0;
		try {
			window.sessionStorage.setItem('nzm_identify', JSON.stringify({ email: email }));
		} catch (error) {}

		if (!window._nzm || typeof window._nzm.identify !== 'function') {
			if (attempt < maxAttempts) {
				window.setTimeout(function() {
					identifyEmail(email, attempt + 1);
				}, retryDelay);
			}
			return;
		}

		lastEmail = email;
		window._nzm.identify({ email: email });
	}

	function bindInput(input) {
		if (!input || input.getAttribute(boundAttribute) === '1') {
			return;
		}

		input.setAttribute(boundAttribute, '1');
		input.addEventListener('change', function() {
			identifyEmail(getEmail(input));
		});
		input.addEventListener('focusout', function() {
			identifyEmail(getEmail(input));
		});
		identifyEmail(getEmail(input));
	}

	function bindEmailInputs() {
		var inputs = document.querySelectorAll(emailSelector);
		for (var i = 0; i < inputs.length; i++) {
			bindInput(inputs[i]);
		}
	}

	bindEmailInputs();

	if ('MutationObserver' in window) {
		var observer = new MutationObserver(bindEmailInputs);
		observer.observe(document.body || document.documentElement, {
			childList: true,
			subtree: true
		});
	}
})();
JS;
	}
}
