<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman personal data erasure.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Privacy;

use Newsman\Config;
use Newsman\Config\Sms as SmsConfig;
use Newsman\Logger;
use Newsman\User\IpAddress;
use Newsman\Util\ActionScheduler;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Anonymise in NewsMAN the people whose data is erased on the site: Tools > Erase Personal
 * Data (also used by WooCommerce) and deleted users. The email is anonymised in the email
 * list (subscriber.anonymiseEmail), whatever its status there. The phone numbers (account
 * and order billing phones) are unsubscribed from the SMS list: NewsMAN has no SMS anonymise.
 *
 * For deleted users the setting "Anonymise in NewsMAN when users are deleted" (on by
 * default) decides: when it is off, the email is unsubscribed instead. Tools > Erase
 * Personal Data always anonymises.
 *
 * Nothing is sent when users are deleted because a whole network site is deleted, when
 * WooCommerce deletes inactive accounts (Personal data retention), or when a user is only
 * removed from a site of a network (the account still exists): nobody asked to be
 * forgotten, the store owner manages the list in NewsMAN. On a network, only deleting the
 * account from the network sends it, to the list of every site the user belonged to.
 *
 * A later import of the same email, for example from the order billing emails that
 * WooCommerce keeps, subscribes the anonymised contact again.
 *
 * Nothing goes through a confirmation flow, even with double opt-in, so nobody receives
 * a confirmation email after asking to be forgotten.
 *
 * @class \Newsman\Privacy\Eraser
 */
class Eraser {
	/**
	 * Key of the eraser in the wp_privacy_personal_data_erasers list.
	 */
	public const ERASER_ID = 'newsman';

	/**
	 * Background event hook: anonymise the email and unsubscribe the phone numbers of a deleted user.
	 */
	public const BACKGROUND_HOOK_EVENT = 'newsman_anonymise_erased_email';

	/**
	 * Background event hook of version 3.7.41, still handled for the actions it scheduled.
	 */
	public const LEGACY_BACKGROUND_HOOK_EVENT = 'newsman_unsubscribe_erased_email';

	/**
	 * NewsMAN API error code returned when the email or phone number is not on the list.
	 */
	public const ERROR_NO_SUCH_SUBSCRIBER = 121;

	/**
	 * Result: the email was anonymised.
	 */
	public const RESULT_ANONYMISED = 'anonymised';

	/**
	 * Deleted users: anonymise the email.
	 */
	public const MODE_ANONYMISE = 'anonymise';

	/**
	 * Deleted users: unsubscribe the email.
	 */
	public const MODE_UNSUBSCRIBE = 'unsubscribe';

	/**
	 * Function that deletes a user account from a network.
	 */
	public const NETWORK_DELETE_CALLER = 'wpmu_delete_user';

	/**
	 * Result: the email or phone number was unsubscribed.
	 */
	public const RESULT_UNSUBSCRIBED = 'unsubscribed';

	/**
	 * Result: the email or phone number was not on the NewsMAN list.
	 */
	public const RESULT_NOT_SUBSCRIBED = 'not_subscribed';

	/**
	 * Wait in micro seconds before the retry of a scheduled unsubscribe.
	 */
	public const WAIT_RETRY_TIMEOUT = 5000000;

	/**
	 * Phone numbers shorter than this (digits only) are not sent.
	 */
	public const MIN_PHONE_LENGTH = 6;

	/**
	 * Most orders of one email address read for phone numbers.
	 */
	public const MAX_ORDERS = 500;

	/**
	 * Functions that delete users when nobody asked to be forgotten, so nothing is sent to
	 * NewsMAN: a whole network site is deleted (WordPress 5.1 and newer, then older versions),
	 * and WooCommerce deletes the inactive accounts (Personal data retention).
	 */
	public const NO_ACTION_CALLERS = array(
		'wp_uninitialize_site',
		'wpmu_delete_blog',
		'WC_Privacy::delete_inactive_accounts_query',
		'WC_Privacy::delete_inactive_accounts',
	);

	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Logger
	 *
	 * @var Logger|null
	 */
	protected $logger;

