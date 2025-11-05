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

namespace Newsman\Service\Configuration\Sms;

use Newsman\Service\Context\Configuration\User;
use Newsman\Service\AbstractService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Class Service Configuration Get SMS List All
 *
 * @class \Newsman\Service\Configuration\Sms\GetListAll
 */
class GetListAll extends AbstractService {
	/**
	 * Get all SMS lists Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/sms.lists
	 */
	public const ENDPOINT = 'sms.lists';

	/**
	 * Get all SMS lists by user ID
	 *
	 * @param User $context Configuration user context.
	 * @return array|string
	 * @throws \Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$api_context = $this->create_api_context()
			->set_user_id( $context->get_user_id() )
			->set_api_key( $context->get_api_hey() )
			->set_endpoint( self::ENDPOINT );

		$client = $this->create_api_client();
		$result = $client->get( $api_context );

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		return $result;
	}
}
