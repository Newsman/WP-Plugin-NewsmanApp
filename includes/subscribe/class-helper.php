<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman shared subscribe helper.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Subscribe;

use Newsman\Logger;
use Newsman\Service\Context\GetByEmail as GetByEmailContext;
use Newsman\Service\Context\InitSubscribeEmail as InitSubscribeEmailContext;
use Newsman\Service\Context\SubscribeEmail as SubscribeEmailContext;
use Newsman\Service\Context\Subscriber\Update as UpdateContext;
use Newsman\Service\Context\Subscriber\UpdateProps as UpdatePropsContext;
use Newsman\Service\GetByEmail;
use Newsman\Service\InitSubscribeEmail;
use Newsman\Service\SubscribeEmail;
use Newsman\Service\Subscriber\Update as UpdateService;
use Newsman\Service\Subscriber\UpdateProps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared subscribe helper used by every form-source integration (Elementor legacy/atomic
 * Form widgets, Contact Form 7, future ones).
 *
 * Why this exists: calling `subscriber.saveSubscribe` for an email that already exists on
 * the list does NOT persist the `props` payload — the existing subscriber's properties
 * remain unchanged. To fix this, the flow becomes:
 *
 *   1. Look up the email via `subscriber.getByEmail`.
 *   2. If a subscriber is returned -> call `subscriber.updateProps` with their id.
 *   3. If not found (or the lookup fails) -> call `subscriber.saveSubscribe` with props
 *      inline (which DOES persist props on first creation).
 *
 * @class \Newsman\Subscribe\Helper
 */
