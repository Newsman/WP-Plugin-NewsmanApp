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

namespace Newsman\Form\Account;

use Newsman\Config;
use Newsman\Config\Sms as SmsConfig;
use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\User\IpAddress;
use Newsman\Util\ActionScheduler as NewsmanActionScheduler;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add subscribe to newsletter in My Account
 *
 * @class \Newsman\Form\Account\Processor
 */
class Processor {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Remarketing Config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Telephone
	 *
	 * @var Telephone
	 */
	protected $telephone;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Is subscribed cache
	 *
	 * @var bool
	 */
	protected $is_subscribed_cache;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->telephone          = new Telephone();
		$this->logger             = Logger::init();
	}

	/**
	 * Class constructor
	 */
	public function init_hooks() {
		if ( ! $this->is_hook_enabled() ) {
			return;
		}

		// Add link in My Account.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_link' ), 10, 2 );

		// Add rewrite endpoint.
		add_action( 'init', array( $this, 'add_endpoint' ), 10 );

		// Add new query var.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Process subscribe form.
		add_action( 'init', array( $this, 'process' ) );

		add_action( 'woocommerce_account_nzm-newsletter_endpoint', array( $this, 'display_content' ) );
		add_action( 'template_redirect', array( $this, 'process' ), 20 );
	}

	/**
	 * Is hook enabled
	 *
	 * @return bool
	 */
	public function is_hook_enabled() {
		if ( ! $this->config->is_enabled_with_api() ) {
			return false;
		}
		if ( ! $this->config->is_account_subscribe() ) {
			return false;
		}
		return true;
	}

	/**
	 * Process Newsman My Account Subscribe to Newsletter action.
	 *
	 * @return void
	 * @throws \Exception Exceptions.
	 */
	public function process() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! ( isset( $_POST['newsman_newsletter_submit'] ) && is_user_logged_in() ) ) {
			return;
		}

		$nonce = isset( $_POST['newsman_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['newsman_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'newsman_subscribe_newsletter' ) ) {
			wc_add_notice( esc_html__( 'Security check failed. Please try again.', 'newsman' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'nzm-newsletter' ) );
			exit;
		}

		$is_subscribe = false;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['nzmAccountNewsletter'] ) && 1 === (int) sanitize_text_field( wp_unslash( $_POST['nzmAccountNewsletter'] ) ) ) {
			$is_subscribe = true;
		}

		$is_subscribe_previous = false;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['nzmAccountNewsletterPrevious'] ) && 1 === (int) sanitize_text_field( wp_unslash( $_POST['nzmAccountNewsletterPrevious'] ) ) ) {
			$is_subscribe_previous = true;
		}

		if ( $is_subscribe && $is_subscribe_previous ) {
			wc_add_notice( esc_html__( 'You have already subscribed to our newsletter!', 'newsman' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'nzm-newsletter' ) );
			exit;
		}

		if ( ! $is_subscribe && ! $is_subscribe_previous ) {
			wc_add_notice( esc_html__( 'You have already unsubscribed from our newsletter!', 'newsman' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'nzm-newsletter' ) );
			exit;
		}

		try {
			$current_user = wp_get_current_user();
			$email        = $current_user->user_email;
			$firstname    = $current_user->first_name;
			$lastname     = $current_user->last_name;
			$properties   = array();
			$options      = array();

			if ( $is_subscribe ) {
				if ( $this->remarketing_config->is_send_telephone() && class_exists( '\WC_Customer' ) ) {
					$user_id = get_current_user_id();
					if ( $user_id > 0 ) {
						$customer  = new \WC_Customer( $user_id );
						$telephone = $customer->get_billing_phone();

						if ( ! empty( $telephone ) ) {
							$properties['tel']               = $telephone;
							$properties['phone']             = $telephone;
							$properties['telephone']         = $telephone;
							$properties['billing_telephone'] = $telephone;
						}

						$shipping_telephone = $customer->get_shipping_phone();
						if ( ! empty( $shipping_telephone ) ) {
							$shipping_telephone = $this->telephone->clean( $shipping_telephone );
							if ( ! empty( $shipping_telephone ) ) {
								$properties['shipping_telephone'] = $shipping_telephone;
							}
						}
					}
				}

				$filter     = apply_filters(
					'newsman_account_subscribe_newsletter_process_params',
					array(
						'email'      => $email,
						'firstname'  => $firstname,
						'lastname'   => $lastname,
						'properties' => $properties,
						'options'    => $options,
					),
					array(
						'current_user' => $current_user,
					)
				);
				$email      = isset( $filter['email'] ) ? $filter['email'] : $email;
				$firstname  = isset( $filter['firstname'] ) ? $filter['firstname'] : $firstname;
				$lastname   = isset( $filter['lastname'] ) ? $filter['lastname'] : $lastname;
				$properties = isset( $filter['properties'] ) ? $filter['properties'] : $properties;
				$options    = isset( $filter['options'] ) ? $filter['options'] : $options;

				$scheduler = new \Newsman\Scheduler\Subscribe\Email();
				$scheduler->execute( $email, $firstname, $lastname, $properties, $options );

				if ( $this->config->is_newsletter_double_optin() ) {
					wc_add_notice(
						esc_html__(
							'A subscription confirmation email will be sent to you shortly.',
							'newsman'
						),
						'success'
					);
				} else {
					wc_add_notice( esc_html__( 'You have successfully subscribed to our newsletter!', 'newsman' ), 'success' );
				}

				if ( $this->config->use_action_scheduler_subscribe() ) {
					wc_add_notice(
						esc_html__(
							'The subscription process usually takes less than a minute. After that the status of your subscription will be shown here. Thank you!',
							'newsman'
						)
					);
				}
			} else {
				$filter  = apply_filters(
					'newsman_account_unsubscribe_newsletter_process_params',
					array(
						'email'   => $email,
						'options' => $options,
					),
					array(
						'current_user' => $current_user,
					)
				);
				$email   = isset( $filter['email'] ) ? $filter['email'] : $email;
				$options = isset( $filter['options'] ) ? $filter['options'] : $options;

				$scheduler = new \Newsman\Scheduler\Unsubscribe\Email();
				$scheduler->execute( $email, $options );

				if ( $this->config->is_newsletter_double_optin() ) {
					wc_add_notice(
						esc_html__(
							'You have unsubscribed from our newsletter! An unsubscription confirmation email will be sent to you shortly.',
							'newsman'
						),
						'success'
					);
				} else {
					wc_add_notice( esc_html__( 'You have unsubscribed from our newsletter!', 'newsman' ), 'success' );
				}

				if ( $this->config->use_action_scheduler_unsubscribe() ) {
					wc_add_notice(
						esc_html__(
							'The unsubscription process usually takes less than a minute. After that the status of your subscription will be shown here. Thank you!',
							'newsman'
						)
					);
				}
			}
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );

			if ( $e->getCode() === \Newsman\Api\Error\InitSubscribe::TOO_MANY_REQUESTS ) {
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				wc_add_notice( esc_html__( $e->getMessage(), 'newsman' ), 'error' );
			} else {
				wc_add_notice( esc_html__( 'There was an error subscribing to the newsletter. Please try again.', 'newsman' ), 'error' );
			}
			exit;
		}

		// Redirect back to the subscription page.
		wp_safe_redirect( wc_get_account_endpoint_url( 'nzm-newsletter' ) );
		exit;
	}

	/**
	 * Check if email is already subscriber in Newsman.
	 *
	 * @param string $email Email to verify.
	 * @return bool
	 */
	public function get_is_subscribed( $email ) {
		if ( null !== $this->is_subscribed_cache ) {
			return $this->is_subscribed_cache;
		}

		$this->is_subscribed_cache = false;
		try {
			$context = new \Newsman\Service\Context\GetByEmail();
			$context->set_list_id( $this->config->get_list_id() )
				->set_email( $email );
			$get_by_email = new \Newsman\Service\GetByEmail();
			$result       = $get_by_email->execute( $context );

			if ( is_array( $result ) && 'subscribed' === $result['status'] ) {
				$this->is_subscribed_cache = true;
			}
		} catch ( \Exception $e ) {
			$this->is_subscribed_cache = false;
		}

		return $this->is_subscribed_cache;
	}

	/**
	 * Checks if the current user is subscribed.
	 *
	 * @return bool
	 */
	public function get_is_current_user_subscribed() {
		$current_user = wp_get_current_user();
		return $this->get_is_subscribed( $current_user->user_email );
	}

	/**
	 * Display form content
	 *
	 * @return void
	 */
	public function display_content() {
		ob_start();

		$path = plugin_dir_path( __FILE__ ) . '../patterns/account/subscribe.php';
		$path = apply_filters( 'newsman_account_subscribe_filepath', $path );

		include_once $path;

		$content = ob_get_clean();
		$content = (string) apply_filters( 'newsman_account_subscribe_content', $content );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
	}

	/**
	 * Add Newsman Subscribe to Newsletter page in My Account sidebar
	 *
	 * @param array $items My Account menu items.
	 * @return array
	 */
	public function add_link( $items ) {
		$new_items = array();

		foreach ( $items as $key => $value ) {
			if ( 'customer-logout' === $key ) {
				$new_items['nzm-newsletter'] = 'Newsletter';
			}
			$new_items[ $key ] = $value;
		}

		return $new_items;
	}

	/**
	 * Add rewrite endpoint
	 *
	 * @return void
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( 'nzm-newsletter', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add new query_vars
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'nzm-newsletter';
		return $vars;
	}
}
