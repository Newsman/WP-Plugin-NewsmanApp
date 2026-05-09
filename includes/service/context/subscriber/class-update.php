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

namespace Newsman\Service\Context\Subscriber;

use Newsman\Service\Context\SubscribeEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service Context Subscriber Update
 *
 * Inherits email, firstname, lastname, properties, ip, list_id, blog_id from SubscribeEmail.
 *
 * @class \Newsman\Service\Context\Subscriber\Update
 */
class Update extends SubscribeEmail {
	/**
	 * Subscriber ID (optional; when provided, subscriber is identified by ID instead of email).
	 *
	 * @var null|int|string
	 */
	protected $subscriber_id;

	/**
	 * Set subscriber ID.
	 *
	 * @param int|string $subscriber_id Subscriber ID.
	 * @return $this
	 */
	public function set_subscriber_id( $subscriber_id ) {
		$this->subscriber_id = $subscriber_id;
		return $this;
	}

	/**
	 * Get subscriber ID.
	 *
	 * @return null|int|string
	 */
	public function get_subscriber_id() {
		return $this->subscriber_id;
	}
}