class Helper {
	/**
	 * Subscribe an email to a Newsman list with subscriber properties.
	 *
	 * Existing subscribers get their props refreshed via `UpdateProps` (mode-agnostic).
	 * New subscribers are created via `SubscribeEmail` (single opt-in / saveSubscribe)
	 * or `InitSubscribeEmail` (double opt-in / initSubscribe). The caller receives any
	 * exception thrown by the chosen branch — `GetByEmail` failures are swallowed and
	 * treated as "not found, create instead".
	 *
	 * @param int    $blog_id    Current WP blog ID.
	 * @param string $list_id    Newsman list ID.
	 * @param string $email      Subscriber email address.
	 * @param array  $properties Custom properties to set/refresh on the subscriber.
	 * @param string $ip         Client IP for audit (passed to saveSubscribe/initSubscribe).
	 * @param string $optin_mode `'single'` (default) → saveSubscribe; `'double'` → initSubscribe.
	 * @param string $firstname  Optional subscriber first name (sets context->set_firstname).
	 * @param string $lastname   Optional subscriber last name (sets context->set_lastname).
	 * @return void
	 * @throws \Exception When SubscribeEmail, InitSubscribeEmail, or UpdateProps fail.
	 */
	public static function subscribe_with_props( $blog_id, $list_id, $email, $properties, $ip = '', $optin_mode = 'single', $firstname = '', $lastname = '' ) {
		$optin_mode = ( 'double' === $optin_mode ) ? 'double' : 'single';

		/**
		 * Filter the resolved opt-in mode before the subscribe runs.
		 *
		 * @param string $optin_mode `'single'` or `'double'`.
		 * @param int    $blog_id    Current WP blog ID.
		 * @param string $list_id    Newsman list ID.
		 * @param string $email      Subscriber email.
		 * @param array  $properties Subscriber properties.
		 * @param string $ip         Client IP.
		 */
		$optin_mode = apply_filters( 'newsman_subscribe_optin_mode', $optin_mode, $blog_id, $list_id, $email, $properties, $ip );
		$optin_mode = ( 'double' === $optin_mode ) ? 'double' : 'single';
		/**
		 * Filter the Newsman list ID before lookup/subscribe.
		 *
		 * Allows routing the subscription to a different list based on context
		 * (blog, email, properties, IP).
		 *
		 * @param string $list_id    Newsman list ID resolved from settings.
		 * @param int    $blog_id    Current WP blog ID.
		 * @param string $email      Subscriber email.
		 * @param array  $properties Subscriber properties.
		 * @param string $ip         Client IP.
		 */
		$list_id = apply_filters( 'newsman_subscribe_list_id', $list_id, $blog_id, $email, $properties, $ip );

		/**
		 * Filter the subscriber properties before send.
		 *
		 * @param array  $properties Subscriber properties.
		 * @param int    $blog_id    Current WP blog ID.
		 * @param string $list_id    Newsman list ID.
		 * @param string $email      Subscriber email.
		 * @param string $ip         Client IP.
		 */
		$properties = apply_filters( 'newsman_subscribe_properties', $properties, $blog_id, $list_id, $email, $ip );

		/**
		 * Filter the subscriber firstname/lastname before send.
		 *
		 * The returned array must contain `firstname` and `lastname` string keys (extra
		 * keys are ignored). Empty strings disable the corresponding context setter.
		 *
		 * @param array  $names      `[ 'firstname' => ..., 'lastname' => ... ]`.
		 * @param int    $blog_id    Current WP blog ID.
		 * @param string $list_id    Newsman list ID.
		 * @param string $email      Subscriber email.
		 * @param array  $properties Subscriber properties.
		 * @param string $ip         Client IP.
		 */
		$names     = apply_filters(
			'newsman_subscribe_names',
			array(
				'firstname' => (string) $firstname,
				'lastname'  => (string) $lastname,
			),
			$blog_id,
			$list_id,
			$email,
			$properties,
			$ip
		);
		$firstname = isset( $names['firstname'] ) ? (string) $names['firstname'] : '';
		$lastname  = isset( $names['lastname'] ) ? (string) $names['lastname'] : '';

		/**
		 * Filter whether to perform the subscribe at all.
		 *
		 * Return false to short-circuit and skip every API call.
		 *
		 * @param bool   $should     Default true.
		 * @param int    $blog_id    Current WP blog ID.
		 * @param string $list_id    Newsman list ID.
		 * @param string $email      Subscriber email.
		 * @param array  $properties Subscriber properties.
		 * @param string $ip         Client IP.
		 */
		if ( ! apply_filters( 'newsman_should_subscribe', true, $blog_id, $list_id, $email, $properties, $ip ) ) {
			return;
		}

		/**
		 * Fires before any Newsman API call is made for this subscribe.
		 *
		 * @param int    $blog_id    Current WP blog ID.
		 * @param string $list_id    Newsman list ID.
		 * @param string $email      Subscriber email.
		 * @param array  $properties Subscriber properties.
		 * @param string $ip         Client IP.
		 */
		do_action( 'newsman_before_subscribe', $blog_id, $list_id, $email, $properties, $ip );

		$lookup = self::lookup_subscriber( $blog_id, $list_id, $email );

		if ( null !== $lookup ) {
			$subscriber_id = $lookup['id'];
			$status        = $lookup['status'];

			// subscriber.update refreshes firstname/lastname (and email/ip). Only run it
			// when (a) we actually have a firstname or lastname to set, and (b) the row
			// is fully confirmed — touching a pending double-optin row could flip it.
			$has_names = ( '' !== $firstname || '' !== $lastname );
			if ( $has_names && 'subscribed' === $status ) {
				try {
					self::update_subscriber( $blog_id, $list_id, $email, $subscriber_id, $firstname, $lastname, $ip, $properties );
				} catch ( \Exception $e ) {
					do_action( 'newsman_subscribe_failed', $e, 'update_subscriber', $blog_id, $list_id, $email, $properties, $ip );
					throw $e;
				}
			}

			try {
				self::update_props( $blog_id, $list_id, $email, $subscriber_id, $properties );
			} catch ( \Exception $e ) {
				/**
				 * Fires when a Newsman subscribe attempt fails.
				 *
				 * Stage values: 'lookup' (swallowed), 'update_subscriber' (rethrown),
				 * 'update_props' (rethrown), 'save_subscribe' (rethrown),
				 * 'init_subscribe' (rethrown).
				 *
				 * @param \Exception $e          Caught exception.
				 * @param string     $stage      Pipeline stage that failed.
				 * @param int        $blog_id    Current WP blog ID.
				 * @param string     $list_id    Newsman list ID.
				 * @param string     $email      Subscriber email.
				 * @param array      $properties Subscriber properties.
				 * @param string     $ip         Client IP.
				 */
				do_action( 'newsman_subscribe_failed', $e, 'update_props', $blog_id, $list_id, $email, $properties, $ip );
				throw $e;
			}

			/**
			 * Fires after props were refreshed on an existing subscriber.
			 *
			 * @param int|string $subscriber_id Subscriber id from GetByEmail.
			 * @param int        $blog_id       Current WP blog ID.
			 * @param string     $list_id       Newsman list ID.
			 * @param string     $email         Subscriber email.
			 * @param array      $properties    Properties pushed to the subscriber.
			 */
			do_action( 'newsman_props_updated', $subscriber_id, $blog_id, $list_id, $email, $properties );
			return;
		}

		if ( 'double' === $optin_mode ) {
			try {
				self::save_init_subscribe( $blog_id, $list_id, $email, $properties, $ip, $firstname, $lastname );
			} catch ( \Exception $e ) {
				do_action( 'newsman_subscribe_failed', $e, 'init_subscribe', $blog_id, $list_id, $email, $properties, $ip );
				throw $e;
			}

			/**
			 * Fires after initSubscribe was called for a new (or pending) subscriber.
			 *
			 * @param int    $blog_id    Current WP blog ID.
			 * @param string $list_id    Newsman list ID.
			 * @param string $email      Subscriber email.
			 * @param array  $properties Properties attached on creation.
			 * @param string $ip         Client IP.
			 */
			do_action( 'newsman_init_subscribed', $blog_id, $list_id, $email, $properties, $ip );
			return;
		}

		try {
			self::save_subscribe( $blog_id, $list_id, $email, $properties, $ip, $firstname, $lastname );
		} catch ( \Exception $e ) {
			do_action( 'newsman_subscribe_failed', $e, 'save_subscribe', $blog_id, $list_id, $email, $properties, $ip );
			throw $e;
		}

		/**
		 * Fires after a new subscriber was created via saveSubscribe.
		 *
		 * @param int    $blog_id    Current WP blog ID.
		 * @param string $list_id    Newsman list ID.
		 * @param string $email      Subscriber email.
		 * @param array  $properties Properties attached on creation.
		 * @param string $ip         Client IP.
		 */
		do_action( 'newsman_subscribed', $blog_id, $list_id, $email, $properties, $ip );
	}

