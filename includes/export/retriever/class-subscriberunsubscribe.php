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

namespace Newsman\Export\Retriever;

use Newsman\Export\V1\ApiV1Exception;
use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle inbound subscriber.unsubscribe API v1 request.
 *
 * WordPress and WooCommerce have no native newsletter subscription field.
 * This retriever logs the request and fires a do_action hook so that
 * 3rd-party plugins can intercept and act on it.
 *
 * @class \Newsman\Export\Retriever\SubscriberUnsubscribe
 */
class SubscriberUnsubscribe extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Action hook fired when subscriber.unsubscribe is called.
	 */
	public const ACTION_HOOK = 'newsman_subscriber_unsubscribe';

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
		parent::__construct();
		$this->logger = Logger::init();
	}

	/**
	 * Process subscriber unsubscribe.
	 *
	 * @param array    $data    Request data.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws ApiV1Exception On validation errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		$email = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';
		if ( empty( $email ) ) {
			throw new ApiV1Exception( 3200, 'Missing "email" parameter', 400 );
		}

		if ( ! is_email( $email ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ApiV1Exception( 3201, 'Invalid email address: ' . $email, 400 );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Email 2: Blog ID */
				esc_html__( 'subscriber.unsubscribe: %1$s, blog %2$d', 'newsman' ),
				$email,
				(int) $blog_id
			)
		);

		/**
		 * Fires when Newsman requests a subscriber.unsubscribe action.
		 *
		 * WordPress and WooCommerce have no native newsletter field.
		 * Use this hook to integrate with a 3rd-party newsletter plugin.
		 *
		 * @param string   $email   The email address to unsubscribe.
		 * @param null|int $blog_id The WordPress blog/site ID.
		 */
		do_action( self::ACTION_HOOK, $email, $blog_id );

		return array(
			'success' => true,
			'email'   => $email,
		);
	}
}
