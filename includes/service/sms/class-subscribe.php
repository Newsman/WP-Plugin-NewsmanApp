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

namespace Newsman\Service\Sms;

use Newsman\Service\Abstract\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client Service Subscribe Telephone Number to SMS List
 *
 * @class \Newsman\Service\Sms\Subscribe
 */
class Subscribe extends Service {
	/**
	 * Subscribe telephone number to SMS list Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/sms.saveSubscribe
	 */
	public const ENDPOINT = 'sms.saveSubscribe';

	/**
	 * Subscribe telephone number to SMS list
	 *
	 * @param \Newsman\Service\Context\Sms\Subscribe $context SMS subscribe context.
	 * @return array
	 * @throws \Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$api_context = $this->create_api_context()
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		$this->logger->info(
			sprintf(
				/* translators: 1: Telephone number */
				esc_html__( 'Try to subscribe telephone %s', 'newsman' ),
				$context->get_telephone()
			)
		);

		$client = $this->create_api_client();
		$result = $client->post(
			$api_context,
			array(
				'list_id'   => $api_context->get_list_id(),
				'telephone' => $context->get_telephone(),
				'firstname' => $context->get_firstsname(),
				'lastname'  => $context->get_lastsname(),
				'ip'        => $context->get_ip(),
				'props'     => empty( $context->get_properties() ) ? '' : $context->get_properties(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Telephone number */
				esc_html__( 'Subscribed telephone %s', 'newsman' ),
				$context->get_telephone()
			)
		);

		return $result;
	}
}
