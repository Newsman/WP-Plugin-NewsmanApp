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

namespace Newsman\Scheduler\Unsubscribe;

use Newsman\Scheduler\AbstractScheduler;
use Newsman\User\IpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unsubscribe an email address from the emails list
 *
 * @class \Newsman\Scheduler\Unsubscribe\Email
 */
class Email extends AbstractScheduler {
	/**
	 * Background event hook unsubscribe email to list
	 */
	public const BACKGROUND_HOOK_EVENT = 'newsman_unsubscribe_email';

	/**
	 * Wait in micro seconds before retry unsubscribe email to list
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
		return apply_filters( 'newsman_scheduler_unsubscribe_email_allow', $this->config->is_enabled_with_api() );
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

		if ( $this->action_scheduler->is_allowed_single() && $this->config->use_action_scheduler_unsubscribe() ) {
			add_action( self::BACKGROUND_HOOK_EVENT, array( $this, 'unsubscribe' ), 10, 3 );
		}
	}

	/**
	 * Execute unsubscribe email to list
	 *
	 * @param string $email Email address.
	 * @param array  $options Options array, additional fields.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function execute( $email, $options = array() ) {
		if ( ! ( $this->action_scheduler->is_allowed_single() && $this->config->use_action_scheduler_unsubscribe() ) ) {
			$this->unsubscribe( $email, $options );
			return;
		}

		$this->schedule( $email, $options );
	}

	/**
	 * Schedule unsubscribe
	 *
	 * @param string $email Email address.
	 * @param array  $options Options array, additional fields.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function schedule( $email, $options = array() ) {
		as_schedule_single_action(
			time(),
			self::BACKGROUND_HOOK_EVENT,
			array(
				$email,
				$options,
				true,
			),
			$this->action_scheduler->get_group_subscribe()
		);
	}

	/**
	 * Unsubscribe email from list
	 *
	 * @param string $email Email address.
	 * @param array  $options Options array, additional fields.
	 * @param array  $is_scheduled Is action scheduled.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function unsubscribe( $email, $options = array(), $is_scheduled = false ) {
		if ( empty( $email ) || ! $this->is_allow() ) {
			return;
		}

		if ( $this->config->is_newsletter_double_optin() ) {
			$this->unsubscribe_double_optin( $email, $options, $is_scheduled );
		} else {
			$this->unsubscribe_single_optin( $email, $options, $is_scheduled );
		}
	}

	/**
	 * Unsubscribe double optin email from list
	 *
	 * @param string $email Email address.
	 * @param array  $options Options array, additional fields.
	 * @param array  $is_scheduled Is action scheduled.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function unsubscribe_double_optin( $email, $options = array(), $is_scheduled = false ) {
		do_action(
			'newsman_unsubscribe_email_double_optin_before',
			$email,
			$options,
			$is_scheduled
		);
		$context = new \Newsman\Service\Context\InitUnsubscribeEmail();
		$context->set_list_id( $this->config->get_list_id() )
			->set_email( $email )
			->set_ip( $this->user_ip->get_ip() )
			->set_options( $options );
		$context = apply_filters( 'newsman_unsubscribe_email_double_optin_context', $context );

		try {
			try {
				$init_unsubscribe = new \Newsman\Service\InitUnsubscribeEmail();
				$init_unsubscribe->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT );

					$init_unsubscribe = new \Newsman\Service\InitUnsubscribeEmail();
					$init_unsubscribe->execute( $context );
				} else {
					throw $e;
				}
			}
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
		}

		do_action(
			'newsman_unsubscribe_email_double_optin_after',
			$email,
			$options,
			$is_scheduled
		);
	}

	/**
	 * Unsubscribe single optin email to list
	 *
	 * @param string $email Email address.
	 * @param array  $options Options array, additional fields.
	 * @param array  $is_scheduled Is action scheduled.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function unsubscribe_single_optin( $email, $options = array(), $is_scheduled = false ) {
		do_action(
			'newsman_unsubscribe_email_single_optin_before',
			$email,
			$options,
			$is_scheduled
		);

		$context = new \Newsman\Service\Context\UnsubscribeEmail();
		$context->set_list_id( $this->config->get_list_id() )
			->set_email( $email )
			->set_ip( $this->user_ip->get_ip() );
		$context = apply_filters( 'newsman_unsubscribe_email_single_optin_context', $context );

		try {
			try {
				$unsubscribe = new \Newsman\Service\UnsubscribeEmail();
				$unsubscribe->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT );

					$unsubscribe = new \Newsman\Service\UnsubscribeEmail();
					$unsubscribe->execute( $context );
				} else {
					throw $e;
				}
			}
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
		}

		do_action(
			'newsman_unsubscribe_email_single_optin_after',
			$email,
			$options,
			$is_scheduled
		);
	}
}
