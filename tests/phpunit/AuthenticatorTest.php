<?php
/**
 * Tests for the export request authenticator.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Export\Retriever\Authenticator;

/**
 * Authenticator tests.
 */
class AuthenticatorTest extends NewsmanTestCase {
	/**
	 * An empty key is rejected before any option lookup.
	 */
	public function test_empty_key_is_rejected() {
		$this->expectException( \OutOfBoundsException::class );
		( new Authenticator() )->authenticate( '' );
	}

	/**
	 * The configured API key authenticates.
	 */
	public function test_api_key_authenticates() {
		$this->enable_api();
		$this->assertTrue( ( new Authenticator() )->authenticate( self::API_KEY ) );
	}

	/**
	 * A wrong key is rejected.
	 */
	public function test_wrong_key_is_rejected() {
		$this->enable_api();
		$this->expectException( \OutOfBoundsException::class );
		( new Authenticator() )->authenticate( 'not-the-key' );
	}

	/**
	 * A key is rejected when no key is configured at all.
	 */
	public function test_key_is_rejected_when_nothing_is_configured() {
		$this->expectException( \OutOfBoundsException::class );
		( new Authenticator() )->authenticate( 'anything' );
	}

	/**
	 * The authenticate token is accepted as an alternative to the API key.
	 */
	public function test_authenticate_token_authenticates() {
		$this->enable_api();
		update_option( 'newsman_authenticate_token', 'auth-token-xyz' );

		$auth = new Authenticator();
		$this->assertTrue( $auth->authenticate( 'auth-token-xyz' ) );
		$this->assertTrue( $auth->authenticate( self::API_KEY ) );
	}

	/**
	 * The alternate authorize header key only works when both name and key are set.
	 */
	public function test_alternate_header_key_requires_name_and_key() {
		$this->enable_api();
		update_option( 'newsman_export_authorize_header_key', 'alt-key-123' );

		try {
			( new Authenticator() )->authenticate( 'alt-key-123' );
			$this->fail( 'Alternate key must not authenticate without a header name.' );
		} catch ( \OutOfBoundsException $e ) {
			$this->assertStringContainsString( 'Invalid API key', $e->getMessage() );
		}

		update_option( 'newsman_export_authorize_header_name', 'X-Newsman-Auth' );
		$this->assertTrue( ( new Authenticator() )->authenticate( 'alt-key-123' ) );
	}

	/**
	 * Comparison is exact: prefixes and case variants are rejected.
	 */
	public function test_key_comparison_is_exact() {
		$this->enable_api();
		$auth = new Authenticator();

		foreach ( array( self::API_KEY . 'x', substr( self::API_KEY, 0, -1 ), strtoupper( self::API_KEY ) ) as $candidate ) {
			try {
				$auth->authenticate( $candidate );
				$this->fail( 'Key "' . $candidate . '" must be rejected.' );
			} catch ( \OutOfBoundsException $e ) {
				$this->assertNotEmpty( $e->getMessage() );
			}
		}
	}
}
