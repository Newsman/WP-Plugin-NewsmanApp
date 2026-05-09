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

namespace Newsman\Elementor;

use Newsman\Logger;
use Newsman\Service\Context\GetByEmail as GetByEmailContext;
use Newsman\Service\Context\SubscribeEmail as SubscribeEmailContext;
use Newsman\Service\Context\Subscriber\UpdateProps as UpdatePropsContext;
use Newsman\Service\GetByEmail;
use Newsman\Service\SubscribeEmail;
use Newsman\Service\Subscriber\UpdateProps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared subscribe helper used by both the legacy Form widget processor and the
 * Atomic Forms processor.
 *
 * Why this exists: calling `subscriber.saveSubscribe` for an email that already
 * exists on the list does NOT persist the `props` payload — the existing
 * subscriber's properties remain unchanged. To fix this, the flow becomes:
 *
 *   1. Look up the email via `subscriber.getByEmail`.
 *   2. If a subscriber is returned -> call `subscriber.updateProps` with their id.
 *   3. If not found (or the lookup fails) -> call `subscriber.saveSubscribe`
 *      with props inline (which DOES persist props on first creation).
 *
 * @class \Newsman\Elementor\SubscribeHelper
 */
class SubscribeHelper {
	/**
	 * Subscribe an email to a Newsman list with subscriber properties.
	 *
	 * Existing subscribers get their props refreshed via `UpdateProps`. New
	 * subscribers are created via `SubscribeEmail` (saveSubscribe). The caller
	 * receives any exception thrown by the chosen branch — `GetByEmail` failures
	 * are swallowed and treated as "not found, create instead".
	 *
	 * @param int    $blog_id    Current WP blog ID.
	 * @param string $list_id    Newsman list ID.
	 * @param string $email      Subscriber email address.
	 * @param array  $properties Custom properties to set/refresh on the subscriber.
	 * @param string $ip         Client IP for audit (passed to saveSubscribe only).
	 * @return void
	 * @throws \Exception When SubscribeEmail or UpdateProps fail.
	 */
	public static function subscribe_with_props( $blog_id, $list_id, $email, $properties, $ip = '' ) {
		$subscriber_id = self::lookup_subscriber_id( $blog_id, $list_id, $email );

		if ( null !== $subscriber_id ) {
			self::update_props( $blog_id, $list_id, $email, $subscriber_id, $properties );
			return;
		}

		self::save_subscribe( $blog_id, $list_id, $email, $properties, $ip );
	}

	/**
	 * Look up a subscriber id by email + list. Returns null on any failure
	 * ("subscriber not found" is the dominant case but network/auth failures
	 * fall here too — the caller falls back to saveSubscribe in either case).
	 *
	 * @param int    $blog_id WP blog ID.
	 * @param string $list_id Newsman list ID.
	 * @param string $email   Subscriber email.
	 * @return int|string|null Subscriber id when found; null otherwise.
	 */
	protected static function lookup_subscriber_id( $blog_id, $list_id, $email ) {
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

			if ( ! empty( $result['id'] ) ) {
				return $result['id'];
			}
			if ( ! empty( $result['subscriber_id'] ) ) {
				return $result['subscriber_id'];
			}
		} catch ( \Exception $e ) {
			Logger::init()->info(
				sprintf(
					/* translators: 1: Email, 2: error message. */
					esc_html__( 'Newsman: GetByEmail did not return %1$s, will saveSubscribe (%2$s).', 'newsman' ),
					$email,
					$e->getMessage()
				)
			);
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
	 * @return void
	 * @throws \Exception On API error.
	 */
	protected static function save_subscribe( $blog_id, $list_id, $email, $properties, $ip ) {
		$context = new SubscribeEmailContext();
		$context->set_blog_id( $blog_id )
			->set_list_id( $list_id )
			->set_email( $email )
			->set_ip( $ip )
			->set_properties( $properties );

		$service = new SubscribeEmail();
		$service->set_blog_id( $blog_id );
		$service->execute( $context );
	}
}
