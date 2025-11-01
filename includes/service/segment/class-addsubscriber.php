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

namespace Newsman\Service\Segment;

use Newsman\Service\Abstract\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client Service Segment add subscriber (email)
 *
 * @class \Newsman\Service\Segment\AddSubscriber
 */
class AddSubscriber extends Service {
	/**
	 * Subscribe telephone number to SMS list Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/segment.addSubscriber
	 */
	public const ENDPOINT = 'segment.addSubscriber';

	/**
	 * Add subscriber ID (email) to segment
	 *
	 * @param \Newsman\Service\Context\Segment\AddSubscriber $context Segment add subscriber context.
	 * @return array|string
	 * @throws \Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$api_context = $this->create_api_context()
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		$this->logger->info(
			sprintf(
				/* translators: 1: Newsman segment ID, 2: Newsman subscriber ID */
				esc_html__( 'Try to add to segment %1$s subscriber ID %2$s', 'newsman' ),
				$context->get_segment_id(),
				$context->get_subscriber_id()
			)
		);

		$client  = $this->create_api_client();
		$context = apply_filters( 'newsman_service_segment_add_subscriber_execute_context', $context );
		$result  = $client->post(
			$api_context,
			array(
				'list_id'       => $api_context->get_list_id(),
				'segment_id'    => $context->get_segment_id(),
				'subscriber_id' => $context->get_subscriber_id(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Newsman segment ID, 2: Newsman subscriber ID */
				esc_html__( 'Added to segment %1$s subscriber ID %2$s', 'newsman' ),
				$context->get_segment_id(),
				$context->get_subscriber_id()
			)
		);

		return $result;
	}
}
