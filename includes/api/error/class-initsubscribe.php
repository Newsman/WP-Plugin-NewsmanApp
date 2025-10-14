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

namespace Newsman\Api\Error;

use Newsman\Api\Error\Abstract\Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsman API errors for endpoint subscriber.initSubscribe
 *
 * @see https://kb.newsman.com/api/1.2/subscriber.initSubscribe
 * @class \Newsman\Api\Error\InitSubscribe
 */
abstract class InitSubscribe extends Error {
	/**
	 * Too many requests for this subscriber. Can only send once per 10 minutes
	 */
	public const TOO_MANY_REQUESTS = 128;
}
