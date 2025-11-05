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
 * API Class Service Remarketing save order
 *
 * @class Newsman\Service\Remarketing\SaveOrder
 */
class SaveOrder extends AbstractService {
	/**
	 * Order save order Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/remarketing.saveOrder
	 */
	public const ENDPOINT = 'remarketing.saveOrder';

	/**
	 * Save order
	 *
	 * @param \Newsman\Service\Context\Remarketing\SaveOrder $context Save order context.
	 *
	 * @return array|string
	 * @throws \Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$api_context = $this->create_api_context()
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		$details  = $context->get_order_details();
		$order_id = 'unknown';
		if ( is_array( $details ) && ! empty( $details['order_no'] ) ) {
			$order_id = $details['order_no'];
		}
		$this->logger->info(
			sprintf(
				/* translators: 1: Order ID */
				esc_html__( 'Try to save order %s', 'newsman' ),
				$order_id
			)
		);

		$client  = $this->create_api_client();
		$context = apply_filters( 'newsman_service_save_order_execute_context', $context );
		$result  = $client->get(
			$api_context,
			array(
				'list_id'        => $api_context->get_list_id(),
				'order_details'  => $context->get_order_details(),
				'order_products' => $context->get_order_products(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Order ID */
				esc_html__( 'Saved order %s', 'newsman' ),
				$order_id
			)
		);

		return $result;
	}
}
