<?php
/**
 * Tests for the Newsman API error classes. They are loaded lazily in
 * production, so this makes sure every supported PHP version can parse them.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Api\Error\AbstractError;
use Newsman\Api\Error\InitSubscribe;

/**
 * API error class tests.
 */
class ApiErrorTest extends NewsmanTestCase {
	/**
	 * The initSubscribe error codes are available and extend the base error.
	 */
	public function test_init_subscribe_error_codes() {
		$this->assertSame( 128, InitSubscribe::TOO_MANY_REQUESTS );
		$this->assertTrue( is_subclass_of( InitSubscribe::class, AbstractError::class ) );
	}
}
