<?php
/**
 * Tests for the OAuth settings page.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Admin\Settings\Oauth;

/**
 * OAuth settings page tests.
 */
class OauthTest extends NewsmanTestCase {
	/**
	 * Restore the request globals.
	 */
	public function tear_down() {
		$_GET = array();
		parent::tear_down();
	}

	/**
	 * Run step one of the OAuth flow as the redirect back from Newsman.
	 *
	 * @param array $get Query parameters of the redirect.
	 * @return Oauth
	 */
	private function return_from_newsman( $get ) {
		$_GET = $get;
		$page = new Oauth();
		$page->process_form_oauth_step_one();
		return $page;
	}

	/**
	 * The error Newsman redirects back with is shown and step one is shown again.
	 */
	public function test_error_from_newsman_is_shown() {
		$page = $this->return_from_newsman( array( 'error' => 'access_denied' ) );
		$this->assertSame( 'Access is denied', $page->form_error_message );
		$this->assertSame( 1, $page->step );

		$page = $this->return_from_newsman( array( 'error' => 'missing_lists' ) );
		$this->assertSame( 'There are no lists in your NewsMAN account', $page->form_error_message );
		$this->assertSame( 1, $page->step );
	}

	/**
	 * An unknown error code shows a general message without the code and logs the code.
	 */
	public function test_unknown_error_shows_general_message() {
		$logged = array();
		$listen = function ( $message ) use ( &$logged ) {
			$logged[] = $message;
		};
		add_action( 'newsman_logger_before_error', $listen );

		$page = $this->return_from_newsman( array( 'error' => 'server_error<b>Call 555</b>' ) );

		remove_action( 'newsman_logger_before_error', $listen );
		$this->assertSame( 'The connection with NewsMAN could not be completed. Please try again.', $page->form_error_message );
		$this->assertStringNotContainsString( 'server_error', $page->form_error_message );
		$this->assertStringNotContainsString( '555', $page->form_error_message );
		$this->assertSame( 1, $page->step );
		$this->assertSame( array( 'OAuth returned an unknown error code: server_errorCall 555' ), $logged );
	}

	/**
	 * Opening the page without an error or a code shows step one without a message.
	 */
	public function test_page_without_callback_shows_no_error() {
		$page = $this->return_from_newsman( array() );
		$this->assertSame( '', $page->form_error_message );
		$this->assertSame( 1, $page->step );
	}
}
