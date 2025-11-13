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

use Newsman\Config\Sms as SmsConfig;
use Newsman\Scheduler\AbstractScheduler;
use Newsman\User\IpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribe a phone number to the SMS list
 *
 * @class \Newsman\Scheduler\Subscribe\Phone
 */
class Phone extends AbstractScheduler {
	/**
	 * Background event hook subscribe a phone number to SMS list
	 */
	public const BACKGROUND_HOOK_EVENT = 'newsman_subscribe_phone';

	/**
	 * Wait in micro seconds before retry subscribe a phone number to SMS list
	 */
	public const WAIT_RETRY_TIMEOUT = 5000000;

	/**
	 * Config SMS
	 *
	 * @var SmsConfig
	 */
	protected $sms_config;

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
		$this->sms_config = SmsConfig::init();
		$this->user_ip    = IpAddress::init();
	}

	/**
	 * Is allow action to run
	 *
	 * @return bool
	 */
	public function is_allow() {
		return apply_filters( 'newsman_scheduler_subscribe_phone_allow', $this->sms_config->is_enabled_with_api() );
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
			add_action( self::BACKGROUND_HOOK_EVENT, array( $this, 'subscribe' ), 10, 5 );
		}
	}

	/**
	 * Execute subscribe phone number to SMS list
	 *
	 * @param string $telephone Phone number.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function execute( $telephone, $firstname, $lastname, $properties = array() ) {
		if ( ! ( $this->action_scheduler->is_allowed_single() && $this->config->use_action_scheduler_subscribe() ) ) {
			$this->subscribe( $telephone, $firstname, $lastname, $properties );
			return;
		}

		$this->schedule( $telephone, $firstname, $lastname, $properties );
	}

	/**
	 * Schedule subscribe
	 *
	 * @param string $telephone Phone numbers.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function schedule( $telephone, $firstname, $lastname, $properties = array() ) {
		as_schedule_single_action(
			time(),
			self::BACKGROUND_HOOK_EVENT,
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

	/**
	 * Subscribe a phone number to SMS list
	 *
	 * @param string $telephone Phone numbers.
	 * @param string $firstname First name.
	 * @param string $lastname Last name.
	 * @param array  $properties Properties array.
	 * @param array  $is_scheduled Is action scheduled.
	 * @return void
	 * @throws \Exception Error exceptions or other.
	 */
	public function subscribe( $telephone, $firstname, $lastname, $properties = array(), $is_scheduled = false ) {
		if ( empty( $telephone ) || ! $this->is_allow() ) {
			return;
		}

		do_action(
			'newsman_subscribe_telephone_single_optin_before',
			$telephone,
			$firstname,
			$lastname,
			$properties,
			$is_scheduled
		);

		$context = new \Newsman\Service\Context\Sms\Subscribe();
		$context->set_list_id( $this->sms_config->get_list_id() )
			->set_telephone( $telephone )
			->set_firstname( $firstname )
			->set_lastname( $lastname )
			->set_ip( $this->user_ip->get_ip() )
			->set_properties( $properties );
		$context = apply_filters( 'newsman_subscribe_telephone_single_optin_context', $context );

		try {
			try {
				$subscribe = new \Newsman\Service\Sms\Subscribe();
				$subscribe->execute( $context );
			} catch ( \Exception $e ) {
				// Try again if action is scheduled. Otherwise, throw the exception further.
				if ( false !== $is_scheduled ) {
					$this->logger->log_exception( $e );
					$this->logger->notice( 'Wait ' . self::WAIT_RETRY_TIMEOUT . ' seconds before retry' );
					usleep( self::WAIT_RETRY_TIMEOUT );

					$subscribe = new \Newsman\Service\Sms\Subscribe();
					$subscribe->execute( $context );
				} else {
					throw $e;
				}
			}
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
		}

		do_action(
			'newsman_subscribe_telephone_single_optin_after',
			$telephone,
			$firstname,
			$lastname,
			$properties,
			$is_scheduled
		);
	}
}
