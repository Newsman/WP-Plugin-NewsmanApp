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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client Service Get by Email from list
 *
 * @class Newsman_Service_GetByEmail
 */
class Newsman_Service_GetByEmail extends Newsman_Service_Abstract_Service {
	/**
	 * Get by email from email list Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/subscriber.getByEmail
	 */
	public const ENDPOINT = 'subscriber.getByEmail';

	/**
	 * Unsubscribe email
	 *
	 * @param Newsman_Service_Context_GetByEmail $context Get by email context.
	 * @return array
	 * @throws Exception Throw exception on errors.
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
				esc_html__( 'Try to get by email %s', 'newsman' ),
				$context->get_email()
			)
		);

		$client = $this->create_api_client();
		$result = $client->post(
			$api_context,
			array(
				'list_id' => $api_context->get_list_id(),
				'email'   => $context->get_email(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Email */
				esc_html__( 'Done get by email %s', 'newsman' ),
				$context->get_email()
			)
		);

		return $result;
	}
}
