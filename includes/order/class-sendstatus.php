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

namespace Newsman\Order;

use Newsman\Config;
use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronize order status with Newsman
 *
 * @class \Newsman\Order\Synchronize
 */
class SendStatus {

	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Newsman logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config = Config::init();
		$this->logger = Logger::init();
	}

	/**
	 * Send to Newsman order status pending.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function pending( $order_id ) {
		$this->save_order_newsman( $order_id, 'pending' );
	}

	/**
	 * Send to Newsman order status failed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function failed( $order_id ) {
		$this->save_order_newsman( $order_id, 'failed' );
	}

	/**
	 * Send to Newsman order status on hold.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function hold( $order_id ) {
		$this->save_order_newsman( $order_id, 'on-hold' );
	}

	/**
	 * Send to Newsman order status processing.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function processing( $order_id ) {
		$this->save_order_newsman( $order_id, 'processing' );
	}

	/**
	 * Send to Newsman order status completed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function completed( $order_id ) {
		$this->save_order_newsman( $order_id, 'completed' );
	}

	/**
	 * Send to Newsman order status refunded.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function refunded( $order_id ) {
		$this->save_order_newsman( $order_id, 'refunded' );
	}

	/**
	 * Send to Newsman order status canceled.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function cancelled( $order_id ) {
		$this->save_order_newsman( $order_id, 'cancelled' );
	}

	/**
	 * Send to Newsman order status send order status to Newsman.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Order status.
	 * @return void
	 */
	public function save_order_newsman( $order_id, $status ) {

		$newsman_usesms    = get_option( 'newsman_usesms' );
		$newsman_smslist   = get_option( 'newsman_smslist' );
		$newsman_smstest   = get_option( 'newsman_smstest' );
		$newsman_smstestnr = get_option( 'newsman_smstestnr' );

		$send_sms        = false;
		$newsman_smstext = '';

		$newsman_smspending = get_option( 'newsman_smspendingactivate' );
		if ( 'pending' === $status && 'on' === $newsman_smspending ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smspendingtext' );
		}
		$newsman_smsfailed = get_option( 'newsman_smsfailedactivate' );
		if ( 'failed' === $status && 'on' === $newsman_smsfailed ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsfailedtext' );
		}
		$newsman_smsonhold = get_option( 'newsman_smsonholdactivate' );
		if ( 'on-hold' === $status && 'on' === $newsman_smsonhold ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsonholdtext' );
		}
		$newsman_smsprocessing = get_option( 'newsman_smsprocessingactivate' );
		if ( 'processing' === $status && 'on' === $newsman_smsprocessing ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsprocessingtext' );
		}
		$newsman_smscompleted = get_option( 'newsman_smscompletedactivate' );
		if ( 'completed' === $status && 'on' === $newsman_smscompleted ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smscompletedtext' );
		}
		$newsman_smsrefunded = get_option( 'newsman_smsrefundedactivate' );
		if ( 'refunded' === $status && 'on' === $newsman_smsrefunded ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smsrefundedtext' );
		}
		$newsman_smscancelled = get_option( 'newsman_smscancelledactivate' );
		if ( 'cancelled' === $status && 'on' === $newsman_smscancelled ) {
			$send_sms        = true;
			$newsman_smstext = get_option( 'newsman_smscancelledtext' );
		}

		if ( $send_sms ) {
			try {
				if ( ! empty( $newsman_usesms ) && 'on' === $newsman_usesms && ! empty( $newsman_smslist ) ) {
					$order     = wc_get_order( $order_id );
					$item_data = $order->get_data();

					$date = $order->get_date_created()->date( 'F j, Y' );

					$newsman_smstext = str_replace( '{{billing_first_name}}', $item_data['billing']['first_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{billing_last_name}}', $item_data['billing']['last_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{shipping_first_name}}', $item_data['shipping']['first_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{shipping_last_name}}', $item_data['shipping']['last_name'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{email}}', $item_data['billing']['email'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_number}}', $item_data['id'], $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_date}}', $date, $newsman_smstext );
					$newsman_smstext = str_replace( '{{order_total}}', $item_data['total'], $newsman_smstext );
					$phone           = '4' . $item_data['billing']['phone'];

					if ( $newsman_smstest ) {
						$phone = '4' . $newsman_smstestnr;
					}

					$context = new \Newsman\Service\Context\Sms\SendOne();
					$context->set_list_id( $newsman_smslist )
					        ->set_text( $newsman_smstext )
					        ->set_to( $phone );
					$send_one = new \Newsman\Service\Sms\SendOne();
					$send_one->execute( $context );
				}
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$this->logger->log_exception( $e );
			}
		}

		$list_id = get_option( 'newsman_remarketingid' );
		$list_id = explode( '-', $list_id );
		$list_id = $list_id[1];

		$url = 'https://ssl.newsman.app/api/1.2/rest/' . $this->config->get_user_id() . '/'
		       . $this->config->get_api_key() . '/remarketing.setPurchaseStatus.json?list_id='
		       . $list_id . '&order_id=' . $order_id . '&status=' . $status;

		wp_remote_get( esc_url_raw( $url ), array() );
	}
}
