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

namespace Newsman\Service\Remarketing;

use Newsman\Service\AbstractService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Class Service Remarketing save orders
 *
 * @class Newsman\Service\Remarketing\SaveOrders
 */
class SaveOrders extends AbstractService {
	/**
	 * Orders save order Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/remarketing.saveOrders
	 */
	public const ENDPOINT = 'remarketing.saveOrders';

	/**
	 * Save orders
	 *
	 * @param \Newsman\Service\Context\Remarketing\SaveOrders $context Save orders context.
	 *
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
				/* translators: 1: Number of orders */
				esc_html__( 'Try to %s save orders', 'newsman' ),
				count( $context->get_orders() )
			)
		);

		$client  = $this->create_api_client();
		$context = apply_filters( 'newsman_service_save_orders_execute_context', $context );
		$result  = $client->post(
			$api_context,
			array(),
			array(
				'list_id' => $api_context->get_list_id(),
				'orders'  => $context->get_orders(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Number of orders */
				esc_html__( 'Saved %s save orders', 'newsman' ),
				count( $context->get_orders() )
			)
		);

		return $result;
	}
}
