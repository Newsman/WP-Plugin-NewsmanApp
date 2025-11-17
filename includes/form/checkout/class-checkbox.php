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
use Newsman\Config\Sms as SmsConfig;

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
	 * SMS config
	 *
	 * @var SmsConfig
	 */
	protected $sms_config;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config     = Config::init();
		$this->sms_config = SmsConfig::init();
	}

	/**
	 * Init WordPress and Woo Commerce hooks
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! $this->config->is_enabled_with_api() ) {
			return;
		}

		add_action( 'woocommerce_review_order_before_submit', array( $this, 'add_fields' ) );

		// Do not handle nzm_send_order_status used to send SMS if flags are not set.
		if ( ! ( $this->sms_config->is_enabled_with_api() && $this->config->is_checkout_order_status() ) ) {
			return;
		}

		// Create order in checkout, save nzm_send_order_status as meta field.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_send_order_status_field' ), 10, 1 );

		// Add nzm_send_order_status to API rest order.
		add_filter(
			'woocommerce_rest_prepare_shop_order_object',
			array(
				$this,
				'add_send_order_status_to_order_rest_api',
			),
			10,
			2
		);

		// Add nzm_send_order_status checkbox in admin order page.
		add_action(
			'woocommerce_admin_order_data_after_billing_address',
			array(
				$this,
				'add_send_order_status_to_order_admin_edit',
			),
			10,
			1
		);

		// Save as meta field nzm_send_order_status in admin order save.
		add_action(
			'woocommerce_process_shop_order_meta',
			array(
				$this,
				'save_send_order_status_from_admin_edit',
			),
			10,
			1
		);
	}

	/**
	 * Save order field nzm_send_order_status
	 *
	 * @param \WC_Order $order Order instance.
	 * @return void
	 */
	public function save_send_order_status_field( $order ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['nzm_send_order_status'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$is_send = sanitize_text_field( wp_unslash( $_POST['nzm_send_order_status'] ) );
			$order->update_meta_data( '_nzm_send_order_status', (string) ( (int) $is_send ) );
		} else {
			$order->update_meta_data( '_nzm_send_order_status', '0' );
		}
	}

	/**
	 * Add nzm_send_order_status to order REST API
	 *
	 * @param mixed     $response Response object.
	 * @param \WC_Order $order Order instance.
	 * @return mixed
	 */
	public function add_send_order_status_to_order_rest_api( $response, $order ) {
		$data = $response->get_data();

		$data['nzm_send_order_status'] = $order->get_meta( '_nzm_send_order_status' );

		$response->set_data( $data );
		return $response;
	}

	/**
	 * Add checkbox in edit order in admin for send order status SMS
	 *
	 * @param \WC_Order $order Order instance.
	 * @return void
	 */
	public function add_send_order_status_to_order_admin_edit( $order ) {
		$value = $order->get_meta( '_nzm_send_order_status' );

		woocommerce_wp_checkbox(
			array(
				'id'            => 'nzm_send_order_status',
				'label'         => esc_html__( 'Send Order Status SMS', 'newsman' ),
				'value'         => $value,
				'cbvalue'       => '1',
				'wrapper_class' => 'form-field-wide nzm-send-order-status-field',
			)
		);
	}

	/**
	 * Save nzm_send_order_status in admin order edit
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function save_send_order_status_from_admin_edit( $order_id ) {
		$order = wc_get_order( $order_id );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['nzm_send_order_status'] ) ) {
			$order->update_meta_data(
				'_nzm_send_order_status',
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
				(string) ( (int) sanitize_text_field( wp_unslash( $_POST['nzm_send_order_status'] ) ) )
			);
		} else {
			$order->update_meta_data( '_nzm_send_order_status', '0' );
		}

		$order->save();
	}

	/**
	 * Add checkout field subscribe to newsletter checkbox.
	 *
	 * @return void
	 */
	public function add_field_newsletter() {
		if ( ! $this->config->is_enabled_with_api() ) {
			return;
		}

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
			'label_class' => array( 'woocommerce-form__label woocommerce-form__label-for-checkbox checkbox nzm-newsletter__label' ),
			'input_class' => array( 'woocommerce-form__input woocommerce-form__input-checkbox input-checkbox nzm-newsletter__input-checkbox' ),
			'required'    => false,
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			'label'       => esc_html__( $this->config->get_checkout_newsletter_label(), 'newsman' ),
			'default'     => $default,
			'checked'     => $checked,
		);

		woocommerce_form_field(
			'newsmanCheckoutNewsletter',
			apply_filters( 'newsman_checkout_newsletter_field_args', $args )
		);
	}

	/**
	 * Add checkout field send order status checkbox.
	 *
	 * @return void
	 */
	public function add_field_order_status() {
		if ( ! ( $this->config->is_enabled_with_api() && $this->sms_config->is_enabled_with_api() ) ) {
			return;
		}

		if ( ! $this->config->is_checkout_order_status() ) {
			return;
		}

		$default = 0;
		$checked = '';
		if ( $this->config->is_checkout_order_status_checked() ) {
			$default = 1;
			$checked = 'checked';
		}

		$args = array(
			'type'        => 'checkbox',
			'class'       => array( 'form-row nzm-send-order-status' ),
			'label_class' => array( 'woocommerce-form__label woocommerce-form__label-for-checkbox checkbox nzm-order-status__label' ),
			'input_class' => array( 'woocommerce-form__input woocommerce-form__input-checkbox input-checkbox nzm-order-status__input-checkbox' ),
			'required'    => false,
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			'label'       => esc_html__( $this->config->get_checkout_order_status_label(), 'newsman' ),
			'default'     => $default,
			'checked'     => $checked,
		);

		woocommerce_form_field(
			'nzm_send_order_status',
			apply_filters( 'newsman_checkout_order_status_field_args', $args )
		);
	}

	/**
	 * Add checkout fields
	 *
	 * @return void
	 */
	public function add_fields() {
		$this->add_field_newsletter();
		$this->add_field_order_status();
	}

	/**
	 * Add checkout field subscribe to newsletter checkbox.
	 *
	 * @return void
	 * @deprecated Use instead add_field_newsletter.
	 */
	public function add_field() {
		$this->add_field_newsletter();
	}
}
