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

namespace Newsman\Util\Sms\Message;

use Newsman\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SMS Message Order Processor
 *
 * @class \Newsman\Util\Sms\Message\OrderProcessor
 */
class OrderProcessor {
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
	 * Process message. Replace placeholders.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $message SMS message.
	 * @return string
	 */
	public function process( $order, $message ) {
		$item_data = $order->get_data();
		$date      = $order->get_date_created()->date( 'F j, Y' );

		$message = apply_filters( 'newsman_sms_message_order_processor_process_before', $message, $order );

		$message = str_replace( '{{billing_first_name}}', $item_data['billing']['first_name'], $message );
		$message = str_replace( '{{billing_last_name}}', $item_data['billing']['last_name'], $message );
		$message = str_replace( '{{shipping_first_name}}', $item_data['shipping']['first_name'], $message );
		$message = str_replace( '{{shipping_last_name}}', $item_data['shipping']['last_name'], $message );
		$message = str_replace( '{{email}}', $item_data['billing']['email'], $message );
		$message = str_replace( '{{order_number}}', $item_data['id'], $message );
		$message = str_replace( '{{order_date}}', $date, $message );
		$message = str_replace( '{{order_total}}', $item_data['total'], $message );

		$message = $this->process_cargus( $message, $order );
		$message = $this->process_sameday( $message, $order );
		$message = $this->process_fancourier( $message, $order );

		$message = trim( $message );

		return apply_filters( 'newsman_sms_message_order_processor_process_after', $message, $order );
	}

	/**
	 * Replace message AWB placeholders
	 *
	 * @param string    $name Name of shipping service.
	 * @param string    $message SMS message.
	 * @param \WC_Order $order Order instance.
	 * @param string    $awb AWB value.
	 * @return string
	 */
	public function process_awb( $name, $message, $order, $awb ) {
		$after_filter = 'newsman_sms_message_order_processor_process_' . $name . '_after';

		$message = apply_filters( 'newsman_sms_message_order_processor_process_' . $name . '_before', $message, $order, $awb );

		$start_pos = stripos( $message, '{{if_' . $name . '_awb}}' );
		$end_pos   = stripos( $message, '{{endif_' . $name . '_awb}}' );
		if ( false !== $end_pos ) {
			$end_pos += strlen( '{{endif_' . $name . '_awb}}' );
		}

		// If the condition tags are broken.
		if ( false === $start_pos xor false === $end_pos ) {
			$message = str_replace( '{{if_' . $name . '_awb}}', '', $message );
			$message = str_replace( '{{endif_' . $name . '_awb}}', '', $message );
			$message = str_replace( '{{' . $name . '_awb}}', $awb, $message );
			return apply_filters( $after_filter, $message, $order, $awb );
		}

		// If both condition tags are missing.
		if ( false === $start_pos && false === $end_pos ) {
			$message = str_replace( '{{' . $name . '_awb}}', $awb, $message );
			return apply_filters( $after_filter, $message, $order, $awb );
		}

		// If AWB is empty, remove entire condition and the text in between.
		if ( empty( $awb ) ) {
			$message = str_replace( substr( $message, $start_pos, $end_pos - $start_pos ), '', $message );
			return apply_filters( $after_filter, $message, $order, $awb );
		}

		// Remove condition placeholder and replace AWB placeholder with AWB value.
		$message = str_replace( '{{if_' . $name . '_awb}}', '', $message );
		$message = str_replace( '{{endif_' . $name . '_awb}}', '', $message );
		$message = str_replace( '{{' . $name . '_awb}}', $awb, $message );

		return apply_filters( $after_filter, $message, $order, $awb );
	}

	/**
	 * Replace message Cargus AWB placeholders
	 *
	 * @param string    $message SMS message.
	 * @param \WC_Order $order Order instance.
	 * @return string
	 */
	public function process_cargus( $message, $order ) {
		if ( ! $this->config->is_cargus_plugin_active() ) {
			return $message;
		}
		$get_order_awb = new \Newsman\Carrier\Cargus\GetOrderAwb();
		$awb           = $get_order_awb->get( $order->get_id() );
		return $this->process_awb( 'cargus', $message, $order, $awb );
	}

	/**
	 * Replace message SamedayCourier AWB placeholders
	 *
	 * @param string    $message SMS message.
	 * @param \WC_Order $order Order instance.
	 * @return string
	 */
	public function process_sameday( $message, $order ) {
		if ( ! $this->config->is_sameday_plugin_active() ) {
			return $message;
		}
		$get_order_awb = new \Newsman\Carrier\Sameday\GetOrderAwb();
		$awb           = $get_order_awb->get( $order->get_id() );
		return $this->process_awb( 'sameday', $message, $order, $awb );
	}

	/**
	 * Replace message FAN Courier AWB placeholders
	 *
	 * @param string    $message SMS message.
	 * @param \WC_Order $order Order instance.
	 * @return string
	 */
	public function process_fancourier( $message, $order ) {
		if ( ! $this->config->is_fancourier_plugin_active() ) {
			return $message;
		}
		$get_order_awb = new \Newsman\Carrier\Fancourier\GetOrderAwb();
		$awb           = $get_order_awb->get( $order->get_id() );
		return $this->process_awb( 'fancourier', $message, $order, $awb );
	}
}
