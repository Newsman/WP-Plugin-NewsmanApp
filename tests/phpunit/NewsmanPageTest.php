<?php
/**
 * Tests for the main NewsMAN admin page: kept rows and the status section.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Admin\Settings\Newsman;
use Newsman\Export\LastRun;

/**
 * NewsMAN page tests.
 */
class NewsmanPageTest extends NewsmanTestCase {
	/**
	 * Fake the NewsMAN API and log in as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );
		wp_set_current_user( $this->create_user( 'administrator', 'page-admin@example.org', 'Page', 'Admin' ) );
		delete_option( LastRun::OPTION_NAME );
		update_option( 'blogname', 'Shop <i>Test</i>' );
	}

	/**
	 * Log out.
	 */
	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Fake NewsMAN API: list.all returns one list with HTML in its name.
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array|false
	 */
	public function fake_newsman_api( $pre, $args, $url ) {
		unset( $args );
		if ( false === strpos( $url, 'list.all' ) ) {
			return $pre;
		}
		return array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => wp_json_encode(
				array(
					array(
						'list_id'   => '1',
						'list_name' => '<script>alert(1)</script>Main',
					),
				)
			),
		);
	}

	/**
	 * Render src/backend.php. include_page() uses include_once, so the template
	 * is included directly in the page object scope.
	 *
	 * @return string
	 */
	private function render_page() {
		$page   = new Newsman();
		$render = Closure::bind(
			function () {
				ob_start();
				include NEWSMAN_PLUGIN_DIR . 'src/backend.php';
				return ob_get_clean();
			},
			$page,
			Newsman::class
		);
		return $render();
	}

	/**
	 * The kept rows stay, the old menu descriptions and credentials row are gone.
	 */
	public function test_page_keeps_version_oauth_widget_documentation() {
		$this->enable_api();
		$html = $this->render_page();

		$this->assertStringContainsString( 'NewsMAN Info', $html );
		$this->assertStringContainsString( 'Newsman Plugin Version', $html );
		$this->assertStringContainsString( '<td>' . NEWSMAN_VERSION . '</td>', $html );
		$this->assertStringContainsString( 'Reconfigure with Newsman Oauth', $html );
		$this->assertStringContainsString( 'page=NewsmanOauth', $html );
		$this->assertStringContainsString( '<strong>Shop &lt;i&gt;Test&lt;/i&gt;</strong> (ID: <strong>' . get_current_blog_id() . '</strong>)', $html );
		$this->assertStringContainsString( 'newsman_subscribe_widget', $html );
		$this->assertStringContainsString( 'Transform your website visitors into subscribers', $html );
		$this->assertStringContainsString( 'configuration-en.md', $html );
		$this->assertStringContainsString( 'configuration-ro.md', $html );

		$this->assertStringNotContainsString( 'Credentials Status', $html );
		$this->assertStringNotContainsString( 'Menus', $html );
		$this->assertStringNotContainsString( 'You will be able to sync your shop customers', $html );
		$this->assertStringNotContainsString( 'In the settings page enter your API Key', $html );
		$this->assertStringNotContainsString( 'Provide a valuable experience to your customer', $html );

		$version_pos = strpos( $html, 'Newsman Plugin Version' );
		$status_pos  = strpos( $html, 'nzm-health-row-credentials' );
		$widget_pos  = strpos( $html, 'newsman_subscribe_widget' );
		$doc_pos     = strpos( $html, 'configuration-en.md' );
		$this->assertTrue( $version_pos < $status_pos && $status_pos < $widget_pos && $widget_pos < $doc_pos, 'Order: version, status, widget, documentation.' );
	}

	/**
	 * The status section shows every check, escaped.
	 */
	public function test_page_status_section() {
		$this->enable_api();
		( new LastRun() )->record( 'subscribers', null, time() - 2 * DAY_IN_SECONDS );
		$html = $this->render_page();

		foreach ( array( 'credentials', 'list', 'api_access', 'remarketing', 'action_scheduler', 'woocommerce', 'exports' ) as $row ) {
			$this->assertStringContainsString( 'nzm-health-row-' . $row, $html, $row );
		}
		$this->assertMatchesRegularExpression( '#nzm-health-row-credentials.*?nzm-health-good.*?Valid#s', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;Main (ID: 1)', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertMatchesRegularExpression( '#nzm-health-export-subscribers.*?\(2 days ago\)#s', $html );
		$this->assertStringContainsString( 'site-health.php', $html );

		if ( $this->has_woocommerce() ) {
			$this->assertMatchesRegularExpression( '#nzm-health-export-orders.*?No export recorded yet#s', $html );
		} else {
			$this->assertStringNotContainsString( 'nzm-health-export-orders', $html );
		}
	}

	/**
	 * Rejected credentials are shown as a problem with a link to Settings.
	 */
	public function test_page_shows_invalid_credentials() {
		$this->enable_api();
		remove_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10 );
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				unset( $args );
				if ( false === strpos( $url, 'list.all' ) ) {
					return $pre;
				}
				return array(
					'headers'  => array(),
					'cookies'  => array(),
					'response' => array(
						'code'    => 500,
						'message' => 'Error',
					),
					'body'     => '{"err":"Error","message":"Invalid API key","code":401}',
				);
			},
			10,
			3
		);

		$html = $this->render_page();
		$this->assertMatchesRegularExpression( '#nzm-health-row-credentials.*?nzm-health-critical.*?Invalid.*?page=NewsmanSettings#s', $html );
	}

	/**
	 * The storefront check button shows only when remarketing is on with an ID.
	 */
	public function test_remarketing_check_button() {
		$this->enable_api();
		delete_option( 'newsman_useremarketing' );
		$this->assertStringNotContainsString( 'id="nzm-remarketing-check"', $this->render_page(), 'Remarketing off: no button.' );

		update_option( 'newsman_useremarketing', 'on' );
		delete_option( 'newsman_remarketingid' );
		$this->assertStringNotContainsString( 'id="nzm-remarketing-check"', $this->render_page(), 'No Remarketing ID: no button.' );

		update_option( 'newsman_remarketingid', '12345-678-abcdef' );
		$html = $this->render_page();
		$this->assertMatchesRegularExpression( '#nzm-health-row-remarketing.*?id="nzm-remarketing-check".*?id="nzm-remarketing-check-results"#s', $html );
		$this->assertStringContainsString( 'Check the remarketing scripts on the storefront', $html );
		$this->assertMatchesRegularExpression(
			'#id="nzm-remarketing-check-results".*?nzm-remarketing-check-note.*?Integrations &gt; NewsMAN Remarketing &gt; Check installation#s',
			$html,
			'The note to use the NewsMAN installation check ends the Remarketing row.'
		);
	}

	/**
	 * The check script and its settings are loaded only on the NewsMAN page.
	 */
	public function test_remarketing_check_script_only_on_newsman_page() {
		$admin = \Newsman\Admin::init();
		$admin->register_plugin_scripts( 'toplevel_page_NewsmanSettings' );
		$this->assertFalse( wp_script_is( 'newsman_remarketing_check', 'enqueued' ) );

		$admin->register_plugin_scripts( 'toplevel_page_Newsman' );
		$this->assertTrue( wp_script_is( 'newsman_remarketing_check', 'enqueued' ) );
		$data = wp_scripts()->get_data( 'newsman_remarketing_check', 'data' );
		$this->assertStringContainsString( 'NEWSMAN_REMARKETING_CHECK', $data );
		$this->assertStringContainsString( \Newsman\Remarketing\StorefrontCheck::AJAX_ACTION, $data );
		wp_dequeue_script( 'newsman_remarketing_check' );
	}
}
