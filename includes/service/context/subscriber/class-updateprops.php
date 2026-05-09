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

use Newsman\Service\Context\UnsubscribeEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service Context Subscriber Update Props
 *
 * Inherits email, ip, list_id, blog_id from UnsubscribeEmail. Adds the properties payload.
 *
 * @class \Newsman\Service\Context\Subscriber\UpdateProps
 */
class UpdateProps extends UnsubscribeEmail {
	/**
	 * Subscriber ID (optional; when provided, subscriber is identified by ID instead of email).
	 *
	 * @var null|int|string
	 */
	protected $subscriber_id;

	/**
	 * Properties to set/overwrite on the subscriber.
	 *
	 * @var array
	 */
	protected $properties = array();

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

	/**
	 * Set properties.
	 *
	 * @param array $properties Properties.
	 * @return $this
	 */
	public function set_properties( $properties ) {
		$this->properties = $properties;
		return $this;
	}

	/**
	 * Get properties.
	 *
	 * @return array
	 */
	public function get_properties() {
		return $this->properties;
	}
}
