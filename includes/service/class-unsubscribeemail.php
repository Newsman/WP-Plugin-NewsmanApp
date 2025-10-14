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

namespace Newsman\Service;

use Newsman\Service\Abstract\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client Service Unsubscribe from Email List
 *
 * @class \Newsman\Service\UnsubscribeEmail
 */
class UnsubscribeEmail extends Service {
	/**
	 * Unsubscribe from email list Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/subscriber.saveUnsubscribe
	 */
	public const ENDPOINT = 'subscriber.saveUnsubscribe';

	/**
	 * Unsubscribe email
	 *
	 * @param Context\UnsubscribeEmail $context Unsubscribe email context.
	 * @return array
	 * @throws \Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$this->validate_email( $context->get_email() );

		$api_context = $this->create_api_context()
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		$this->logger->info(
			sprintf(
				/* translators: 1: Email */
				esc_html__( 'Try to unsubscribe email %s', 'newsman' ),
				$context->get_email()
			)
		);

		$client = $this->create_api_client();
		$result = $client->post(
			$api_context,
			array(
				'list_id' => $api_context->get_list_id(),
				'email'   => $context->get_email(),
				'ip'      => $context->get_ip(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Email */
				esc_html__( 'Unsubscribed email %s', 'newsman' ),
				$context->get_email()
			)
		);

		return $result;
	}
}
