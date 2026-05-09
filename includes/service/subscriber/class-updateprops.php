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

namespace Newsman\Service\Subscriber;

use Newsman\Service\AbstractService;
use Newsman\Service\Context\Subscriber\UpdateProps as UpdatePropsContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Class Service Subscriber Update Properties
 *
 * @class \Newsman\Service\Subscriber\UpdateProps
 */
class UpdateProps extends AbstractService {
	/**
	 * Update subscriber properties Newsman API endpoint.
	 *
	 * @see https://kb.newsman.com/api/1.2/subscriber.updateProps
	 */
	public const ENDPOINT = 'subscriber.updateProps';

	/**
	 * Update subscriber properties.
	 *
	 * @param UpdatePropsContext $context Subscriber update-props context.
	 * @return array|string
	 * @throws \Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$subscriber_id = $context->get_subscriber_id();
		if ( empty( $subscriber_id ) ) {
			$this->validate_email( $context->get_email() );
		}

		$api_context = $this->create_api_context()
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		$this->logger->info(
			sprintf(
				/* translators: 1: Email or subscriber ID */
				esc_html__( 'Try to update subscriber properties for %s', 'newsman' ),
				empty( $subscriber_id ) ? $context->get_email() : (string) $subscriber_id
			)
		);

		$client  = $this->create_api_client();
		$context = apply_filters( 'newsman_service_subscriber_updateprops_execute_context', $context );
		$result  = $client->post(
			$api_context,
			array(),
			array(
				'list_id'       => $api_context->get_list_id(),
				'subscriber_id' => empty( $subscriber_id ) ? '' : $subscriber_id,
				'email'         => $context->get_email(),
				'props'         => empty( $context->get_properties() ) ? '' : $context->get_properties(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Email or subscriber ID */
				esc_html__( 'Updated subscriber properties for %s', 'newsman' ),
				empty( $subscriber_id ) ? $context->get_email() : (string) $subscriber_id
			)
		);

		return $result;
	}
}
