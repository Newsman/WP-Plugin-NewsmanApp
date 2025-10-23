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

namespace Newsman\Remarketing\Script;

use Newsman\Remarketing\Config as RemarketingConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remarketing tracking script
 *
 * @class \Newsman\Remarketing\Script\Track
 */
class Track {
	/**
	 * Remarketing config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Construct class
	 */
	public function __construct() {
		$this->remarketing_config = RemarketingConfig::init();
	}

	/**
	 * Display Newsman remarketing tracking script in page
	 *
	 * @return void
	 */
	public function display_script() {
		if ( ! $this->remarketing_config->is_active() ) {
			return;
		}

		// The remarketing script can also be displayed in WordPress without WooCommerce. No WooCommerce check.
		if ( ! $this->remarketing_config->is_tracking_allowed() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_before_script();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->include_script();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_after_script();
	}

	/**
	 * Get JS code to display before tracking script
	 *
	 * @return string
	 */
	public function get_before_script() {
		$before = '';
		$before = apply_filters( 'newsman_remarketing_script_track_content_before', $before );
		return (string) $before;
	}

	/**
	 * Get JS code to display after tracking script
	 *
	 * @return string
	 */
	public function get_after_script() {
		$after = '';
		$after = apply_filters( 'newsman_remarketing_script_track_content_after', $after );
		return (string) $after;
	}

	/**
	 * Include script and return contents
	 *
	 * @return string
	 */
	public function include_script() {
		ob_start();

		$path = plugin_dir_path( __FILE__ ) . '../patterns/track.php';
		$path = apply_filters( 'newsman_remarketing_script_track_filepath', $path );

		include_once $path;

		$content = ob_get_clean();
		$content = (string) apply_filters( 'newsman_remarketing_script_track_content', $content );

		$content .= $this->include_script_cart();

		return $content;
	}

	/**
	 * Include track cart script and return contents
	 *
	 * @return string
	 */
	public function include_script_cart() {
		ob_start();

		$path = plugin_dir_path( __FILE__ ) . '../patterns/track-cart.php';
		$path = apply_filters( 'newsman_remarketing_script_track_cart_filepath', $path );

		include_once $path;

		$content = ob_get_clean();
		return (string) apply_filters( 'newsman_remarketing_script_track_cart_content', $content );
	}

	/**
	 * Display no tracking script
	 *
	 * @note ob_start and ob_get_clean is already used by function include_script.
	 * @return void
	 */
	public function display_no_track_script() {
		$path = plugin_dir_path( __FILE__ ) . '../patterns/no-track.php';
		$path = apply_filters( 'newsman_remarketing_script_notrack_filepath', $path );

		include_once $path;
	}

	/**
	 * Get script tag attributes
	 *
	 * @return string
	 */
	public function get_script_tag_additional_attributes() {
		$get_attributes = new GetAttributes();
		return $get_attributes->get();
	}

	/**
	 * JS with newsman config.
	 * Example:
	 *   _nzm_config['option_1'] = 1;
	 *   _nzm_config['option_2'] = 'value';
	 *
	 * @return string
	 */
	public function get_config_js() {
		$js = '';
		return apply_filters( 'newsman_remarketing_script_track_config_js', $js );
	}

	/**
	 * Get tracking URL
	 *
	 * @return string|void
	 * @throws \Exception Not implemented yet exception.
	 */
	public function get_tracking_url() {
		throw new \Exception( 'Not implemented' );
	}

	/**
	 * Get tracking script final URL
	 *
	 * @return string
	 * @throws \Exception Not implemented yet exception.
	 */
	public function get_script_final_url() {
		$url = '';
		if ( $this->remarketing_config->use_proxy() ) {
			$url = $this->get_resources_url() . '/' . $this->get_script_request_uri();
			throw new \Exception( 'Not implemented' );
		} else {
			$url = $this->remarketing_config->get_script_url();
		}
		return apply_filters( 'newsman_remarketing_script_track_final_url', $url );
	}

	/**
	 * Get resources URL.
	 * It will be implemented in near future.
	 *
	 * @return string
	 */
	public function get_resources_url() {
		return '';
	}

	/**
	 * Get script request uri.
	 * It will be implemented in near future.
	 *
	 * @return string
	 */
	public function get_script_request_uri() {
		return '';
	}

	/**
	 * Get store currency code
	 *
	 * @return string
	 */
	public function get_currency_code() {
		$code = get_woocommerce_currency();
		return (string) apply_filters( 'newsman_remarketing_script_track_currency_code', $code );
	}

	/**
	 * Is Woo Commerce plugin exists
	 *
	 * @return bool
	 */
	public function is_woo_commerce_exist() {
		$exist = new \Newsman\Util\WooCommerceExist();
		return $exist->exist();
	}
}
