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

namespace Newsman\Scheduler\Subscribe;

use Newsman\Scheduler\AbstractScheduler;
use Newsman\User\IpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribe an email address to the emails list
 *
 * @class \Newsman\Scheduler\Subscribe\Email
 */
class Email extends AbstractScheduler {
	/**
	 * Background event hook subscribe email to list
	 */
	public const BACKGROUND_HOOK_EVENT = 'newsman_subscribe_email';

	/**
	 * Wait in micro seconds before retry subscribe email to list
	 */
	public const WAIT_RETRY_TIMEOUT = 5000000;

	/**
	 * User IP address
	 *
	 * @var IpAddress
	 */
	protected $user_ip;

	/**
	 * Class constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->user_ip = IpAddress::init();
	}

	/**
	 * Is allow action to run
	 *
	 * @return bool
	 */
	public function is_allow() {
		return apply_filters( 'newsman_scheduler_subscribe_email_allow', $this->config->is_enabled_with_api() );
	}

	/**
	 * Init WordPress and Woo Commerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! $this->is_allow() ) {
			return;
		}

		if ( $this->action_scheduler->is_allowed_single() && $this->config->use_action_scheduler_subscribe() ) {
			add_action( self::BACKGROUND_HOOK_EVENT, array( $this, 'subscribe' ), 10, 6 );
		}
	}

	/**
	 * Execute subscribe email to list
	 *
	 * @param string $email Email address.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array.
	 * @param array  $options Options array, additional fields.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function execute( $email, $firstname, $lastname, $properties = array(), $options = array() ) {
		if ( ! ( $this->action_scheduler->is_allowed_single() && $this->config->use_action_scheduler_subscribe() ) ) {
			$this->subscribe( $email, $firstname, $lastname, $properties, $options );
			return;
		}

		$this->schedule( $email, $firstname, $lastname, $properties, $options );
	}

	/**
	 * Schedule subscribe
	 *
	 * @param string $email Email address.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array.
	 * @param array  $options Options array, additional fields.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function schedule( $email, $firstname, $lastname, $properties = array(), $options = array() ) {
		as_schedule_single_action(
			time(),
			self::BACKGROUND_HOOK_EVENT,
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
	public function subscribe( $email, $firstname, $lastname, $properties = array(), $options = array(), $is_scheduled = false ) {
		if ( empty( $email ) || ! $this->is_allow() ) {
			return;
		}

		if ( $this->config->is_newsletter_double_optin() ) {
			$this->subscribe_double_optin( $email, $firstname, $lastname, $properties, $options, $is_scheduled );
		} else {
			$this->subscribe_single_optin( $email, $firstname, $lastname, $properties, $options, $is_scheduled );
		}
	}

	/**
	 * Subscribe double optin email to list
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
	public function subscribe_double_optin( $email, $firstname, $lastname, $properties = array(), $options = array(), $is_scheduled = false ) {
		do_action(
			'newsman_subscribe_email_double_optin_before',
			$email,
			$firstname,
			$lastname,
			$properties,
			$options,
			$is_scheduled
		);
		$context = new \Newsman\Service\Context\InitSubscribeEmail();
		$context->set_list_id( $this->config->get_list_id() )
			->set_email( $email )
			->set_firstname( $firstname )
			->set_lastname( $lastname )
			->set_ip( $this->user_ip->get_ip() )
			->set_properties( $properties )
			->set_options( $options );
		$context = apply_filters( 'newsman_subscribe_email_double_optin_context', $context );

		try {
			try {
				$init_subscribe = new \Newsman\Service\InitSubscribeEmail();
				$init_subscribe->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT );

					$init_subscribe = new \Newsman\Service\InitSubscribeEmail();
					$init_subscribe->execute( $context );
				} else {
					throw $e;
				}
			}
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
		}

		do_action(
			'newsman_subscribe_email_double_optin_after',
			$email,
			$firstname,
			$lastname,
			$properties,
			$options,
			$is_scheduled
		);
	}

	/**
	 * Subscribe single optin email to list
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
	public function subscribe_single_optin( $email, $firstname, $lastname, $properties = array(), $options = array(), $is_scheduled = false ) {
		do_action(
			'newsman_subscribe_email_single_optin_before',
			$email,
			$firstname,
			$lastname,
			$properties,
			$options,
			$is_scheduled
		);

		$context = new \Newsman\Service\Context\SubscribeEmail();
		$context->set_list_id( $this->config->get_list_id() )
			->set_email( $email )
			->set_firstname( $firstname )
			->set_lastname( $lastname )
			->set_ip( $this->user_ip->get_ip() )
			->set_properties( $properties );
		$context = apply_filters( 'newsman_subscribe_email_single_optin_context', $context );

		try {
			try {
				$subscribe     = new \Newsman\Service\SubscribeEmail();
				$subscriber_id = $subscribe->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT );

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
				$context = apply_filters( 'newsman_subscribe_email_single_optin_context_segment_add', $context );

				try {
					$add_subscriber = new \Newsman\Service\Segment\AddSubscriber();
					$add_subscriber->execute( $context );
				} catch ( \Exception $e ) {
					// Try again if action is scheduled. Otherwise, throw the exception further.
					if ( false !== $is_scheduled ) {
						$this->logger->log_exception( $e );
						$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
						usleep( self::WAIT_RETRY_TIMEOUT );

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

		do_action(
			'newsman_subscribe_email_single_optin_after',
			$email,
			$firstname,
			$lastname,
			$properties,
			$options,
			$is_scheduled
		);
	}
}
