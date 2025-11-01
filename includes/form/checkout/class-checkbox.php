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

namespace Newsman\Form\Checkout;

use Newsman\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add checkbox subscribe to newsletter in checkout
 *
 * @class \Newsman\Form\Checkout
 */
class Checkbox {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Add checkout field subscribe to newsletter checkbox.
	 *
	 * @return void
	 */
	public function add_field() {

		if ( ! $this->config->is_checkout_newsletter() ) {
			return;
		}

		$default = 0;
		$checked = '';
		if ( $this->config->is_checkout_newsletter_checked() ) {
			$default = 1;
			$checked = 'checked';
		}

		$args = array(
			'type'        => 'checkbox',
			'class'       => array( 'form-row newsmanCheckoutNewsletter' ),
			'label_class' => array( 'woocommerce-form__label woocommerce-form__label-for-checkbox checkbox' ),
			'input_class' => array( 'woocommerce-form__input woocommerce-form__input-checkbox input-checkbox' ),
			'required'    => false,
			'label'       => $this->config->get_checkout_newsletter_label(),
			'default'     => $default,
			'checked'     => $checked,
		);

		woocommerce_form_field(
			'newsmanCheckoutNewsletter',
			apply_filters( 'newsman_checkout_newsletter_field_args', $args )
		);
	}
}
