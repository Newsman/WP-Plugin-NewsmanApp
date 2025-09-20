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
 * API Client Service SMS Send One
 *
 * @class Newsman_Service_Sms_SendOne
 */
class Newsman_Service_Sms_SendOne extends Newsman_Service_Abstract_Service {
	/**
	 * Get all SMS lists Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/sms.sendone
	 */
	public const ENDPOINT = 'sms.sendone';

	/**
	 * SMS send one
	 *
	 * @param Newsman_Service_Context_Sms_SendOne $context User context.
	 * @return array
	 * @throws Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$api_context = $this->create_api_context()
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		/* translators: 1: Phone number */
		$this->logger->info( sprintf( esc_html__( 'Try to send one SMS to %s', 'newsman' ), $context->get_to() ) );

		$client = $this->create_api_client();
		$result = $client->post(
			$api_context,
			array(
				'list_id' => $api_context->get_list_id(),
				'text'    => $context->get_text(),
				'to'      => $context->get_to(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		/* translators: 1: Phone number */
		$this->logger->info( sprintf( esc_html__( 'Sent SMS to %s', 'newsman' ), $context->get_to() ) );

		return $result;
	}
}
