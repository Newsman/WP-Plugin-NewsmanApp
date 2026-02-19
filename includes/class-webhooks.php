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

namespace Newsman;

use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes inbound Newsman webhook events (newsman_events POST parameter).
 *
 * Handles subscribe, unsubscribe and import event types. WordPress and
 * WooCommerce have no native newsletter subscription field, so each event
 * is logged and a do_action hook is fired for 3rd-party code to act on.
 *
 * @class \Newsman\Webhooks
 */
class Webhooks {
	/**
	 * Newsman logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->logger = Logger::init();
	}

	/**
	 * Process an array of Newsman webhook events.
	 *
	 * @param array    $events  Array of event objects, each with 'type' and 'data' keys.
	 * @param null|int $blog_id WordPress blog/site ID.
	 * @return array Array of per-event result arrays.
	 */
	public function process( $events, $blog_id = null ) {
		if ( ! is_array( $events ) ) {
			$this->logger->error( esc_html__( 'newsman_events: invalid events format', 'newsman' ) );
			return array( 'error' => 'Invalid events format' );
		}

		$this->logger->info( esc_html__( 'Processing newsman webhook events', 'newsman' ) );

		$result = array();

		foreach ( $events as $event ) {
			if ( ! isset( $event['type'] ) ) {
				continue;
			}

			$this->logger->info(
				sprintf(
					/* translators: 1: Event type */
					esc_html__( 'Processing webhook event type: %s', 'newsman' ),
					$event['type']
				)
			);

			switch ( $event['type'] ) {
				case 'unsub':
					$result[] = $this->process_unsubscribe( $event, $blog_id );
					break;

				case 'subscribe':
				case 'subscribe_confirm':
					$result[] = $this->process_subscribe( $event, $blog_id );
					break;

				case 'import':
					$result[] = $this->process_import( $event, $blog_id );
					break;

				default:
					$this->logger->error(
						sprintf(
							/* translators: 1: Event type */
							esc_html__( 'Unknown webhook type: %s', 'newsman' ),
							$event['type']
						)
					);
					$result[] = array( 'error' => 'Unknown webhook type: ' . $event['type'] );
			}
		}

		return $result;
	}

	/**
	 * Process a subscribe or subscribe_confirm webhook event.
	 *
	 * WordPress and WooCommerce have no native newsletter field.
	 * Fires the newsman_webhook_subscribe action for 3rd-party integration.
	 *
	 * @param array    $event   Webhook event array with 'data.email'.
	 * @param null|int $blog_id WordPress blog/site ID.
	 * @return array
	 */
	protected function process_subscribe( $event, $blog_id ) {
		if ( ! isset( $event['data']['email'] ) ) {
			$this->logger->error( esc_html__( 'webhook subscribe: email not found in event data', 'newsman' ) );
			return array( 'error' => 'Email not found in webhook data' );
		}

		$email = sanitize_email( $event['data']['email'] );

		if ( ! is_email( $email ) ) {
			$this->logger->error(
				sprintf(
					/* translators: 1: Email */
					esc_html__( 'webhook subscribe: invalid email address %s', 'newsman' ),
					$email
				)
			);
			return array(
				'error' => 'Invalid email address',
				'email' => $email,
			);
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Email 2: Blog ID */
				esc_html__( 'webhook subscribe: %1$s, blog %2$d', 'newsman' ),
				$email,
				(int) $blog_id
			)
		);

		/**
		 * Fires when Newsman sends a subscribe or subscribe_confirm webhook event.
		 *
		 * WordPress and WooCommerce have no native newsletter field.
		 * Use this hook to integrate with a 3rd-party newsletter plugin.
		 *
		 * @param string   $email   The email address to subscribe.
		 * @param array    $event   The full webhook event array.
		 * @param null|int $blog_id The WordPress blog/site ID.
		 */
		do_action( 'newsman_webhook_subscribe', $email, $event, $blog_id );

		return array(
			'success' => true,
			'email'   => $email,
		);
	}

	/**
	 * Process an unsub webhook event.
	 *
	 * WordPress and WooCommerce have no native newsletter field.
	 * Fires the newsman_webhook_unsubscribe action for 3rd-party integration.
	 *
	 * @param array    $event   Webhook event array with 'data.email'.
	 * @param null|int $blog_id WordPress blog/site ID.
	 * @return array
	 */
	protected function process_unsubscribe( $event, $blog_id ) {
		if ( ! isset( $event['data']['email'] ) ) {
			$this->logger->error( esc_html__( 'webhook unsubscribe: email not found in event data', 'newsman' ) );
			return array( 'error' => 'Email not found in webhook data' );
		}

		$email = sanitize_email( $event['data']['email'] );

		if ( ! is_email( $email ) ) {
			$this->logger->error(
				sprintf(
					/* translators: 1: Email */
					esc_html__( 'webhook unsubscribe: invalid email address %s', 'newsman' ),
					$email
				)
			);
			return array(
				'error' => 'Invalid email address',
				'email' => $email,
			);
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Email 2: Blog ID */
				esc_html__( 'webhook unsubscribe: %1$s, blog %2$d', 'newsman' ),
				$email,
				(int) $blog_id
			)
		);

		/**
		 * Fires when Newsman sends an unsub webhook event.
		 *
		 * WordPress and WooCommerce have no native newsletter field.
		 * Use this hook to integrate with a 3rd-party newsletter plugin.
		 *
		 * @param string   $email   The email address to unsubscribe.
		 * @param array    $event   The full webhook event array.
		 * @param null|int $blog_id The WordPress blog/site ID.
		 */
		do_action( 'newsman_webhook_unsubscribe', $email, $event, $blog_id );

		return array(
			'success' => true,
			'email'   => $email,
		);
	}

	/**
	 * Process an import webhook event.
	 *
	 * Fires the newsman_webhook_import action for 3rd-party code to handle
	 * post-import notifications from Newsman.
	 *
	 * @param array    $event   Webhook event array.
	 * @param null|int $blog_id WordPress blog/site ID.
	 * @return array
	 */
	protected function process_import( $event, $blog_id ) {
		$this->logger->info(
			sprintf(
				/* translators: 1: Blog ID */
				esc_html__( 'webhook import received, blog %d', 'newsman' ),
				(int) $blog_id
			)
		);

		/**
		 * Fires when Newsman sends an import webhook event.
		 *
		 * @param array    $event   The full webhook event array.
		 * @param null|int $blog_id The WordPress blog/site ID.
		 */
		do_action( 'newsman_webhook_import', $event, $blog_id );

		return array( 'success' => true );
	}
}
