<?php
/**
 * Tests for the remarketing storefront check of the NewsMAN page.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Remarketing\Action\ListingImpression;
use Newsman\Remarketing\Action\PageView;
use Newsman\Remarketing\Action\ProductDetail;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Remarketing\StorefrontCheck;

/**
 * Storefront check tests.
 */
class RemarketingStorefrontCheckTest extends NewsmanTestCase {
	/**
	 * Administrator ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Log in as an administrator with remarketing on.
	 */
	public function set_up() {
		parent::set_up();
		$this->admin_id = $this->create_user( 'administrator', 'rm-check-admin@example.org', 'Check', 'Admin' );
		wp_set_current_user( $this->admin_id );
		$this->mark_plugin_active();
		update_option( 'newsman_useremarketing', 'on' );
		update_option( 'newsman_remarketingid', '12345-678-abcdef' );
		PageView::$is_page_view_sent = false;
	}

	/**
	 * Reset the request.
	 */
	public function tear_down() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		unset( $GLOBALS['current_screen'] );
		wp_set_current_user( 0 );
		PageView::$is_page_view_sent = false;
		parent::tear_down();
	}

	/**
	 * Run a callback with this saved NewsMAN tracking script, then restore the saved options.
	 *
	 * @param string   $script   Saved script.
	 * @param callable $callback Callback.
	 * @return void
	 */
	private function with_saved_script( $script, $callback ) {
		$property = new ReflectionProperty( \Newsman\Config\Options::class, 'options' );
		$property->setAccessible( true );
		$saved = $property->getValue();
		$property->setValue( null, array_merge( (array) $saved, array( 'newsman_scriptjs' => $script ) ) );
		try {
			$callback();
		} finally {
			$property->setValue( null, $saved );
		}
	}

	/**
	 * Put the token in the query string.
	 *
	 * @param string $token Token.
	 * @return void
	 */
	private function request_with_token( $token ) {
		$_GET[ StorefrontCheck::QUERY_PARAM ]     = $token;
		$_REQUEST[ StorefrontCheck::QUERY_PARAM ] = $token;
	}

	/**
	 * Administrators get no tracking, by design; the token of the same administrator overrides it.
	 */
	public function test_token_overrides_admin_exclusion() {
		$config = RemarketingConfig::init();
		$this->assertFalse( $config->is_tracking_allowed(), 'Administrators are not tracked.' );

		$token = ( new StorefrontCheck() )->create_token( $this->admin_id );
		$this->assertSame( 32, strlen( $token ) );

		$this->request_with_token( $token );
		$this->assertTrue( StorefrontCheck::is_check_request() );
		$this->assertTrue( $config->is_tracking_allowed() );
		$this->assertNotSame( '', ( new PageView() )->get_js() );

		$this->request_with_token( $token . 'x' );
		$this->assertFalse( $config->is_tracking_allowed(), 'A wrong token changes nothing.' );

		$this->request_with_token( '' );
		$this->assertFalse( $config->is_tracking_allowed() );
	}

	/**
	 * The token belongs to one user, is stored only as a hash and expires.
	 */
	public function test_token_is_bound_to_user_and_expires() {
		$token = ( new StorefrontCheck() )->create_token( $this->admin_id );
		$this->request_with_token( $token );

		$stored = get_transient( StorefrontCheck::TRANSIENT_PREFIX . $this->admin_id );
		$this->assertSame( hash( 'sha256', $token ), $stored, 'Only the hash is stored.' );

		wp_set_current_user( $this->create_user( 'administrator', 'rm-check-other@example.org', 'Other', 'Admin' ) );
		$this->assertFalse( StorefrontCheck::is_check_request(), 'Another administrator cannot use the token.' );

		wp_set_current_user( 0 );
		$this->assertFalse( StorefrontCheck::is_check_request(), 'A visitor cannot use the token.' );

		wp_set_current_user( $this->admin_id );
		$this->assertTrue( StorefrontCheck::is_check_request() );
		delete_transient( StorefrontCheck::TRANSIENT_PREFIX . $this->admin_id );
		$this->assertFalse( StorefrontCheck::is_check_request(), 'An expired token changes nothing.' );

		$second = ( new StorefrontCheck() )->create_token( $this->admin_id );
		$this->assertFalse( StorefrontCheck::is_check_request(), 'A new token replaces the old one.' );
		$this->request_with_token( $second );
		$this->assertTrue( StorefrontCheck::is_check_request() );
	}

	/**
	 * The token never turns tracking on inside wp-admin.
	 */
	public function test_token_is_ignored_in_admin() {
		$this->request_with_token( ( new StorefrontCheck() )->create_token( $this->admin_id ) );
		// is_admin() reads the current screen.
		$GLOBALS['current_screen'] = new class() { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- simulates wp-admin.
			/**
			 * Inside wp-admin.
			 *
			 * @return bool
			 */
			public function in_admin() {
				return true;
			}
		};
		$this->assertFalse( StorefrontCheck::is_check_request() );
		$this->assertFalse( RemarketingConfig::init()->is_tracking_allowed() );
	}

	/**
	 * The pages to check carry the token and the lookups; WooCommerce pages only with WooCommerce.
	 */
	public function test_pages() {
		$category = 0;
		if ( $this->has_woocommerce() ) {
			$category = self::factory()->term->create(
				array(
					'taxonomy' => 'product_cat',
					'name'     => 'Check Category',
				)
			);
			$product  = new WC_Product_Simple();
			$product->set_name( 'Check Product' );
			$product->set_status( 'publish' );
			$product->set_regular_price( '10' );
			$product->set_category_ids( array( $category ) );
			$product->save();
		}

		$pages = array();
		$this->with_saved_script(
			'',
			function () use ( &$pages ) {
				$pages = ( new StorefrontCheck() )->get_pages( 'tok123' );
			}
		);
		$types = wp_list_pluck( $pages, 'type' );

		$this->assertSame( 'home', $pages[0]['type'] );
		$this->assertSame( home_url( '/' ), $pages[0]['link'] );
		$this->assertStringContainsString( StorefrontCheck::QUERY_PARAM . '=tok123', $pages[0]['url'] );
		$this->assertSame( array( 'tracker', 'pageview' ), wp_list_pluck( $pages[0]['checks'], 'key' ) );
		$this->assertSame( 'regex', $pages[0]['checks'][0]['type'], 'Without a saved script the loader call is searched.' );
		$this->assertSame( StorefrontCheck::PATTERN_TRACKER_LOADER, $pages[0]['checks'][0]['pattern'] );

		if ( ! $this->has_woocommerce() ) {
			$this->assertSame( array( 'home' ), $types );
			return;
		}

		$this->assertSame( array( 'home', 'product', 'category' ), $types );
		$this->assertSame( get_permalink( $product->get_id() ), $pages[1]['link'] );
		$this->assertSame( array( 'tracker', 'product_detail' ), wp_list_pluck( $pages[1]['checks'], 'key' ) );
		$this->assertSame( get_term_link( $category ), $pages[2]['link'] );
		$this->assertSame( array( 'tracker', 'listing_impression' ), wp_list_pluck( $pages[2]['checks'], 'key' ) );
		foreach ( array_slice( $pages, 1 ) as $page ) {
			$this->assertStringContainsString( StorefrontCheck::QUERY_PARAM . '=tok123', $page['url'] );
		}
	}

	/**
	 * Without products or categories the WooCommerce pages are skipped, not failed.
	 */
	public function test_pages_skipped_without_catalog() {
		$this->require_woocommerce();
		$all = wc_get_products(
			array(
				'limit'  => -1,
				'return' => 'ids',
				'status' => 'any',
			)
		);
		foreach ( $all as $id ) {
			wp_delete_post( $id, true );
		}
		$pages = ( new StorefrontCheck() )->get_pages( 'tok' );
		$this->assertArrayHasKey( 'skipped', $pages[1] );
		$this->assertArrayHasKey( 'skipped', $pages[2] );
	}

	/**
	 * The lookup patterns match the scripts the plugin prints, and the cart script does not
	 * look like a product view.
	 */
	public function test_patterns_match_plugin_scripts() {
		$this->request_with_token( ( new StorefrontCheck() )->create_token( $this->admin_id ) );
		$regex = function ( $pattern ) {
			return '~' . $pattern . '~';
		};

		$page_view = ( new PageView() )->get_js();
		$this->assertMatchesRegularExpression( $regex( StorefrontCheck::PATTERN_PAGE_VIEW ), $page_view );

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin files.
		$cart_script = (string) file_get_contents( NEWSMAN_PLUGIN_DIR . 'includes/remarketing/patterns/track-cart.php' )
			. (string) file_get_contents( NEWSMAN_PLUGIN_DIR . 'includes/remarketing/patterns/track-cart-storeapi.php' );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertDoesNotMatchRegularExpression( $regex( StorefrontCheck::PATTERN_PRODUCT_DETAIL ), $cart_script );
		$this->assertDoesNotMatchRegularExpression( $regex( StorefrontCheck::PATTERN_LISTING_IMPRESSION ), $cart_script );
		$this->assertDoesNotMatchRegularExpression( $regex( StorefrontCheck::PATTERN_PAGE_VIEW ), $cart_script );

		if ( ! $this->has_woocommerce() ) {
			return;
		}
		add_filter( 'is_woocommerce', '__return_true' );
		$product = new WC_Product_Simple();
		$product->set_name( 'Pattern Product' );
		$product->set_regular_price( '5' );
		$product->save();

		$detail = new ProductDetail();
		$detail->set_data( array( 'product' => $product ) );
		$this->assertMatchesRegularExpression( $regex( StorefrontCheck::PATTERN_PRODUCT_DETAIL ), $detail->get_js() );

		$listing = new ListingImpression();
		$listing->set_data(
			array(
				'product'          => $product,
				'woocommerce_loop' => 0,
			)
		);
		$this->assertMatchesRegularExpression( $regex( StorefrontCheck::PATTERN_LISTING_IMPRESSION ), $listing->get_js() );
		remove_filter( 'is_woocommerce', '__return_true' );
	}

	/**
	 * The check response is not stored by page caches.
	 */
	public function test_check_request_disables_page_cache() {
		$check       = new StorefrontCheck();
		$was_defined = defined( 'DONOTCACHEPAGE' );
		$check->disable_page_cache();
		if ( ! $was_defined ) {
			$this->assertFalse( defined( 'DONOTCACHEPAGE' ), 'No change without a token.' );
		}

		$this->request_with_token( $check->create_token( $this->admin_id ) );
		$check->disable_page_cache();
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
	}

	/**
	 * Call the AJAX handler and decode its JSON answer.
	 *
	 * @param array $post POST fields.
	 * @return array|null
	 */
	private function ajax( $post ) {
		$_POST    = $post;
		$_REQUEST = $post;
		add_filter( 'wp_doing_ajax', '__return_true' );
		$die = function () {
			return function ( $message ) {
				throw new WPDieException( is_scalar( $message ) ? (string) $message : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			};
		};
		add_filter( 'wp_die_ajax_handler', $die );
		ob_start();
		try {
			( new StorefrontCheck() )->handle_ajax();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$output = ob_get_clean();
		remove_filter( 'wp_die_ajax_handler', $die );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		return json_decode( $output, true );
	}

	/**
	 * The AJAX endpoint needs the capability and the nonce, and returns a working token.
	 */
	public function test_ajax_endpoint() {
		$answer = $this->ajax( array( 'nonce' => wp_create_nonce( StorefrontCheck::NONCE_ACTION ) ) );
		$this->assertTrue( $answer['success'] );
		$this->assertSame( 'home', $answer['data']['pages'][0]['type'] );

		wp_parse_str( (string) wp_parse_url( $answer['data']['pages'][0]['url'], PHP_URL_QUERY ), $query );
		$this->request_with_token( $query[ StorefrontCheck::QUERY_PARAM ] );
		$this->assertTrue( StorefrontCheck::is_check_request(), 'The returned URL carries a valid token.' );

		$_GET = array();
		$this->assertNull( $this->ajax( array( 'nonce' => 'wrong' ) ), 'A wrong nonce stops before any answer.' );

		wp_set_current_user( $this->create_user( 'editor', 'rm-check-editor@example.org', 'Check', 'Editor' ) );
		$answer = $this->ajax( array( 'nonce' => wp_create_nonce( StorefrontCheck::NONCE_ACTION ) ) );
		$this->assertFalse( $answer['success'] );
	}

	/**
	 * The tracker lookup uses the site ID of the saved NewsMAN script, not the
	 * Remarketing ID setting (which only turns remarketing on).
	 */
	public function test_tracker_lookup_uses_saved_script_site_id() {
		$tag    = '<' . 'script'; // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found -- test fixture, not an enqueued script.
		$loader = $tag . '>(function (w, d, t, n, c) { w[n] = w[n] || function () { (w[n].q = w[n].q || []).push(arguments); };'
			. ' var l = d.createElement(t); l.async = 1; l.src = (w[c].js_prefix || "https://t.newsmanapp.com") + "/jt/t.js";'
			. ' l.setAttribute("data-site-id", "99999-14794-site"); })(window, document, "script", "_nzm", "_nzm_config");</script>';
		$this->with_saved_script(
			$loader,
			function () {
				$check = new StorefrontCheck();
				$this->assertSame( '99999-14794-site', $check->get_tracking_site_id() );
				$tracker = $check->get_pages( 'tok' )[0]['checks'][0];
				$this->assertSame( 'text', $tracker['type'] );
				$this->assertSame( '99999-14794-site', $tracker['pattern'], 'Not the Remarketing ID setting 12345-678-abcdef.' );
			}
		);
		$this->assertMatchesRegularExpression( '~' . StorefrontCheck::PATTERN_TRACKER_LOADER . '~', $loader );
		$this->assertDoesNotMatchRegularExpression(
			'~' . StorefrontCheck::PATTERN_TRACKER_LOADER . '~',
			$tag . ' async src="https://t.newsmanapp.com/js/embed-form.js" data-nzmform="1-2-3"></script>',
			'The embedded form script is not the tracker.'
		);
	}
}
