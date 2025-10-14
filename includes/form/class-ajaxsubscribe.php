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

namespace Newsman\Form;

use Newsman\Config;
use Newsman\Logger;
use Newsman\User\IpAddress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intercept AJAX requests. Subscribe to newsletter if specific conditions are meet.
 *
 * @class Newsman\Form\AjaxSubscribe
 * @deprecated since 3.0.0
 */
class AjaxSubscribe {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Config
	 *
	 * @var IpAddress
	 */
	protected $user_ip;

	/**
	 * Logger
	 *
	 * @var Logger|null
	 */
	protected $logger;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config  = Config::init();
		$this->user_ip = IpAddress::init();
		$this->logger  = Logger::init();
	}

	/**
	 * Get class instance
	 *
	 * @return self AjaxSubscribe
	 */
	public static function init() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new AjaxSubscribe();
		}

		return $instance;
	}

	/**
	 * Precess ajax request for the subscription form.
	 * Initializes the subscription process for a new user.
	 *
	 * @return void
	 */
	public function subscribe() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! ( isset( $_POST['email'] ) && ! empty( $_POST['email'] ) ) ) {
			die();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['email'] ) ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$lastname = isset( $_POST['name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$firstname = isset( $_POST['prename'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['prename'] ) ) ) : '';

		$list_id = $this->config->get_list_id();

		try {
			if ( $this->newsman_list_email_exists( $email, $list_id ) ) {
				$message = esc_html( 'E-mail address is already subscribed.' );
				$this->send_message_front( 'error', $message );
				die();
			}

			$context = new \Newsman\Service\Context\InitSubscribeEmail();
			$context->set_list_id( $list_id )
				->set_email( $email )
				->set_firstname( $firstname )
				->set_lastname( $lastname )
				->set_ip( $this->user_ip->get_ip() );
			$subscribe_email = new \Newsman\Service\InitSubscribeEmail();
			$subscribe_email->execute( $context );

			$message = get_option( 'newsman_widget_confirm' );
			$this->send_message_front( 'success', $message );

		} catch ( \Exception $e ) {
			$this->logger->log( 'error', $e->getMessage() );
			$message = get_option( 'newsman_widget_infirm' );
			$this->send_message_front( 'error', $message );
		}

		die();
	}

	/**
	 * Check if email is already subscriber in Newsman.
	 *
	 * @param string $email Email to verify.
	 * @param string $list_id List ID.
	 * @return bool
	 */
	public function newsman_list_email_exists( $email, $list_id ) {
		try {
			$context = new \Newsman\Service\Context\GetByEmail();
			$context->set_list_id( $list_id )
				->set_email( $email );
			$get_by_email = new \Newsman\Service\GetByEmail();
			$result       = $get_by_email->execute( $context );

			if ( is_array( $result ) && 'subscribed' === $result['status'] ) {
				return true;
			}
		} catch ( \Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Creates and return a message for frontend (because of the echo statement).
	 *
	 * @param string $status       The status of the message (the css class of the message).
	 * @param string $message      The actual message.
	 * @return void
	 */
	public function send_message_front( $status, $message ) {
		$this->message = wp_json_encode(
			array(
				'status'  => $status,
				'message' => $message,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->message;
	}
}
