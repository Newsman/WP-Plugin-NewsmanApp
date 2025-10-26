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
use Newsman\Config\Sms;
use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\User\IpAddress;
use Newsman\Util\ActionScheduler as NewsmanActionScheduler;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add checkbox subscribe to newsletter in checkout
 *
 * @class \Newsman\Form\Checkout\Processor
 */
class Processor {
	/**
	 * Background event hook subscribe email to list
	 */
	public const SUBSCRIBE_EMAIL = 'newsman_subscribe_email';

	/**
	 * Background event hook subscribe telephone to SMS list
	 */
	public const SUBSCRIBE_PHONE = 'newsman_subscribe_phone';

	/**
	 * Wait in micro seconds before retry subscribe email
	 */
	public const WAIT_RETRY_TIMEOUT_SUBSCRIBE_EMAIL = 5000000;

	/**
	 * Wait in micro seconds before retry subscribe telephone number to SMS list
	 */
	public const WAIT_RETRY_TIMEOUT_SUBSCRIBE_PHONE = 5000000;

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
	 * Config SMS
	 *
	 * @var Sms
	 */
	protected $sms_config;

	/**
	 * User IP address
	 *
	 * @var IpAddress
	 */
	protected $user_ip;

	/**
	 * Telephone
	 *
	 * @var Telephone
	 */
	protected $telephone;

