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
 * API Client Service Remarketing order Set Purchase Status
 *
 * @class Newsman\Service\SetPurchaseStatus
 */
class SetPurchaseStatus extends Service {
	/**
	 * Order set purchase status Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/remarketing.setPurchaseStatus
	 */
	public const ENDPOINT = 'remarketing.setPurchaseStatus';

	/**
	 * Set order purchase status
	 *
	 * @param Context\SetPurchaseStatus $context Order Set Purchase Status context.
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
				/* translators: 1: Order ID, 2: Order status */
				esc_html__( 'Try to send order %1$s status %2$s', 'newsman' ),
				$context->get_order_id(),
				$context->get_order_status()
			)
		);

		$client = $this->create_api_client();
		$result = $client->get(
			$api_context,
			array(
				'list_id'  => $api_context->get_list_id(),
				'order_id' => $context->get_order_id(),
				'status'   => $context->get_order_status(),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Order ID, 2: Order status */
				esc_html__( 'Sent order %1$s status %2$s', 'newsman' ),
				$context->get_order_id(),
				$context->get_order_status()
			)
		);

		return $result;
	}
}
