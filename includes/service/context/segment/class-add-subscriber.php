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

namespace Newsman\Service\Context\Segment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Context Segment Add Subscriber
 *
 * @class \Newsman\Service\Context\Segment\AddSubscriber
 */
class AddSubscriber extends \Newsman\Service\Context\Blog {
	/**
	 * Segment ID
	 *
	 * @var string|int
	 */
	protected $segment_id;

	/**
	 * Subscriber ID
	 *
	 * @var string
	 */
	protected $subscriber_id;

	/**
	 * Set segment ID
	 *
	 * @param string $segment_id Segment ID.
	 * @return $this
	 */
	public function set_segment_id( $segment_id ) {
		$this->segment_id = $segment_id;
		return $this;
	}

	/**
	 * Get segment ID
	 *
	 * @return string
	 */
	public function get_segment_id() {
		return $this->segment_id;
	}

	/**
	 * Set subscriber ID
	 *
	 * @param string $subscriber_id Newsman subscriber ID.
	 * @return $this
	 */
	public function set_subscriber_id( string $subscriber_id ) {
		$this->subscriber_id = $subscriber_id;
		return $this;
	}

	/**
	 * Get Newsman subscriber ID
	 *
	 * @return string
	 */
	public function get_subscriber_id() {
		return $this->subscriber_id;
	}
}
