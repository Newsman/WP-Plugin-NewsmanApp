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

namespace Newsman\Remarketing;

use Newsman\Config\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remarketing config
 *
 * @class \Newsman\Remarketing\Config
 */
class Config {
	/**
	 * Newsman config
	 *
	 * @var \Newsman\Config
	 */
	protected $config;

	/**
	 * Newsman options config
	 *
	 * @var Options
	 */
	protected $config_options;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config         = \Newsman\Config::init();
		$this->config_options = Options::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self \Newsman\Remarketing\Config
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Config();
		}

		return $instance;
	}

	/**
	 * Is active
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_active( $blog_id = null ) {
		return $this->config->is_active( $blog_id ) &&
			$this->use_remarketing( $blog_id ) &&
			! empty( $this->get_id( $blog_id ) );
	}

	/**
	 * Is active in any blog
	 *
	 * @return bool
	 */
	public function is_active_in_any() {
		foreach ( $this->config->get_all_blog_ids() as $blog_id ) {
			if ( $this->is_active( $blog_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Use remarketing
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function use_remarketing( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_useremarketing', '' );
	}

	/**
	 * Get Newsman remarketing ID
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_id( $blog_id = null ) {
		return $this->config->get_blog_option( $blog_id, 'newsman_remarketingid', '' );
	}

	/**
	 * Is anonymize user IP address
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_anonymize_ip( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_remarketinganonymizeip', '' );
	}

	/**
	 * Is send telephone
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_send_telephone( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_remarketingsendtelephone', '' );
	}

	/**
	 * Get additional product attributes
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function get_product_attributes( $blog_id = null ) {
		$return = $this->config->get_blog_option( $blog_id, 'newsman_remarketingproductattributes', '' );
		if ( empty( $return ) ) {
			return array();
		}
		return $return;
	}

	/**
	 * Get additional customer attributes from orders
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function get_customer_attributes( $blog_id = null ) {
		$return = $this->config->get_blog_option( $blog_id, 'newsman_remarketingcustomerattributes', '' );
		if ( empty( $return ) ) {
			return array();
		}
		return $return;
	}

	/**
	 * Is export WordPress users with role subscriber recurring
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_export_wordpress_subscribers( $blog_id = null ) {
		$is = 'on' === $this->config->get_blog_option( $blog_id, 'newsman_remarketingexportwordpresssubscribers', '' );
		return $is && $this->is_active( $blog_id );
	}

	/**
	 * Get export WordPress subscribers short recurring latest number of days
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int
	 */
	public function get_export_wordpress_recurring_short_days( $blog_id = null ) {
		$days = (int) $this->config->get_blog_option(
			$blog_id,
			'newsman_remarketingexportwordpresssubscribers_recurring_short_days',
			7
		);
		if ( 1 > $days ) {
			return 7;
		}
		return $days;
	}

	/**
	 * Get export WordPress subscribers long recurring latest number of days
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int
	 */
	public function get_export_wordpress_recurring_long_days( $blog_id = null ) {
		$days = (int) $this->config->get_blog_option(
			$blog_id,
			'newsman_remarketingexportwordpresssubscribers_recurring_long_days',
			90
		);
		if ( 1 > $days ) {
			return 90;
		}
		return $days;
	}

	/**
	 * Is export WooCommerce buyers with orders complete recurring
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_export_woocommerce_subscribers( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_remarketingexportwoocommercesubscribers', '' );
	}

	/**
	 * Get export WooCommerce buyers from orders short recurring latest number of days
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int
	 */
	public function get_export_woocommerce_recurring_short_days( $blog_id = null ) {
		$days = (int) $this->config->get_blog_option(
			$blog_id,
			'newsman_remarketingexportwoocommercesubscribers_recurring_short_days',
			7
		);
		if ( 1 > $days ) {
			return 7;
		}
		return $days;
	}

	/**
	 * Get export WooCommerce buyers from orders long recurring latest number of days
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int
	 */
	public function get_export_woocommerce_recurring_long_days( $blog_id = null ) {
		$days = (int) $this->config->get_blog_option(
			$blog_id,
			'newsman_remarketingexportwoocommercesubscribers_recurring_long_days',
			90
		);
		if ( 1 > $days ) {
			return 90;
		}
		return $days;
	}

	/**
	 * Is export orders recurring
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_export_orders( $blog_id = null ) {
		return 'on' === $this->config->get_blog_option( $blog_id, 'newsman_remarketingexportorders', '' );
	}

	/**
	 * Get export orders short recurring latest number of days
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int
	 */
	public function get_export_orders_recurring_short_days( $blog_id = null ) {
		$days = (int) $this->config->get_blog_option(
			$blog_id,
			'newsman_remarketingexportorders_recurring_short_days',
			7
		);
		if ( 1 > $days ) {
			return 7;
		}
		return $days;
	}

	/**
	 * Get export orders long recurring latest number of days
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return int
	 */
	public function get_export_orders_recurring_long_days( $blog_id = null ) {
		$days = (int) $this->config->get_blog_option(
			$blog_id,
			'newsman_remarketingexportorders_recurring_long_days',
			90
		);
		if ( 1 > $days ) {
			return 90;
		}
		return $days;
	}

	/**
	 * Get order date to export orders created after it, including.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_order_date( $blog_id = null ) {
		$date_string = $this->config->get_blog_option( $blog_id, 'newsman_remarketingorderdate', '' );

		if ( ! ( ! empty( $date_string ) && $this->is_valid_date_format( $date_string ) ) ) {
			$current_date = new \DateTime();
			$current_date->modify( '-5 years' );
			$date_string = $current_date->format( 'Y-m-d' );
		}

		return $date_string;
	}

	/**
	 * Test if a string is a valid date in YYYY-MM-DD format
	 *
	 * @param string $date_string The date string to validate.
	 * @return bool True if valid, false otherwise
	 */
	public function is_valid_date_format( $date_string ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_string ) ) {
			return false;
		}
		try {
			$date = \DateTime::createFromFormat( 'Y-m-d', $date_string );
		} catch ( \Exception $e ) {
			return false;
		}

		return $date && $date->format( 'Y-m-d' ) === $date_string;
	}

	/**
	 * Get remarketing script JS code
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_script_js( $blog_id = null ) {
		return (string) $this->config_options->get( 'newsman_scriptjs', $blog_id );
	}

	/**
	 * Get script URL
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_script_url( $blog_id = null ) {
		return (string) $this->config_options->get( 'newsman_trackingscripturl', $blog_id );
	}

	/**
	 * Get resources URL
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_resources_url( $blog_id = null ) {
		return (string) $this->config_options->get( 'newsman_httpresourceurl', $blog_id );
	}

	/**
	 * Get tracking URL
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_tracking_url( $blog_id = null ) {
		return (string) $this->config_options->get( 'newsman_httptrackingurl', $blog_id );
	}

	/**
	 * Get tracking JS run function
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_js_track_run_func( $blog_id = null ) {
		$return = (string) $this->config_options->get( 'newsman_jstrackrunfunc', $blog_id );
		if ( empty( $return ) ) {
			return '_nzm.run';
		}
		return $return;
	}

	/**
	 * Use proxy.
	 * It will be implemented.
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return false
	 */
	public function use_proxy( $blog_id = null ) {
		$blog_id;
		return false;
	}

	/**
	 * Get required file patterns URL
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function get_required_file_patterns( $blog_id = null ) {
		$str = (string) $this->config_options->get( 'newsman_httprequiredfilepatterns', $blog_id );
		if ( empty( $str ) ) {
			return array();
		}
		$str = str_replace( "\r", "\n", $str );
		$str = preg_replace( '/\n{2,}/', "\n", $str );
		$arr = explode( "\n", $str );
		if ( empty( $arr ) ) {
			return array();
		}
		$return = array();
		foreach ( $arr as $pattern ) {
			if ( ! empty( $pattern ) ) {
				$return[] = trim( $pattern );
			}
		}
		return $return;
	}

	/**
	 * Get script request URL path
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return string
	 */
	public function get_script_request_uri_path( $blog_id = null ) {
		$url = $this->get_script_url( $blog_id );
		if ( empty( $url ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$url_info = parse_url( $url );
		if ( isset( $url_info['path'] ) && ! empty( $url_info['path'] ) ) {
			$url_info['path'] = ltrim( $url_info['path'], '/' );
			if ( empty( $url_info['path'] ) ) {
				return '';
			}
			return $url_info['path'];
		}
		return '';
	}

	/**
	 * Is tracking disabled.
	 *
	 * @return bool
	 */
	public function is_tracking_allowed() {
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.Capabilities.RoleFound
		if ( current_user_can( 'administrator' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Is Woo Commerce application page
	 *
	 * @return bool
	 */
	public function is_woo_commerce_page() {
		if ( is_woocommerce() ) {
			return true;
		}
		if ( is_cart() ) {
			return true;
		}
		if ( is_checkout() ) {
			return true;
		}
		return false;
	}
}
