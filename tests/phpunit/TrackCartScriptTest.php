<?php
/**
 * Tests for the remarketing cart tracking script: the cookie gate that lets
 * the classic tracker skip getCart.json requests, and which pattern is rendered.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Remarketing\Script\Track;

/**
 * Cart tracking script tests.
 */
class TrackCartScriptTest extends NewsmanTestCase {
	/**
	 * Theme Cart Compatibility option.
	 */
	public const THEME_CART_OPTION = 'newsman_remarketingthemecartcompatibility';

	/**
	 * The gate is on with a current WooCommerce and default cookie settings.
	 */
	public function test_cookie_gate_enabled_by_default_with_woocommerce() {
		$this->require_woocommerce();
		$this->assertTrue( ( new Track() )->is_cart_cookie_gate_enabled() );
	}

	/**
	 * Without WooCommerce there are no cart cookies to rely on.
	 */
	public function test_cookie_gate_disabled_without_woocommerce() {
		add_filter( 'newsman_woocommerce_exist', '__return_false' );
		$this->assertFalse( ( new Track() )->is_cart_cookie_gate_enabled() );
	}

	/**
	 * WooCommerce older than 3.2 does not set the cart cookies on add to cart before the response is sent.
	 */
	public function test_cookie_gate_disabled_before_woocommerce_3_2() {
		$this->require_woocommerce();
		$track = new class() extends Track {
			/**
			 * WooCommerce 3.1.2.
			 *
			 * @return \Newsman\Util\WooCommerceExist
			 */
			protected function get_woo_commerce_exist() {
				return new class() extends \Newsman\Util\WooCommerceExist {
					/**
					 * Compare with WooCommerce 3.1.2.
					 *
					 * @param string $version Version to compare.
					 * @return bool
					 */
					public function is_wc_before( $version ) {
						return version_compare( '3.1.2', $version, '<' );
					}
				};
			}
		};
		$this->assertFalse( $track->is_cart_cookie_gate_enabled() );
	}

	/**
	 * Cart cookies disabled through the WooCommerce filter.
	 */
	public function test_cookie_gate_disabled_when_woocommerce_cookies_disabled() {
		$this->require_woocommerce();
		$filter = function ( $enabled, $name ) {
			return 'woocommerce_items_in_cart' === $name ? false : $enabled;
		};
		add_filter( 'woocommerce_set_cookie_enabled', $filter, 10, 2 );
		$this->assertFalse( ( new Track() )->is_cart_cookie_gate_enabled() );
	}

	/**
	 * HttpOnly cart cookies can't be read by the tracker.
	 */
	public function test_cookie_gate_disabled_when_cart_cookies_are_httponly() {
		$this->require_woocommerce();
		add_filter( 'woocommerce_cookie_httponly', '__return_true' );
		$this->assertFalse( ( new Track() )->is_cart_cookie_gate_enabled() );
	}

	/**
	 * The plugin filter overrides the detection both ways.
	 */
	public function test_cookie_gate_plugin_filter() {
		$this->require_woocommerce();
		add_filter( 'newsman_remarketing_cart_cookie_gate', '__return_false' );
		$this->assertFalse( ( new Track() )->is_cart_cookie_gate_enabled() );

		remove_filter( 'newsman_remarketing_cart_cookie_gate', '__return_false' );
		add_filter( 'woocommerce_cookie_httponly', '__return_true' );
		add_filter( 'newsman_remarketing_cart_cookie_gate', '__return_true' );
		$this->assertTrue( ( new Track() )->is_cart_cookie_gate_enabled() );
	}

	/**
	 * Theme Cart Compatibility ON renders the classic tracker with the gate flag.
	 */
	public function test_classic_cart_script_renders_gate_flag() {
		$this->require_woocommerce();
		update_option( self::THEME_CART_OPTION, 'on' );

		$script = $this->render_classic_cart_script( new Track() );
		$this->assertStringContainsString( '?newsman_cart=getCart.json', $script );
		$this->assertStringContainsString( 'var nzmCartCookieGate = true;', $script );
		$this->assertStringContainsString( 'nzmCartCookieToken()', $script );
		$this->assertStringContainsString( "nzmGetCookie('woocommerce_items_in_cart')", $script );
		$this->assertStringContainsString( "nzmGetCookie('woocommerce_cart_hash')", $script );

		add_filter( 'newsman_remarketing_cart_cookie_gate', '__return_false' );
		$this->assertStringContainsString( 'var nzmCartCookieGate = false;', $this->render_classic_cart_script( new Track() ) );
	}

	/**
	 * Theme Cart Compatibility OFF renders the Store API listener, which also
	 * follows the classic WooCommerce cart events and the cart cookies.
	 */
	public function test_store_api_cart_script_follows_classic_cart_events() {
		$this->require_woocommerce();
		update_option( self::THEME_CART_OPTION, 'off' );

		$script = ( new Track() )->include_script_cart();
		$this->assertStringContainsString( 'nzm_cart_sync', $script );
		$this->assertStringContainsString( '/wc/store/v1/cart/add-item', $script );
		$this->assertStringContainsString( "'added_to_cart removed_from_cart updated_wc_div wc_cart_emptied'", $script );
		$this->assertStringContainsString( 'window.jQuery(document.body).on(NZM_CLASSIC_CART_EVENTS', $script );
		$this->assertStringContainsString( 'var nzmCartCookieGate = true;', $script );
		$this->assertStringContainsString( "nzmGetCookie('woocommerce_cart_hash')", $script );
		$this->assertStringNotContainsString( 'setInterval(NewsmanAutoEvents', $script );

		add_filter( 'newsman_remarketing_cart_cookie_gate', '__return_false' );
		$this->assertStringContainsString( 'var nzmCartCookieGate = false;', $this->render_cart_script( new Track(), 'track-cart-storeapi.php' ) );
	}

	/**
	 * Render track-cart.php as Track::include_script_cart() does. That method uses
	 * include_once, so it renders the pattern only once per PHP process.
	 *
	 * @param Track $track Track instance used as $this in the pattern.
	 * @return string
	 */
	protected function render_classic_cart_script( Track $track ) {
		return $this->render_cart_script( $track, 'track-cart.php' );
	}

	/**
	 * Render a cart pattern in the Track scope.
	 *
	 * @param Track  $track   Track instance used as $this in the pattern.
	 * @param string $pattern Pattern file name.
	 * @return string
	 */
	protected function render_cart_script( Track $track, $pattern ) {
		$path   = dirname( __DIR__, 2 ) . '/includes/remarketing/patterns/' . $pattern;
		$render = function () use ( $path ) {
			ob_start();
			include $path;
			return ob_get_clean();
		};
		return \Closure::bind( $render, $track, Track::class )();
	}
}