	/**
	 * Look up a subscriber by email + list. Returns `[ 'id' => ..., 'status' => ... ]`
	 * when GetByEmail returns a row, null on any failure ("subscriber not found" is the
	 * dominant case but network/auth failures fall here too — the caller falls back to
	 * saveSubscribe / initSubscribe in either case).
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param string $list_id Newsman list ID.
	 * @param string $email   Subscriber email.
	 * @return array{id:int|string,status:string}|null
	 */
	protected static function lookup_subscriber( $blog_id, $list_id, $email ) {
		try {
			$context = new GetByEmailContext();
			$context->set_blog_id( $blog_id )
				->set_list_id( $list_id )
				->set_email( $email );

			$service = new GetByEmail();
			$service->set_blog_id( $blog_id );
			$result = $service->execute( $context );

			if ( ! is_array( $result ) ) {
				return null;
			}

			$subscriber_id = null;
			if ( ! empty( $result['id'] ) ) {
				$subscriber_id = $result['id'];
			} elseif ( ! empty( $result['subscriber_id'] ) ) {
				$subscriber_id = $result['subscriber_id'];
			}
			if ( null === $subscriber_id ) {
				return null;
			}

			$status = isset( $result['status'] ) ? (string) $result['status'] : '';
			return array(
				'id'     => $subscriber_id,
				'status' => $status,
			);
		} catch ( \Exception $e ) {
			Logger::init()->info(
				sprintf(
					/* translators: 1: Email, 2: error message. */
					esc_html__( 'Newsman: GetByEmail did not return %1$s, will saveSubscribe (%2$s).', 'newsman' ),
					$email,
					$e->getMessage()
				)
			);
			do_action( 'newsman_subscribe_failed', $e, 'lookup', $blog_id, $list_id, $email, array(), '' );
		}

		return null;
	}

	/**
	 * Push properties onto an existing subscriber via subscriber.updateProps.
	 *
	 * @param int        $blog_id       WP blog ID.
	 * @param string     $list_id       Newsman list ID.
	 * @param string     $email         Subscriber email.
	 * @param int|string $subscriber_id Subscriber id from GetByEmail.
	 * @param array      $properties    Properties to set/refresh.
	 * @return void
	 * @throws \Exception On API error.
	 */
	protected static function update_props( $blog_id, $list_id, $email, $subscriber_id, $properties ) {
		$context = new UpdatePropsContext();
		$context->set_blog_id( $blog_id )
			->set_list_id( $list_id )
			->set_email( $email )
			->set_subscriber_id( $subscriber_id )
			->set_properties( $properties );

		$service = new UpdateProps();
		$service->set_blog_id( $blog_id );
		$service->execute( $context );
	}

