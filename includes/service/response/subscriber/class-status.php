<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman subscriber status constants returned by subscriber.getByEmail.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Service\Response\Subscriber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscriber status values returned in the `status` field of subscriber.getByEmail.
 *
 * @see https://kb.newsman.com/api/1.2/subscriber.getByEmail
 * @class \Newsman\Service\Response\Subscriber\Status
 */
abstract class Status {
	/**
	 * Fully-confirmed subscriber. Safe to call subscriber.update on these rows.
	 */
	public const SUBSCRIBED = 'subscribed';

	/**
	 * Explicitly unsubscribed. Newsman won't deliver to these rows; re-subscribing
	 * requires a fresh subscribe (single or double opt-in).
	 */
	public const UNSUBSCRIBED = 'unsubscribed';

	/**
	 * Awaiting double-optin confirmation. Avoid subscriber.update — it may flip
	 * the row to subscribed and bypass the confirmation handshake.
	 */
	public const PENDING = 'pending';
}