	/**
	 * Action Scheduler helper
	 *
	 * @var ActionScheduler|null
	 */
	protected $action_scheduler;

	/**
	 * Emails already handled in this request, keyed by "blog_id|email".
	 *
	 * @var array
	 */
	protected $handled = array();

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Init WordPress hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );

		if ( is_multisite() ) {
			// Fired for each site when a user is deleted from the network, and when a
			// user is removed or deleted from one site. Only the deletion from the
			// network is sent.
			add_action( 'remove_user_from_blog', array( $this, 'on_remove_user_from_blog' ), 10, 2 );
		} else {
			add_action( 'delete_user', array( $this, 'on_delete_user' ), 10, 3 );
		}

		add_action( self::BACKGROUND_HOOK_EVENT, array( $this, 'run_scheduled' ), 10, 4 );
		add_action( self::LEGACY_BACKGROUND_HOOK_EVENT, array( $this, 'run_scheduled' ), 10, 3 );
	}

	/**
	 * Add the NewsMAN eraser to Tools > Erase Personal Data, first in the list: the
	 * WooCommerce erasers remove the phone numbers it reads.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$blog_id = get_current_blog_id();
		if ( ! is_array( $erasers ) || ! ( $this->is_allow( $blog_id ) || $this->is_allow_sms( $blog_id ) ) ) {
			return $erasers;
		}

		unset( $erasers[ self::ERASER_ID ] );
		return array(
			self::ERASER_ID => array(
				'eraser_friendly_name' => __( 'NewsMAN newsletter subscription', 'newsman' ),
				'callback'             => array( $this, 'erase' ),
			),
		) + $erasers;
	}

	/**
	 * Eraser callback: anonymise the email in the NewsMAN list and unsubscribe its phone
	 * numbers from the NewsMAN SMS list of this site.
	 *
	 * @param string $email_address Email address of the erasure request.
	 * @param int    $page          Page number, the eraser always finishes on the first one.
	 * @return array
	 */
	public function erase( $email_address, $page = 1 ) {
		unset( $page );
		$response = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$email   = trim( (string) $email_address );
		$blog_id = get_current_blog_id();
		if ( ! is_email( $email ) ) {
			return $response;
		}

		// The privacy screen shows the messages as HTML.
		if ( $this->is_allow( $blog_id ) ) {
			try {
				if ( self::RESULT_ANONYMISED === $this->anonymise_now( $email, $blog_id ) ) {
					$response['items_removed'] = true;
					$response['messages'][]    = esc_html__( 'The email address was anonymised in the NewsMAN list.', 'newsman' );
				}
			} catch ( \Exception $e ) {
				$this->get_logger()->log_exception( $e );
				$response['items_retained'] = true;
				$response['messages'][]     = esc_html(
					sprintf(
						/* translators: %s: Error message */
						__( 'NewsMAN could not anonymise the email address: %s. Erase the personal data again later.', 'newsman' ),
						$e->getMessage()
					)
				);
			}
		}

		if ( $this->is_allow_sms( $blog_id ) ) {
			$unsubscribed = 0;
			foreach ( $this->get_phones( $email, get_user_by( 'email', $email ) ) as $phone ) {
				try {
					if ( self::RESULT_UNSUBSCRIBED === $this->unsubscribe_phone_now( $phone, $blog_id ) ) {
						++$unsubscribed;
					}
				} catch ( \Exception $e ) {
					$this->get_logger()->log_exception( $e );
					$response['items_retained'] = true;
					$response['messages'][]     = esc_html(
						sprintf(
							/* translators: 1: Phone number, 2: Error message */
							__( 'NewsMAN could not unsubscribe the phone number %1$s: %2$s. Erase the personal data again later.', 'newsman' ),
							$phone,
							$e->getMessage()
						)
					);
				}
			}
			if ( $unsubscribed > 0 ) {
				$response['items_removed'] = true;
				$response['messages'][]    = esc_html(
					sprintf(
						/* translators: %d: Number of phone numbers */
						_n(
							'%d phone number was unsubscribed from the NewsMAN SMS list.',
							'%d phone numbers were unsubscribed from the NewsMAN SMS list.',
							$unsubscribed,
							'newsman'
						),
						$unsubscribed
					)
				);
			}
		}

		return $response;
	}

	/**
	 * A user is deleted (single site).
	 *
	 * @param int           $user_id  User ID.
	 * @param int|null      $reassign User ID that gets the posts, or null.
	 * @param \WP_User|null $user     User object, WordPress 5.5 and newer.
	 * @return void
	 */
	public function on_delete_user( $user_id, $reassign = null, $user = null ) {
		unset( $reassign );
		if ( ! $user instanceof \WP_User ) {
			$user = get_userdata( $user_id );
		}
		$this->erase_user( $user, get_current_blog_id() );
	}

	/**
	 * A user is removed from a site of a network, or deleted from the network. Only the
	 * deletion from the network is sent (see is_no_action_delete()).
	 *
	 * @param int $user_id User ID.
	 * @param int $blog_id Site ID.
	 * @return void
	 */
	public function on_remove_user_from_blog( $user_id, $blog_id ) {
		$this->erase_user( get_userdata( $user_id ), (int) $blog_id );
	}

	/**
	 * Anonymise (or unsubscribe, see the setting) the email and unsubscribe the phone numbers
	 * of a deleted user in the NewsMAN lists of a site. The phone numbers are read now, while
	 * the user and its meta still exist.
	 *
	 * @param \WP_User|false $user    User.
	 * @param int            $blog_id Site ID.
	 * @return void
	 */
	public function erase_user( $user, $blog_id ) {
		try {
			if ( ! $user instanceof \WP_User || empty( $user->user_email ) ) {
				return;
			}

			$allow = ! $this->is_no_action_delete();
			if ( ! $allow ) {
				$this->get_logger()->debug( 'Deleted user ' . $user->user_email . ': nothing sent to NewsMAN, the site is deleted, WooCommerce deletes inactive accounts or the user is only removed from a site of the network.' );
			}

			/**
			 * Whether to anonymise (or unsubscribe) the email and unsubscribe the phone numbers of a
			 * deleted user in NewsMAN.
			 *
			 * @param bool     $allow   Default true, false when a whole network site is deleted,
			 *                          WooCommerce deletes inactive accounts or the user is only
			 *                          removed from a site of a network.
			 * @param \WP_User $user    The user being deleted.
			 * @param int      $blog_id Site ID.
			 */
			if ( ! apply_filters( 'newsman_privacy_erase_deleted_user', $allow, $user, $blog_id ) ) {
				return;
			}

			$phones = $this->is_allow_sms( $blog_id ) ? $this->get_phones( $user->user_email, $user ) : array();
			$this->execute( $user->user_email, $blog_id, $phones, $this->get_delete_mode( $blog_id ) );
		} catch ( \Throwable $e ) {
			$this->get_logger()->error( $e->getMessage() );
		}
	}

	/**
	 * Anonymise (or unsubscribe) an email and unsubscribe phone numbers in the background when
	 * Action Scheduler is used, right away otherwise. Never throws: a NewsMAN error must not stop
	 * the deletion.
	 *
	 * @param string      $email   Email address.
	 * @param int         $blog_id Site ID.
	 * @param array       $phones  Phone numbers, digits only.
	 * @param string|null $mode    self::MODE_ANONYMISE or self::MODE_UNSUBSCRIBE, null for the setting.
	 * @return void
	 */
	public function execute( $email, $blog_id, $phones = array(), $mode = null ) {
		try {
			$mode  = $this->resolve_mode( $mode, $blog_id );
			$email = trim( (string) $email );
			$key   = (int) $blog_id . '|' . strtolower( $email );
			if ( '' === $email || isset( $this->handled[ $key ] ) ) {
				return;
			}
			if ( ! ( $this->is_allow( $blog_id ) || ( $this->is_allow_sms( $blog_id ) && ! empty( $phones ) ) ) ) {
				return;
			}
			$this->handled[ $key ] = true;

			if ( $this->use_action_scheduler( $blog_id ) ) {
				as_schedule_single_action(
					time(),
					self::BACKGROUND_HOOK_EVENT,
					array( $email, (int) $blog_id, array_values( (array) $phones ), $mode ),
					$this->get_action_scheduler()->get_group_subscribe()
				);
				return;
			}

			if ( $this->is_allow( $blog_id ) ) {
				$this->try_once( $this->get_email_method( $mode ), $email, $blog_id );
			}
			if ( $this->is_allow_sms( $blog_id ) ) {
				foreach ( (array) $phones as $phone ) {
					$this->try_once( array( $this, 'unsubscribe_phone_now' ), $phone, $blog_id );
				}
			}
		} catch ( \Throwable $e ) {
			$this->get_logger()->error( $e->getMessage() );
		}
	}

	/**
	 * Action Scheduler callback. Each call is tried one more time after a short wait;
	 * then the action fails so it shows in Tools > Scheduled Actions.
	 *
	 * @param string      $email   Email address.
	 * @param null|int    $blog_id Site ID.
	 * @param array       $phones  Phone numbers, digits only.
	 * @param string|null $mode    self::MODE_ANONYMISE or self::MODE_UNSUBSCRIBE; null (actions
	 *                             scheduled before 3.7.43) for the setting.
	 * @return void
	 * @throws \Exception When NewsMAN fails twice for the email or a phone number.
	 */
	public function run_scheduled( $email, $blog_id = null, $phones = array(), $mode = null ) {
		if ( null === $blog_id ) {
			$blog_id = get_current_blog_id();
		}
		$mode = $this->resolve_mode( $mode, $blog_id );

		$failed = array();
		if ( ! empty( $email ) && $this->is_allow( $blog_id ) && ! $this->try_twice( $this->get_email_method( $mode ), $email, $blog_id ) ) {
			$failed[] = $email;
		}
		if ( $this->is_allow_sms( $blog_id ) ) {
			foreach ( (array) $phones as $phone ) {
				if ( ! $this->try_twice( array( $this, 'unsubscribe_phone_now' ), $phone, $blog_id ) ) {
					$failed[] = $phone;
				}
			}
		}

		if ( ! empty( $failed ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Shown in Tools > Scheduled Actions, escaped there.
			throw new \Exception( 'NewsMAN could not anonymise or unsubscribe: ' . implode( ', ', $failed ) );
		}
	}

	/**
	 * Anonymise an email in the NewsMAN list of a site with subscriber.anonymiseEmail. NewsMAN
	 * replaces the email address with an anonymous one a few seconds later.
	 *
	 * @param string $email   Email address.
	 * @param int    $blog_id Site ID.
	 * @return string self::RESULT_ANONYMISED or self::RESULT_NOT_SUBSCRIBED.
	 * @throws \Exception On NewsMAN errors other than "no such subscriber".
	 */
	public function anonymise_now( $email, $blog_id ) {
		$context = new \Newsman\Service\Context\AnonymiseEmail();
		$context->set_blog_id( $blog_id )
			->set_list_id( $this->config->get_list_id( $blog_id ) )
			->set_email( $email );

		/**
		 * Context of the anonymise sent for erased personal data.
		 *
		 * @param \Newsman\Service\Context\AnonymiseEmail $context Context.
		 * @param int                                      $blog_id Site ID.
		 */
		$context = apply_filters( 'newsman_privacy_anonymise_context', $context, $blog_id );

		try {
			$anonymise = new \Newsman\Service\AnonymiseEmail();
			$anonymise->execute( $context );
		} catch ( \Exception $e ) {
			if ( self::ERROR_NO_SUCH_SUBSCRIBER === (int) $e->getCode() ) {
				$this->get_logger()->info(
					sprintf(
						/* translators: 1: Email */
						esc_html__( 'Erase personal data: %s is not on the NewsMAN list', 'newsman' ),
						$context->get_email()
					)
				);
				return self::RESULT_NOT_SUBSCRIBED;
			}
			throw $e;
		}

		/**
		 * After an email with erased personal data was anonymised in the NewsMAN list.
		 *
		 * @param string $email   Email address.
		 * @param int    $blog_id Site ID.
		 */
		do_action( 'newsman_privacy_anonymised', $context->get_email(), $blog_id );

		return self::RESULT_ANONYMISED;
	}

	/**
	 * Unsubscribe the email of a deleted user from the NewsMAN list of a site with
	 * subscriber.saveUnsubscribe, when "Anonymise in NewsMAN when users are deleted" is off.
	 *
	 * @param string $email   Email address.
	 * @param int    $blog_id Site ID.
	 * @return string self::RESULT_UNSUBSCRIBED or self::RESULT_NOT_SUBSCRIBED.
	 * @throws \Exception On NewsMAN errors other than "no such subscriber".
	 */
	public function unsubscribe_email_now( $email, $blog_id ) {
		$context = new \Newsman\Service\Context\UnsubscribeEmail();
		$context->set_blog_id( $blog_id )
			->set_list_id( $this->config->get_list_id( $blog_id ) )
			->set_email( $email )
			->set_ip( IpAddress::init()->get_ip() );

		/**
		 * Context of the unsubscribe sent for a deleted user when the email is not anonymised.
		 *
		 * @param \Newsman\Service\Context\UnsubscribeEmail $context Context.
		 * @param int                                        $blog_id Site ID.
		 */
		$context = apply_filters( 'newsman_privacy_unsubscribe_context', $context, $blog_id );

		try {
			$unsubscribe = new \Newsman\Service\UnsubscribeEmail();
			$unsubscribe->execute( $context );
		} catch ( \Exception $e ) {
			if ( self::ERROR_NO_SUCH_SUBSCRIBER === (int) $e->getCode() ) {
				$this->get_logger()->info(
					sprintf(
						/* translators: 1: Email */
						esc_html__( 'Erase personal data: %s is not on the NewsMAN list', 'newsman' ),
						$context->get_email()
					)
				);
				return self::RESULT_NOT_SUBSCRIBED;
			}
			throw $e;
		}

		/**
		 * After the email of a deleted user was unsubscribed from the NewsMAN list.
		 *
		 * @param string $email   Email address.
		 * @param int    $blog_id Site ID.
		 */
		do_action( 'newsman_privacy_unsubscribed', $context->get_email(), $blog_id );

		return self::RESULT_UNSUBSCRIBED;
	}

	/**
	 * Unsubscribe a phone number from the NewsMAN SMS list of a site with sms.saveUnsubscribe.
	 *
	 * @param string $phone   Phone number, digits only.
	 * @param int    $blog_id Site ID.
	 * @return string self::RESULT_UNSUBSCRIBED or self::RESULT_NOT_SUBSCRIBED.
	 * @throws \Exception On NewsMAN errors other than "no such subscriber".
	 */
	public function unsubscribe_phone_now( $phone, $blog_id ) {
		$context = new \Newsman\Service\Context\Sms\Unsubscribe();
		$context->set_blog_id( $blog_id )
			->set_list_id( SmsConfig::init()->get_list_id( $blog_id ) )
			->set_telephone( $phone )
			->set_ip( IpAddress::init()->get_ip() );

		/**
		 * Context of the SMS unsubscribe sent for erased personal data.
		 *
		 * @param \Newsman\Service\Context\Sms\Unsubscribe $context Context.
		 * @param int                                       $blog_id Site ID.
		 */
		$context = apply_filters( 'newsman_privacy_unsubscribe_sms_context', $context, $blog_id );

		try {
			$unsubscribe = new \Newsman\Service\Sms\Unsubscribe();
			$unsubscribe->execute( $context );
		} catch ( \Exception $e ) {
			if ( self::ERROR_NO_SUCH_SUBSCRIBER === (int) $e->getCode() ) {
				$this->get_logger()->info(
					sprintf(
						/* translators: 1: Phone number */
						esc_html__( 'Erase personal data: %s is not on the NewsMAN SMS list', 'newsman' ),
						$context->get_telephone()
					)
				);
				return self::RESULT_NOT_SUBSCRIBED;
			}
			throw $e;
		}

		/**
		 * After a phone number with erased personal data was unsubscribed from the NewsMAN SMS list.
		 *
		 * @param string $phone   Phone number.
		 * @param int    $blog_id Site ID.
		 */
		do_action( 'newsman_privacy_unsubscribed_phone', $context->get_telephone(), $blog_id );

		return self::RESULT_UNSUBSCRIBED;
	}

	/**
	 * Phone numbers of a person: the billing phone of the account and the billing phones of
	 * the WooCommerce orders placed with the email address or by the account. Digits only,
	 * the format sent to the SMS list at checkout.
	 *
	 * @param string              $email Email address.
	 * @param \WP_User|false|null $user  User with this email address, if any.
	 * @return array
	 */
	public function get_phones( $email, $user = null ) {
		$phones = array();
		if ( $user instanceof \WP_User ) {
			$phones[] = (string) get_user_meta( $user->ID, 'billing_phone', true );
		}

		if ( function_exists( 'wc_get_orders' ) ) {
			$queries = array( array( 'billing_email' => $email ) );
			if ( $user instanceof \WP_User ) {
				$queries[] = array( 'customer_id' => $user->ID );
			}
			foreach ( $queries as $query ) {
				$orders = wc_get_orders(
					array_merge(
						$query,
						array(
							'type'   => 'shop_order',
							'status' => 'any',
							'limit'  => self::MAX_ORDERS,
						)
					)
				);
				foreach ( (array) $orders as $order ) {
					if ( is_object( $order ) && method_exists( $order, 'get_billing_phone' ) ) {
						$phones[] = (string) $order->get_billing_phone();
					}
				}
			}
		}

		$telephone = new Telephone();
		$clean     = array();
		foreach ( $phones as $phone ) {
			$phone = $telephone->clean( $phone );
			if ( strlen( $phone ) >= self::MIN_PHONE_LENGTH ) {
				$clean[ $phone ] = $phone;
			}
		}

		/**
		 * Phone numbers unsubscribed from the NewsMAN SMS list when personal data is erased.
		 *
		 * @param array               $phones Phone numbers, digits only.
		 * @param string              $email  Email address.
		 * @param \WP_User|false|null $user   User, if any.
		 */
		return array_values( (array) apply_filters( 'newsman_privacy_erase_phones', array_values( $clean ), $email, $user ) );
	}

	/**
	 * Whether the plugin is connected to NewsMAN on the site.
	 *
	 * @param int $blog_id Site ID.
	 * @return bool
	 */
	public function is_allow( $blog_id ) {
		/**
		 * Whether to anonymise in the NewsMAN list the people whose data is erased, and to
		 * add their NewsMAN data to the personal data export.
		 *
		 * @param bool $allow   Default: the plugin is connected to NewsMAN on the site.
		 * @param int  $blog_id Site ID.
		 */
		return (bool) apply_filters( 'newsman_privacy_anonymise_allow', $this->config->is_enabled_with_api( $blog_id ), $blog_id );
	}

	/**
	 * Whether nothing is sent for the user being deleted, read from the call stack: a whole
	 * network site is deleted, WooCommerce deletes inactive accounts, or, on a network, the
	 * account is not deleted from the network (the user is only removed from a site, which is
	 * also what wp_delete_user() does on a network).
	 *
	 * @return bool
	 */
	public function is_no_action_delete() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary -- Only reads which functions delete the user, nothing is output.
		$callers = wp_debug_backtrace_summary( null, 0, false );
		if ( ! is_array( $callers ) ) {
			return false;
		}
		if ( array() !== array_intersect( self::NO_ACTION_CALLERS, $callers ) ) {
			return true;
		}
		return is_multisite() && ! in_array( self::NETWORK_DELETE_CALLER, $callers, true );
	}

	/**
	 * What is sent for the email of a deleted user on a site: the setting "Anonymise in
	 * NewsMAN when users are deleted".
	 *
	 * @param int $blog_id Site ID.
	 * @return string self::MODE_ANONYMISE or self::MODE_UNSUBSCRIBE.
	 */
	public function get_delete_mode( $blog_id ) {
		$mode = $this->config->is_anonymise_on_delete( $blog_id ) ? self::MODE_ANONYMISE : self::MODE_UNSUBSCRIBE;

		/**
		 * What is sent to NewsMAN for the email of a deleted user.
		 *
		 * @param string $mode    'anonymise' or 'unsubscribe'.
		 * @param int    $blog_id Site ID.
		 */
		$mode = apply_filters( 'newsman_privacy_delete_mode', $mode, $blog_id );
		return self::MODE_UNSUBSCRIBE === $mode ? self::MODE_UNSUBSCRIBE : self::MODE_ANONYMISE;
	}

	/**
	 * A valid mode, the setting when none is given.
	 *
	 * @param string|null $mode    Mode.
	 * @param int         $blog_id Site ID.
	 * @return string
	 */
	protected function resolve_mode( $mode, $blog_id ) {
		if ( self::MODE_ANONYMISE === $mode || self::MODE_UNSUBSCRIBE === $mode ) {
			return $mode;
		}
		return $this->get_delete_mode( $blog_id );
	}

	/**
	 * Method that sends the email of a deleted user.
	 *
	 * @param string $mode self::MODE_ANONYMISE or self::MODE_UNSUBSCRIBE.
	 * @return callable
	 */
	protected function get_email_method( $mode ) {
		return self::MODE_UNSUBSCRIBE === $mode ? array( $this, 'unsubscribe_email_now' ) : array( $this, 'anonymise_now' );
	}

	/**
	 * Whether SMS is set up on the site (Use SMS and an SMS list).
	 *
	 * @param int $blog_id Site ID.
	 * @return bool
	 */
	public function is_allow_sms( $blog_id ) {
		/**
		 * Whether to unsubscribe from the NewsMAN SMS list the phone numbers of the people whose data is erased.
		 *
		 * @param bool $allow   Default: SMS is set up on the site.
		 * @param int  $blog_id Site ID.
		 */
		return (bool) apply_filters( 'newsman_privacy_unsubscribe_sms_allow', SmsConfig::init()->is_enabled_with_api( $blog_id ), $blog_id );
	}

	/**
	 * Whether deleted users are sent to NewsMAN in the background. Uses the general
	 * Action Scheduler setting: deleting many users at once must stay fast.
	 *
	 * @param int $blog_id Site ID.
	 * @return bool
	 */
	public function use_action_scheduler( $blog_id ) {
		$action_scheduler = $this->get_action_scheduler();
		return $action_scheduler->exist() &&
			$action_scheduler->is_single_action() &&
			$this->config->use_action_scheduler( $blog_id );
	}

	/**
	 * Call a NewsMAN method once, log the error.
	 *
	 * @param callable $unsubscribe Anonymise or unsubscribe method.
	 * @param string   $value       Email or phone number.
	 * @param int      $blog_id     Site ID.
	 * @return bool False on error.
	 */
	protected function try_once( $unsubscribe, $value, $blog_id ) {
		try {
			call_user_func( $unsubscribe, $value, $blog_id );
			return true;
		} catch ( \Exception $e ) {
			$this->get_logger()->log_exception( $e );
			return false;
		}
	}

	/**
	 * Call a NewsMAN method, and once more after a short wait when it fails.
	 *
	 * @param callable $unsubscribe Anonymise or unsubscribe method.
	 * @param string   $value       Email or phone number.
	 * @param int      $blog_id     Site ID.
	 * @return bool False when both tries failed.
	 */
	protected function try_twice( $unsubscribe, $value, $blog_id ) {
		if ( $this->try_once( $unsubscribe, $value, $blog_id ) ) {
			return true;
		}
		$this->get_logger()->notice( 'Wait ' . number_format( static::WAIT_RETRY_TIMEOUT / 1000000, 2 ) . ' seconds before retry' );
		usleep( static::WAIT_RETRY_TIMEOUT );
		return $this->try_once( $unsubscribe, $value, $blog_id );
	}

	/**
	 * Get the logger.
	 *
	 * @return Logger
	 */
	protected function get_logger() {
		if ( null === $this->logger ) {
			$this->logger = Logger::init();
		}
		return $this->logger;
	}

	/**
	 * Get the Action Scheduler helper.
	 *
	 * @return ActionScheduler
	 */
	protected function get_action_scheduler() {
		if ( null === $this->action_scheduler ) {
			$this->action_scheduler = new ActionScheduler();
		}
		return $this->action_scheduler;
	}
}
