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

namespace Newsman\Service\Configuration\Integration;

use Newsman\Service\AbstractService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Class Service Configuration Integration SaveListIntegrationSetup
 *
 * @class \Newsman\Service\Configuration\Integration\SaveListIntegrationSetup
 * @see https://kb.newsman.ro/api/1.2/integration.saveListIntegrationSetup
 */
class SaveListIntegrationSetup extends AbstractService {
	/**
	 * Saves integration setup for a list via API in Newsman
	 *
	 * @see https://kb.newsman.ro/api/1.2/integration.saveListIntegrationSetup
	 */
	public const ENDPOINT = 'integration.saveListIntegrationSetup';

	/**
	 * Save list integration setup in Newsman
	 *
	 * @param \Newsman\Service\Context\Configuration\SaveListIntegrationSetup $context Integration setup context.
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
			->set_user_id( $context->get_user_id() )
			->set_api_key( $context->get_api_key() )
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		$this->logger->info(
			sprintf(
				/* translators: 1: list ID, 2: integration name */
				esc_html__( 'Saving list integration setup for list %1$s, integration %2$s', 'newsman' ),
				$context->get_list_id(),
				$context->get_integration()
			)
		);

		$client  = $this->create_api_client();
		$context = apply_filters( 'newsman_service_configuration_integration_save_list_integration_setup_execute_context', $context );
		$result  = $client->post(
			$api_context,
			array(),
			array(
				'list_id'     => $api_context->get_list_id(),
				'integration' => $context->get_integration(),
				'payload'     => $context->get_payload(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: list ID, 2: integration name */
				esc_html__( 'Saved list integration setup for list %1$s, integration %2$s', 'newsman' ),
				$context->get_list_id(),
				$context->get_integration()
			)
		);

		return $result;
	}
}
