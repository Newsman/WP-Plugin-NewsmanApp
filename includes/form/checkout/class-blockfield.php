<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman Blocks checkout fields.
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
 * Registers the newsletter and order-status SMS checkboxes on the WooCommerce
 * Blocks checkout via woocommerce_register_additional_checkout_field(), and
 * bridges the auto-saved _wc_other/newsman/* order meta to the legacy keys
 * _newsman_checkout_newsletter / _nzm_send_order_status that Processor reads.
 *
 * @class \Newsman\Form\Checkout\BlockField
 */
class BlockField {
	/**
	 * Newsletter checkbox field ID (namespace/key, hyphenated).
	 */
	public const FIELD_NEWSLETTER = 'newsman/checkout-newsletter';

	/**
	 * Order-status SMS checkbox field ID.
	 */
	public const FIELD_SEND_ORDER_STATUS = 'newsman/send-order-status';

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
	 * Init WooCommerce Blocks hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// woocommerce_register_additional_checkout_field was introduced in WC 8.9.
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		if ( ! $this->config->is_enabled_with_api() ) {
			return;
		}

		// Fields must register during or after woocommerce_init.
		add_action( 'woocommerce_init', array( $this, 'register_fields' ) );

		// Bridge Blocks-saved meta to the legacy keys that Processor reads.
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array( $this, 'bridge_meta_to_legacy_keys' ),
			20,
			2
		);
	}

	/**
	 * Register the Blocks checkout fields.
	 *
	 * @return void
	 */
	public function register_fields() {
		if ( $this->config->is_checkout_newsletter() ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => self::FIELD_NEWSLETTER,
					'label'    => $this->config->get_checkout_newsletter_label(),
					'type'     => 'checkbox',
					'location' => 'contact',
					'required' => false,
				)
			);

			add_filter(
				'woocommerce_get_default_value_for_' . self::FIELD_NEWSLETTER,
				array( $this, 'default_newsletter_checked' )
			);
		}

		if (
			$this->sms_config->is_enabled_with_api()
			&& $this->config->is_checkout_order_status()
		) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => self::FIELD_SEND_ORDER_STATUS,
					'label'    => $this->config->get_checkout_order_status_label(),
					'type'     => 'checkbox',
					'location' => 'contact',
					'required' => false,
				)
			);

			add_filter(
				'woocommerce_get_default_value_for_' . self::FIELD_SEND_ORDER_STATUS,
				array( $this, 'default_send_order_status_checked' )
			);
		}
	}

	/**
	 * Default-checked state for the newsletter checkbox.
	 *
	 * @return bool
	 */
	public function default_newsletter_checked() {
		return (bool) $this->config->is_checkout_newsletter_checked();
	}

	/**
	 * Default-checked state for the send-order-status SMS checkbox.
	 *
	 * @return bool
	 */
	public function default_send_order_status_checked() {
		return (bool) $this->config->is_checkout_order_status_checked();
	}

	/**
	 * Copy the Blocks-saved additional-field values to the legacy meta keys so
	 * Processor has a single source of truth across Classic and Blocks.
	 *
	 * @param \WC_Order        $order   Order instance.
	 * @param \WP_REST_Request $request Store API request.
	 * @return void
	 */
	public function bridge_meta_to_legacy_keys( $order, $request ) {
		unset( $request );

		if ( $this->config->is_checkout_newsletter() ) {
			$value = $order->get_meta( '_wc_other/' . self::FIELD_NEWSLETTER );
			$flag  = $this->truthy_to_flag( $value );
			$order->update_meta_data( '_newsman_checkout_newsletter', $flag );
		}

		if (
			$this->sms_config->is_enabled_with_api()
			&& $this->config->is_checkout_order_status()
		) {
			$value = $order->get_meta( '_wc_other/' . self::FIELD_SEND_ORDER_STATUS );
			$flag  = $this->truthy_to_flag( $value );
			$order->update_meta_data( '_nzm_send_order_status', $flag );
		}
	}

	/**
	 * Normalize a Blocks checkbox value (true/false, "1"/"0", "true"/"false") to a "1" or "0" flag.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	protected function truthy_to_flag( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_string( $value ) ) {
			$lower = strtolower( $value );
			if ( 'true' === $lower || '1' === $lower ) {
				return '1';
			}
			return '0';
		}
		return $value ? '1' : '0';
	}
}
