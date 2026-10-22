<?php
/**
 * Tests for the export processor: authentication gate and dispatch to the
 * retrievers, including the API v1 parse -> process path.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Config;
use Newsman\Export\Retriever\Authenticator;
use Newsman\Export\Retriever\Processor;
use Newsman\Export\V1\PayloadParser;

/**
 * Processor tests.
 */
class ProcessorTest extends NewsmanTestCase {
	/**
	 * Requests without a valid key never reach a retriever.
	 */
	public function test_process_rejects_missing_or_wrong_key() {
		$this->enable_api();
		$processor = new Processor();

		try {
			$processor->process( 'platform-name', get_current_blog_id(), array() );
			$this->fail( 'Expected OutOfBoundsException for a missing key.' );
		} catch ( \OutOfBoundsException $e ) {
			$this->assertStringContainsString( 'Empty API key', $e->getMessage() );
		}

		$this->expectException( \OutOfBoundsException::class );
		$processor->process( 'platform-name', get_current_blog_id(), array( Authenticator::API_KEY_PARAM => 'wrong' ) );
	}

	/**
	 * The platform and integration retrievers report the environment.
	 */
	public function test_process_platform_and_integration_retrievers() {
		$this->enable_api();
		$processor = new Processor();
		$blog_id   = get_current_blog_id();
		$key       = array( Authenticator::API_KEY_PARAM => self::API_KEY );

		$this->assertSame( array( 'name' => 'WordPress' ), $processor->process( 'platform-name', $blog_id, $key ) );
		$this->assertSame( array( 'version' => NEWSMAN_VERSION ), $processor->process( 'integration-version', $blog_id, $key ) );
		$this->assertSame( array( 'version' => $GLOBALS['wp_version'] ), $processor->process( 'platform-version', $blog_id, $key ) );
	}

	/**
	 * An unknown retriever code is an error, not a silent empty result.
	 */
	public function test_process_rejects_unknown_code() {
		$this->enable_api();
		$this->expectException( \Exception::class );
		( new Processor() )->process( 'no-such-retriever', get_current_blog_id(), array( Authenticator::API_KEY_PARAM => self::API_KEY ) );
	}

	/**
	 * Legacy query string code extraction.
	 */
	public function test_get_code_by_data() {
		$processor = new Processor();
		$this->assertSame( 'orders', $processor->get_code_by_data( array( 'newsman' => 'orders.json' ) ) );
		$this->assertSame( 'platform-name', $processor->get_code_by_data( array( 'newsman' => 'platform-name' ) ) );
		$this->assertFalse( $processor->get_code_by_data( array() ) );
		$this->assertFalse( $processor->get_code_by_data( array( 'newsman' => '' ) ) );
	}

	/**
	 * A full API v1 round trip: JSON payload -> parser -> processor -> retriever.
	 */
	public function test_api_v1_round_trip() {
		$this->enable_api();

		$parsed                               = ( new PayloadParser() )->parse( '{"method": "integration.version", "params": {}}' );
		$data                                 = $parsed['data'];
		$data[ Authenticator::API_KEY_PARAM ] = self::API_KEY;

		$result = ( new Processor() )->process( $parsed['code'], get_current_blog_id(), $data );
		$this->assertSame( array( 'version' => NEWSMAN_VERSION ), $result );
	}

	/**
	 * The API gate needs the plugin active, the API switch, credentials and a list.
	 */
	public function test_config_api_gate() {
		$config = Config::init();
		$this->assertFalse( $config->is_enabled_with_api() );

		$this->enable_api();
		$this->assertTrue( $config->is_enabled_with_api() );

		update_option( 'newsman_list', '' );
		$this->assertFalse( $config->is_enabled_with_api() );

		update_option( 'newsman_list', '1' );
		update_option( 'newsman_api', 'off' );
		$this->assertFalse( $config->is_enabled_with_api() );

		update_option( 'newsman_api', 'on' );
		update_option( 'active_plugins', array() );
		$this->assertFalse( $config->is_enabled_with_api() );
	}
}
