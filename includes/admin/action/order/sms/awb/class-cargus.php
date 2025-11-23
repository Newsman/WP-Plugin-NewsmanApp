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

namespace Newsman\Admin\Action\Order\Sms\Awb;

use Newsman\Config;
use Newsman\Config\Sms;
use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin order view action send SMS with Cargus AWB
 *
 * @class \Newsman\Admin\Action\Order\Sms\Cargus
 */
class Cargus {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * SMS Config
	 *
	 * @var Sms
	 */
	protected $sms_config;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config     = Config::init();
		$this->sms_config = Sms::init();
		$this->logger     = Logger::init();
	}

	/**
	 * Init WordPress and Woo Commerce hooks
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! $this->sms_config->is_enabled_with_api() ) {
			return;
		}

		if ( ! $this->config->is_cargus_plugin_active() ) {
			return;
		}

		if ( ! ( $this->sms_config->is_sms_send_cargus_awb() && ! empty( $this->sms_config->get_sms_cargus_awb_message() ) ) ) {
			return;
		}

		add_filter( 'woocommerce_order_actions', array( $this, 'add_menu_action' ), 10, 2 );
		add_action( 'woocommerce_order_action_newsman_send_sms_awb_cargus', array( $this, 'execute' ), 10, 1 );
	}

	/**
	 * Add action to send SMS with Cargus AWB in Order actions in admin order view page
	 *
	 * @param array     $actions Order actions.
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	public function add_menu_action( $actions, $order ) {
		$awb = get_post_meta( $order->get_id(), '_cargus_awb', true );
		if ( empty( $awb ) ) {
			return $actions;
		}
		$actions['newsman_send_sms_awb_cargus'] = __( 'Send SMS with Cargus AWB', 'newsman' );
		return $actions;
	}

	/**
	 * Execute action
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function execute( $order ) {
		$awb   = get_post_meta( $order->get_id(), '_cargus_awb', true );
		$phone = $order->get_billing_phone();

		try {
			$send_sms = new \Newsman\Scheduler\Order\Awb\Cargus\SendSms();
			$send_sms->notify( $order->get_id() );

			$order->add_order_note(
				sprintf(
					/* translators: 1: AWB, 2: Phone number */
					__( 'SMS with AWB (%1$s) sent to %2$s', 'newsman' ),
					$awb,
					$phone
				),
				false,
				true
			);
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
		}
	}
}
