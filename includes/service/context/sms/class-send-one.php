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

namespace Newsman\Service\Context\Sms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Service Context SMS Send One
 *
 * @class \Newsman\Service\Context\Sms\SendOne
 */
class SendOne extends \Newsman\Service\Context\Blog {
	/**
	 * Phone number
	 *
	 * @var string
	 */
	protected $to;

	/**
	 * Text in SMS
	 *
	 * @var string
	 */
	protected $text;

	/**
	 * Set phone number
	 *
	 * @param string $to Phone number.
	 * @return $this
	 */
	public function set_to( $to ) {
		$this->to = $to;
		return $this;
	}

	/**
	 * Get phone number
	 *
	 * @return string
	 */
	public function get_to() {
		return $this->to;
	}

	/**
	 * Set SMS text
	 *
	 * @param string $text SMS text.
	 * @return $this
	 */
	public function set_text( $text ) {
		$this->text = $text;
		return $this;
	}

	/**
	 * Get SMS text
	 *
	 * @return string
	 */
	public function get_text() {
		return $this->text;
	}
}
