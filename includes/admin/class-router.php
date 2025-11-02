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

namespace Newsman\Admin;

use Newsman\Config;
use Newsman\Util\WooCommerceExist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin router class
 *
 * @class \Newsman\Admin\Router
 */
class Router {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 *  Woo Commerce Exists
	 *
	 * @var WooCommerceExist
	 */
	protected $woo_commerce_exists;

	/**
	 * Action
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * Allowed admin actions
	 *
	 * @var array
	 */
	protected $allowed_actions = array(
		'newsman_export_wordpress_subscribers' => array(
			'class'            => '\Newsman\Admin\Action\Export\WordpressSubscribers',
			'only_woocommerce' => false,
			'has_admin_notice' => true,
		),
		'newsman_export_subscribers_orders'    => array(
			'class'            => '\Newsman\Admin\Action\Export\SubscribersOrders',
			'only_woocommerce' => true,
			'has_admin_notice' => true,
		),
	);

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config              = Config::init();
		$this->woo_commerce_exists = new WooCommerceExist();
	}

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		$allowed_actions = $this->get_allowed_actions();

		foreach ( $allowed_actions as $data ) {
			if ( ! $data['has_admin_notice'] ) {
				continue;
			}

			add_action( 'admin_init', array( $data['class'], 'is_success_notice' ) );
			add_action( 'admin_notices', array( $data['class'], 'display_success_notice' ) );
		}
	}

	/**
	 * Get class instance
	 *
	 * @return self Router
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new Router();
		}

		return $instance;
	}

	/**
	 * Execute admin action
	 *
	 * @return void
	 */
	public function execute() {
		$allowed_actions = $this->get_allowed_actions();

		if ( isset( $_GET['action'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
			if ( isset( $allowed_actions[ $action ] ) ) {
				$this->action = $action;
			} else {
				return;
			}
		} else {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = $this->get_current_nonce();
		if ( ! ( ! empty( $nonce ) && wp_verify_nonce( $nonce, $this->action ) ) ) {
			wp_die( 'Security check failed' );
		}

		if ( $allowed_actions[ $this->action ]['only_woocommerce'] && ! $this->woo_commerce_exists->exist() ) {
			return;
		}

		$action_class = $allowed_actions[ $this->action ]['class'];
		$action_class::init()->execute();
	}

	/**
	 * Get allowed actions
	 *
	 * @return array
	 */
	public function get_allowed_actions() {
		return apply_filters( 'newsman_admin_router_allowed_actions', $this->allowed_actions );
	}

	/**
	 * Get current action
	 *
	 * @return string
	 */
	public function get_action() {
		return $this->action;
	}

	/**
	 * Get page's current nonce
	 *
	 * @return string
	 */
	public function get_current_nonce() {
		$nonce = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}
		return $nonce;
	}
}
