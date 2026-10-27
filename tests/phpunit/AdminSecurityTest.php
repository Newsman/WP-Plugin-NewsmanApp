<?php
/**
 * Tests for the nonce and capability checks of the Newsman settings pages.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Admin\Settings\Oauth;
use Newsman\Admin\Settings\Remarketing;
use Newsman\Admin\Settings\Sync;

/**
 * Admin settings security tests.
 */
class AdminSecurityTest extends NewsmanTestCase {
	/**
	 * Log in as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( $this->create_user( 'administrator', 'admin@example.org', 'Site', 'Admin' ) );
	}

	/**
	 * Restore the request globals.
	 */
	public function tear_down() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		unset( $_SERVER['REQUEST_METHOD'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Simulate a request to the settings page.
	 *
	 * @param string $method HTTP method.
	 * @param array  $get    Query parameters.
	 * @param array  $post   Body parameters.
	 * @return void
	 */
	private function request( $method, $get = array(), $post = array() ) {
		$_SERVER['REQUEST_METHOD'] = $method;
		$_GET                      = $get;
		$_POST                     = $post;
		$_REQUEST                  = array_merge( $get, $post );
	}

	/**
	 * Opening a page needs no nonce.
	 */
	public function test_page_view_without_parameters_passes() {
		$this->request( 'GET' );
		$page = new Remarketing();
		$this->assertTrue( $page->validate_nonce( array( $page->form_id ) ) );
	}

	/**
	 * The form save needs the nonce, as GET or POST.
	 */
	public function test_form_save_without_nonce_fails() {
		$page = new Remarketing();

		$this->request( 'POST', array(), array( $page->form_id => 'Y' ) );
		$this->assertFalse( $page->validate_nonce( array( $page->form_id ) ) );

		$this->request( 'GET', array( $page->form_id => 'Y' ) );
		$this->assertFalse( $page->validate_nonce( array( $page->form_id ) ) );
	}

	/**
	 * A POST without the listed form field needs the nonce too: the Sync page
	 * refreshes the lists and segments cache from its own submit button.
	 */
	public function test_post_without_form_field_needs_nonce() {
		$page = new Sync();

		$this->request( 'POST', array(), array( 'newsman_sync_refresh' => 'Refresh' ) );
		$this->assertFalse( $page->validate_nonce( array( $page->form_id ) ) );

		$this->request( 'POST', array(), array( 'anything' => '1' ) );
		$this->assertFalse( $page->validate_nonce( array() ) );
	}

	/**
	 * The page's own nonce passes, a nonce for another action or user does not.
	 */
	public function test_valid_nonce_passes_and_others_fail() {
		$page = new Sync();

		$this->request(
			'POST',
			array(),
			array(
				'_wpnonce'             => wp_create_nonce( $page->nonce_action ),
				'newsman_sync_refresh' => 'Refresh',
				$page->form_id         => 'Y',
			)
		);
		$this->assertTrue( $page->validate_nonce( array( $page->form_id ) ) );

		$this->request(
			'POST',
			array(),
			array(
				'_wpnonce'     => wp_create_nonce( 'another-action' ),
				$page->form_id => 'Y',
			)
		);
		$this->assertFalse( $page->validate_nonce( array( $page->form_id ) ) );

		$nonce = wp_create_nonce( $page->nonce_action );
		wp_set_current_user( $this->create_user( 'administrator', 'admin2@example.org', 'Other', 'Admin' ) );
		$this->request(
			'POST',
			array(),
			array(
				'_wpnonce'     => $nonce,
				$page->form_id => 'Y',
			)
		);
		$this->assertFalse( $page->validate_nonce( array( $page->form_id ) ) );
	}

	/**
	 * The OAuth callback exchanges the code only with the nonce in the redirect URL.
	 */
	public function test_oauth_callback_needs_nonce() {
		$page       = new Oauth();
		$parameters = array( $page->form_id, $page->form_id_step_two, 'error', 'code' );

		$this->request( 'GET', array( 'code' => 'abc' ) );
		$this->assertFalse( $page->validate_nonce( $parameters ) );

		$this->request(
			'GET',
			array(
				'code'     => 'abc',
				'_wpnonce' => wp_create_nonce( $page->nonce_action ),
			)
		);
		$this->assertTrue( $page->validate_nonce( $parameters ) );
	}

	/**
	 * The OAuth forms, which save the API credentials, need manage_options.
	 */
	public function test_oauth_forms_need_manage_options() {
		wp_set_current_user( $this->create_user( 'editor', 'editor@example.org', 'Site', 'Editor' ) );
		$this->request( 'POST', array(), array( 'oauthstep2' => 'Y' ) );

		$this->expectException( WPDieException::class );
		( new Oauth() )->process_forms();
	}
}
