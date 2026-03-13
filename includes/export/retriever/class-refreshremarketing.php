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

namespace Newsman\Export\Retriever;

use Newsman\Export\V1\ApiV1Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle inbound refresh.remarketing API v1 request.
 *
 * Fetches the remarketing script from the Newsman API via
 * remarketing.getSettings and stores it in wp_newsman_options.
 *
 * @class \Newsman\Export\Retriever\RefreshRemarketing
 */
class RefreshRemarketing extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Process refresh remarketing.
	 *
	 * @param array    $data    Request data.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws ApiV1Exception On validation or execution errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		$refresh = isset( $data['refresh'] ) ? (int) $data['refresh'] : 0;
		if ( 1 !== $refresh ) {
			throw new ApiV1Exception( 9001, 'Missing or invalid "refresh" parameter: must be 1', 400 );
		}

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$user_id = $this->config->get_user_id( $blog_id );
		$api_key = $this->config->get_api_key( $blog_id );
		$list_id = $this->config->get_list_id( $blog_id );

		if ( empty( $user_id ) || empty( $api_key ) || empty( $list_id ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			throw new ApiV1Exception( 9002, 'Plugin is not configured: missing user ID, API key, or list ID', 400 );
		}

		try {
			$context = new \Newsman\Service\Context\Configuration\EmailList();
			$context->set_user_id( $user_id )
				->set_api_key( $api_key )
				->set_list_id( $list_id );

			$get_settings = new \Newsman\Service\Configuration\Remarketing\GetSettings();
			$settings     = $get_settings->execute( $context );
		} catch ( \Exception $e ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			$this->logger->log_exception( $e );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ApiV1Exception( 9003, 'Failed to retrieve remarketing settings from Newsman API', 500 );
		}

		if ( empty( $settings ) || ! is_array( $settings ) || empty( $settings['javascript'] ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			throw new ApiV1Exception( 9004, 'Newsman API returned empty remarketing script', 500 );
		}

		$newsman_options = new \Newsman\Options();
		$newsman_options->update_option( 'newsman_scriptjs', $settings['javascript'] );

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Blog ID */
				esc_html__( 'refresh.remarketing: updated newsman_scriptjs, blog %1$d', 'newsman' ),
				(int) $blog_id
			)
		);
		$this->logger->warning( $settings['javascript'] );

		return array( 'status' => 1 );
	}
}
