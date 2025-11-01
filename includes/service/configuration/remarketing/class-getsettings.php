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

namespace Newsman\Service\Configuration\Remarketing;

use Newsman\Service\Abstract\Service;
use Newsman\Service\Context\Configuration\EmailList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client Service Configuration Remarketing Get Settings
 *
 * @class \Newsman\Service\Configuration\Remarketing\GetSettings
 */
class GetSettings extends Service {
	/**
	 * Get remarketing settings
	 *
	 * @see https://kb.newsman.com/api/1.2/remarketing.getSettings
	 */
	public const ENDPOINT = 'remarketing.getSettings';

	/**
	 * Get remarketing settings list ID
	 *
	 * @param EmailList $context List context.
	 * @return array|string
	 * @throws \Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$api_context = $this->create_api_context()
			->set_user_id( $context->get_user_id() )
			->set_api_key( $context->get_api_hey() )
			->set_endpoint( self::ENDPOINT );

		$client  = $this->create_api_client();
		$context = apply_filters( 'newsman_service_configuration_remarketing_get_settings_execute_context', $context );
		$result  = $client->get( $api_context, array( 'list_id' => $context->get_list_id() ) );

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		return $result;
	}
}
