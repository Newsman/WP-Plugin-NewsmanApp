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

namespace Newsman\Service\Configuration;

use Newsman\Service\AbstractService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Class Service Configuration Update Feed
 *
 * @class \Newsman\Service\Configuration\UpdateFeed
 */
class UpdateFeed extends AbstractService {
	/**
	 * Update a feed in Newsman
	 *
	 * @see https://kb.newsman.com/api/1.2/feeds.updateFeed
	 */
	public const ENDPOINT = 'feeds.updateFeed';

	/**
	 * Update a feed
	 *
	 * @param \Newsman\Service\Context\Configuration\UpdateFeed $context Update Feed context.
	 * @return array|string
	 * @throws \Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		if ( empty( $context->get_list_id() ) ) {
			$e = new \Exception( esc_html__( 'List ID is required.', 'newsman' ) );
			$this->logger->error( $e );
			throw $e;
		}

		$api_context = $this->create_api_context()
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		/* translators: 1: Feed ID */
		$this->logger->info( sprintf( esc_html__( 'Try to update feed %s', 'newsman' ), $context->get_list_id() ) );

		$client  = $this->create_api_client();
		$context = apply_filters( 'newsman_service_configuration_update_feed_execute_context', $context );
		$result  = $client->post(
			$api_context,
			array(),
			array(
				'list_id' => $api_context->get_list_id(),
				'feed_id' => $context->get_feed_id(),
				'props'   => $context->get_properties(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		/* translators: 1: Feed ID */
		$this->logger->info( sprintf( esc_html__( 'Updated the feed %s', 'newsman' ), $context->get_list_id() ) );

		return $result;
	}
}