	/**
	 * Subscribe an email via subscriber.saveSubscribe with props inline.
	 *
	 * @param int    $blog_id    WP blog ID.
	 * @param string $list_id    Newsman list ID.
	 * @param string $email      Subscriber email.
	 * @param array  $properties Properties to attach on creation.
	 * @param string $ip         Client IP for audit.
	 * @param string $firstname  Subscriber firstname; empty string skips the setter.
	 * @param string $lastname   Subscriber lastname; empty string skips the setter.
	 * @return void
	 * @throws \Exception On API error.
	 */
	protected static function save_subscribe( $blog_id, $list_id, $email, $properties, $ip, $firstname = '', $lastname = '' ) {
		$context = new SubscribeEmailContext();
		$context->set_blog_id( $blog_id )
			->set_list_id( $list_id )
			->set_email( $email )
			->set_ip( $ip )
			->set_properties( $properties );

		if ( '' !== $firstname ) {
			$context->set_firstname( $firstname );
		}
		if ( '' !== $lastname ) {
			$context->set_lastname( $lastname );
		}

		$service = new SubscribeEmail();
		$service->set_blog_id( $blog_id );
		$service->execute( $context );
	}

	/**
	 * Trigger a double-optin confirmation email via subscriber.initSubscribe with props inline.
	 *
	 * @param int    $blog_id    WP blog ID.
	 * @param string $list_id    Newsman list ID.
	 * @param string $email      Subscriber email.
	 * @param array  $properties Properties to attach on creation.
	 * @param string $ip         Client IP for audit.
	 * @param string $firstname  Subscriber firstname; empty string skips the setter.
	 * @param string $lastname   Subscriber lastname; empty string skips the setter.
	 * @return void
	 * @throws \Exception On API error (e.g. 128 too-many-requests).
	 */
	protected static function save_init_subscribe( $blog_id, $list_id, $email, $properties, $ip, $firstname = '', $lastname = '' ) {
		$context = new InitSubscribeEmailContext();
		$context->set_blog_id( $blog_id )
			->set_list_id( $list_id )
			->set_email( $email )
			->set_ip( $ip )
			->set_properties( $properties );

		if ( '' !== $firstname ) {
			$context->set_firstname( $firstname );
		}
		if ( '' !== $lastname ) {
			$context->set_lastname( $lastname );
		}

		$service = new InitSubscribeEmail();
		$service->set_blog_id( $blog_id );
		$service->execute( $context );
	}

	/**
	 * Refresh firstname/lastname (and email/ip) on an existing fully-subscribed row via
	 * subscriber.update. The caller must gate this on status === 'subscribed' — running
	 * it on a pending double-optin row is unsafe (it could flip the row to subscribed).
	 *
	 * @param int        $blog_id       WP blog ID.
	 * @param string     $list_id       Newsman list ID.
	 * @param string     $email         Subscriber email.
	 * @param int|string $subscriber_id Subscriber id from GetByEmail.
	 * @param string     $firstname     Subscriber firstname (empty skips the setter).
	 * @param string     $lastname      Subscriber lastname (empty skips the setter).
	 * @param string     $ip            Client IP for audit.
	 * @param array      $properties    Properties (passed through; subscriber.updateProps
	 *                                  follows so this is mostly informational).
	 * @return void
	 * @throws \Exception On API error.
	 */
	protected static function update_subscriber( $blog_id, $list_id, $email, $subscriber_id, $firstname, $lastname, $ip, $properties ) {
		$context = new UpdateContext();
		$context->set_blog_id( $blog_id )
			->set_list_id( $list_id )
			->set_email( $email )
			->set_subscriber_id( $subscriber_id )
			->set_ip( $ip )
			->set_properties( $properties );

		if ( '' !== $firstname ) {
			$context->set_firstname( $firstname );
		}
		if ( '' !== $lastname ) {
			$context->set_lastname( $lastname );
		}

		$service = new UpdateService();
		$service->set_blog_id( $blog_id );
		$service->execute( $context );
	}

	/**
	 * Translation-register stub for API error messages we pass through to the user.
	 *
	 * The form processors render `esc_html__( $e->getMessage(), 'newsman' )` so the
	 * translation is only applied when the API's literal English message matches a
	 * known `msgid`. Listing the strings here gives `wp i18n make-pot` a static call
	 * to extract; the method itself is never invoked.
	 *
	 * @return void
	 */
	protected static function register_api_translatable_strings() {
		/* translators: surfaced to the user when subscriber.initSubscribe returns API error 128. */
		__( 'Too many requests for this subscriber. Can only send once per 10 minutes', 'newsman' );
	}
}
