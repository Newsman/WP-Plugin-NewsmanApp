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

namespace Newsman\Export\Retriever;

use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Export Retriever Coupons
 *
 * @class Coupons
 */
class Coupons implements RetrieverInterface {
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
		$this->logger = Logger::init();
	}

	/**
	 * Process coupons retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process( $data = array(), $blog_id = null ) {
		/* translators: 1: Input data */
		$this->logger->info( sprintf( esc_html__( 'Add coupons: %s', 'newsman' ), wp_json_encode( $data ) ) );

		if ( ! class_exists( 'WC_Coupon' ) ) {
			include_once WC()->plugin_path() . '/includes/class-wc-coupon.php';
		}

		$discount_type = ! isset( $data['type'] ) ? -1 : (int) $data['type'];
		$value         = ! isset( $data['value'] ) ? -1 : (int) $data['value'];
		$batch_size    = ! isset( $data['batch_size'] ) ? 1 : (int) $data['batch_size'];
		$prefix        = ! isset( $data['prefix'] ) ? '' : $data['prefix'];
		$expire_date   = isset( $data['expire_date'] ) ? $data['expire_date'] : null;
		$min_amount    = ! isset( $data['min_amount'] ) ? -1 : (float) $data['min_amount'];
		$currency      = isset( $data['currency'] ) ? $data['currency'] : '';

		if ( -1 === $discount_type || '-1' === $discount_type ) {
			return array(
				'status' => 0,
				'msg'    => 'Missing type param',
			);
		} elseif ( -1 === $value || '-1' === $value ) {
			return array(
				'status' => 0,
				'msg'    => 'Missing value param',
			);
		}

		try {
			$coupons_list  = array();
			$coupons_codes = array();
			for ( $step = 0; $step < $batch_size; $step++ ) {
				$coupon          = $this->process_coupon( $discount_type, $prefix, $expire_date, $value, $min_amount );
				$coupons_codes[] = $coupon->get_code();
				$coupons_list[]  = $coupon;
			}

			$this->logger->info(
				sprintf(
					/* translators: 1: Coupons count, 2: All coupons codes added */
					esc_html__( 'Added %1$d coupons %2$s', 'newsman' ),
					count( $coupons_codes ),
					implode( ', ', $coupons_codes )
				)
			);

			return array(
				'status' => 1,
				'codes'  => $coupons_codes,
			);
		} catch ( \Exception $e ) {
			foreach ( $coupons_list as $coupon ) {
				try {
					$coupon->delete();
				} catch ( \Exception $ex ) {
					$this->logger->log_exception( $ex );
				}
			}

			$this->logger->log_exception( $e );

			return array(
				'status' => 0,
				'msg'    => $e->getMessage(),
			);
		}
	}

	/**
	 * Save coupon
	 *
	 * @param int         $discount_type Discount type 0 or 1.
	 * @param string      $prefix Prefix of coupon code.
	 * @param null|string $expire_date Expire date of coupon code.
	 * @param int         $value Value of discount applied.
	 * @param int         $min_amount Minimum purchase amount.
	 * @return \WC_Coupon
	 */
	public function process_coupon( $discount_type, $prefix, $expire_date, $value, $min_amount ) {
		$coupon = new \WC_Coupon();

		switch ( $discount_type ) {
			case 1:
				$coupon->set_discount_type( 'percent' );
				break;
			case 0:
				$coupon->set_discount_type( 'fixed_cart' );
				break;
		}

		$coupon->set_code( $this->generate_coupon_code( $prefix ) );
		$coupon->set_description( 'NewsMAN generated coupon code' );
		$coupon->set_amount( $value );

		if ( null !== $expire_date ) {
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$formatted_expire_date = date( 'Y-m-d H:i:s', strtotime( $expire_date ) );
			$coupon->set_date_expires( strtotime( $formatted_expire_date ) );
		}

		if ( -1 !== $min_amount && '-1' !== $min_amount ) {
			$coupon->set_minimum_amount( $min_amount );
		}

		$coupon->save();

		return $coupon;
	}

	/**
	 * Generate coupon code
	 *
	 * @param string $prefix Prefix of coupon code.
	 * @return string
	 */
	public function generate_coupon_code( $prefix ) {
		$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$fail_safe  = 0;

		do {
			++$fail_safe;
			$coupon_code = '';
			for ( $i = 0; $i < 8; $i++ ) {
				$coupon_code .= $characters[ wp_rand( 0, strlen( $characters ) - 1 ) ];
			}
			$full_coupon_code   = $prefix . $coupon_code;
			$existing_coupon_id = wc_get_coupon_id_by_code( $full_coupon_code );
		} while ( ! empty( $existing_coupon_id ) && $fail_safe < 3 );

		return $full_coupon_code;
	}
}