	/**
	 *  Action Scheduler Util
	 *
	 * @var NewsmanActionScheduler
	 */
	protected $action_scheduler;

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
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->sms_config         = Sms::init();
		$this->user_ip            = IpAddress::init();
		$this->telephone          = new Telephone();
		$this->action_scheduler   = new NewsmanActionScheduler();
		$this->logger             = Logger::init();
	}

	/**
	 * Class constructor
	 */
	public function init() {
		if ( ! $this->config->is_enabled_with_api() ) {
			return;
		}

		if ( $this->is_hook_enabled() ) {
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'process' ), 10, 2 );
		}

		if ( $this->action_scheduler->is_allowed_single() && $this->config->use_action_scheduler_subscribe() ) {
			add_action( self::SUBSCRIBE_EMAIL, array( $this, 'subscribe_email' ), 10, 6 );
			if ( $this->sms_config->is_enabled_with_api() ) {
				add_action( self::SUBSCRIBE_PHONE, array( $this, 'subscribe_phone' ), 10, 5 );
			}
		}
	}

	/**
	 * Is hook enabled
	 *
	 * @return bool
	 */
	public function is_hook_enabled() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! ( ! empty( $_POST['newsmanCheckoutNewsletter'] ) && 1 === (int) $_POST['newsmanCheckoutNewsletter'] ) ) {
			return false;
		}
		if ( ! $this->config->is_checkout_newsletter() && ! $this->config->is_checkout_sms() ) {
			return false;
		}
		return true;
	}

	/**
	 * Process checkout action.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function process( $order_id ) {
		$order      = wc_get_order( $order_id );
		$order_data = $order->get_data();

		$properties = array();

		try {
			$metadata = $order->get_meta_data();

			foreach ( $metadata as $_metadata ) {
				if ( '_billing_functia' === $_metadata->key || 'billing_functia' === $_metadata->key ) {
					$properties['functia'] = $_metadata->value;
				}
				if ( '_billing_sex' === $_metadata->key || 'billing_sex' === $_metadata->key ) {
					$properties['sex'] = $_metadata->value;
				}
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \Exception $e ) {
			// Custom fields not found.
		}

		foreach ( $this->remarketing_config->get_customer_attributes() as $attribute ) {
			if ( strpos( $attribute, 'billing_' ) === 0 || strpos( $attribute, 'shipping_' ) === 0 ) {
				$getter = 'get_' . $attribute;
				if ( method_exists( $order, $getter ) ) {
					$properties[ $attribute ] = $order->$getter();
				}
			}
		}

		$email     = $order_data['billing']['email'];
		$firstname = $order_data['billing']['first_name'];
		$lastname  = $order_data['billing']['last_name'];
		$telephone = ( ! empty( $order_data['billing']['phone'] ) ) ? $order_data['billing']['phone'] : '';
		$telephone = $this->telephone->clean( $telephone );

		$options    = array();
		$segment_id = $this->config->get_segment_id();
		if ( ! empty( $segment_id ) ) {
			$options['segments'] = array( $segment_id );
		}

		$form_id = $this->config->get_newsman_form_id();
		if ( ! empty( $form_id ) ) {
			$options['form_id'] = $form_id;
		}

		try {
			if ( ! $this->action_scheduler->is_allowed_single() || ! $this->config->use_action_scheduler_subscribe() ) {
				$this->subscribe_email( $email, $firstname, $lastname, $properties, $options );
			} elseif ( $this->config->is_checkout_newsletter() ) {
				as_schedule_single_action(
					time(),
					self::SUBSCRIBE_EMAIL,
					array(
						$email,
						$firstname,
						$lastname,
						$properties,
						$options,
						true,
					),
					$this->action_scheduler->get_group_subscribe()
				);
			}
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e->getMessage() );
		}

		if ( ! $this->action_scheduler->is_allowed_single() || ! $this->config->use_action_scheduler_subscribe() ) {
			$this->subscribe_phone( $telephone, $firstname, $lastname, $properties );
		} elseif ( $this->config->is_checkout_sms() && $this->sms_config->is_enabled_with_api() ) {
			as_schedule_single_action(
				time(),
				self::SUBSCRIBE_PHONE,
				array(
					$telephone,
					$firstname,
					$lastname,
					$properties,
					true,
				),
				$this->action_scheduler->get_group_subscribe()
			);
		}
	}

	/**
	 * Subscribe email to list
	 *
	 * @param string $email Email address.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array.
	 * @param array  $options Options array, additional fields.
	 * @param array  $is_scheduled Is action scheduled.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function subscribe_email( $email, $firstname, $lastname, $properties = array(), $options = array(), $is_scheduled = false ) {
		if ( empty( $email ) ) {
			return;
		}
		if ( ! $this->config->is_checkout_newsletter() ) {
			return;
		}
		if ( ! $this->config->is_enabled_with_api() ) {
			return;
		}

		if ( ! $this->remarketing_config->is_send_telephone() ) {
			unset( $properties['phone'] );
		}

		if ( $this->config->is_checkout_newsletter_double_optin() ) {
			$context = new \Newsman\Service\Context\InitSubscribeEmail();
			$context->set_list_id( $this->config->get_list_id() )
				->set_email( $email )
				->set_firstname( $firstname )
				->set_lastname( $lastname )
				->set_ip( $this->user_ip->get_ip() )
				->set_properties( $properties )
				->set_options( $options );

			try {
				try {
					$init_subscribe = new \Newsman\Service\InitSubscribeEmail();
					$init_subscribe->execute( $context );
				} catch ( \Exception $e ) {
					// Try again if action is scheduled. Otherwise, throw the exception further.
					if ( false !== $is_scheduled ) {
						$this->logger->log_exception( $e );
						$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT_SUBSCRIBE_EMAIL . ' seconds before retry' );
						usleep( self::WAIT_RETRY_TIMEOUT_SUBSCRIBE_EMAIL );

						$init_subscribe = new \Newsman\Service\InitSubscribeEmail();
						$init_subscribe->execute( $context );
					} else {
						throw $e;
					}
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		} else {
			$context = new \Newsman\Service\Context\SubscribeEmail();
			$context->set_list_id( $this->config->get_list_id() )
				->set_email( $email )
				->set_firstname( $firstname )
				->set_lastname( $lastname )
				->set_ip( $this->user_ip->get_ip() )
				->set_properties( $properties );

			try {
				try {
					$subscribe     = new \Newsman\Service\SubscribeEmail();
					$subscriber_id = $subscribe->execute( $context );
				} catch ( \Exception $e ) {
					// Try again if action is scheduled. Otherwise, throw the exception further.
					if ( false !== $is_scheduled ) {
						$this->logger->log_exception( $e );
						$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT_SUBSCRIBE_EMAIL . ' seconds before retry' );
						usleep( self::WAIT_RETRY_TIMEOUT_SUBSCRIBE_EMAIL );

						$subscribe     = new \Newsman\Service\SubscribeEmail();
						$subscriber_id = $subscribe->execute( $context );
					} else {
						throw $e;
					}
				}

				if ( ! empty( $this->config->get_segment_id() ) ) {
					$context = new \Newsman\Service\Context\Segment\AddSubscriber();
					$context->set_segment_id( $this->config->get_segment_id() )
						->set_subscriber_id( $subscriber_id );

					try {
						$add_subscriber = new \Newsman\Service\Segment\AddSubscriber();
						$add_subscriber->execute( $context );
					} catch ( \Exception $e ) {
						// Try again if action is scheduled. Otherwise, throw the exception further.
						if ( false !== $is_scheduled ) {
							$this->logger->log_exception( $e );
							$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT_SUBSCRIBE_EMAIL . ' seconds before retry' );
							usleep( self::WAIT_RETRY_TIMEOUT_SUBSCRIBE_EMAIL );

							$add_subscriber = new \Newsman\Service\Segment\AddSubscriber();
							$add_subscriber->execute( $context );
						} else {
							throw $e;
						}
					}
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}
	}

	/**
	 * Subscribe telephone number to SMS list
	 *
	 * @param string $telephone Telephone number.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array, additional fields.
	 * @param array  $is_scheduled Is action scheduled.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function subscribe_phone( $telephone, $firstname, $lastname, $properties = array(), $is_scheduled = false ) {
		if ( ! $this->config->is_checkout_sms() ) {
			return;
		}

		if ( ! $this->sms_config->is_enabled_with_api() ) {
			return;
		}

		if ( empty( $telephone ) ) {
			return;
		}

		$context = new \Newsman\Service\Context\Sms\Subscribe();
		$context->set_list_id( $this->sms_config->get_list_id() )
			->set_telephone( $telephone )
			->set_firstname( $firstname )
			->set_lastname( $lastname )
			->set_ip( $this->user_ip->get_ip() )
			->set_properties( $properties );

		try {
			try {
				$subscribe = new \Newsman\Service\Sms\Subscribe();
				$subscribe->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT_SUBSCRIBE_PHONE . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT_SUBSCRIBE_PHONE );

					$subscribe = new \Newsman\Service\Sms\Subscribe();
					$subscribe->execute( $context );
				} else {
					throw $e;
				}
			}
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
		}
	}
}
