<?php
/**
 * Regression tests for the order received purchase script (3.7.29):
 * order data is printed only to a visitor WooCommerce shows the order to, and
 * the purchase is neither lost nor sent again on a reload.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Remarketing\Action\PageView;
use Newsman\Remarketing\Action\Purchase;

/**
 * Purchase script tests.
 */
class PurchaseScriptTest extends NewsmanTestCase {
	/**
	 * Billing email of the test orders.
	 */
	public const EMAIL = 'buyer@example.org';

	/**
	 * WooCommerce session before the test.
	 *
	 * @var WC_Session|null
	 */
	private $original_session;

	/**
	 * Set up an order received page request.
	 */
	public function set_up() {
		parent::set_up();
		$this->require_woocommerce();

		$this->original_session      = WC()->session;
		PageView::$is_page_view_sent = false;
		add_filter( 'woocommerce_is_order_received_page', '__return_true' );
		add_filter( 'woocommerce_is_checkout', '__return_true' );
	}

	/**
	 * Restore the request globals.
	 */
	public function tear_down() {
		if ( $this->has_woocommerce() ) {
			WC()->session = $this->original_session;
		}
		unset( $GLOBALS['wp']->query_vars['order-received'] );
		$_GET  = array();
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Create an order with one product line.
	 *
	 * @param int      $customer_id Customer user ID, 0 for a guest order.
	 * @param int|null $created     Creation timestamp, null for now.
	 * @return WC_Order
	 */
	private function create_order( $customer_id = 0, $created = null ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Purchase Probe' );
		$product->set_regular_price( '12.50' );
		$product->save();

		$order = wc_create_order( array( 'customer_id' => $customer_id ) );
		$order->add_product( $product, 2 );
		$order->set_billing_email( self::EMAIL );
		$order->set_billing_first_name( 'Ana' );
		$order->set_billing_last_name( 'Pop' );
		if ( null !== $created ) {
			$order->set_date_created( $created );
		}
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * Render the purchase script for the order received page of an order.
	 *
	 * @param WC_Order          $order Order shown by the page.
	 * @param string|array|null $key   Value of the key query parameter, null for none, true for the order key.
	 * @return string
	 */
	private function render( $order, $key = true ) {
		$_GET = array();
		if ( true === $key ) {
			$_GET['key'] = $order->get_order_key();
		} elseif ( null !== $key ) {
			$_GET['key'] = $key;
		}
		$GLOBALS['wp']->query_vars['order-received'] = $order->get_id();
		PageView::$is_page_view_sent                 = false;

		return ( new Purchase() )->get_js();
	}

	/**
	 * Assert the script carries the order data.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $js    Rendered script.
	 * @return void
	 */
	private function assert_purchase_script( $order, $js ) {
		$this->assertStringContainsString( 'var nzmIdentity = {"email":"' . self::EMAIL . '"', $js );
		$this->assertStringContainsString( "_nzm.run( 'ec:setAction', 'purchase', {", $js );
		$this->assertStringContainsString( "'id': '" . $order->get_order_number() . "'", $js );
		$this->assertStringContainsString( "_nzm.run( 'ec:addProduct', {", $js );
		$this->assertStringContainsString( "'quantity': '2'", $js );
	}

	/**
	 * Use a WooCommerce session holding the given values.
	 *
	 * @param array $values Session values.
	 * @return void
	 */
	private function use_session( $values ) {
		$session = new class() extends WC_Session {};
		foreach ( $values as $name => $value ) {
			$session->set( $name, $value );
		}
		WC()->session = $session;
	}

	/**
	 * A new guest order is shown with its key: identity, products and purchase.
	 */
	public function test_guest_order_with_key_prints_purchase() {
		$order = $this->create_order();
		$this->assert_purchase_script( $order, $this->render( $order ) );
	}

	/**
	 * Without the order key, with a wrong key or a malformed key nothing is printed.
	 */
	public function test_order_without_valid_key_prints_nothing() {
		$order = $this->create_order();

		$this->assertSame( '', $this->render( $order, null ) );
		$this->assertSame( '', $this->render( $order, '' ) );
		$this->assertSame( '', $this->render( $order, 'wc_order_wrongkey' ) );
		$this->assertSame( '', $this->render( $order, substr( $order->get_order_key(), 0, -1 ) ) );
		$this->assertSame( '', $this->render( $order, array( $order->get_order_key() ) ) );
	}

	/**
	 * Only the order received page prints the purchase.
	 */
	public function test_other_pages_print_nothing() {
		$order = $this->create_order();
		remove_filter( 'woocommerce_is_order_received_page', '__return_true' );
		add_filter( 'woocommerce_is_order_received_page', '__return_false' );

		$this->assertSame( '', $this->render( $order ) );
	}

	/**
	 * An account order needs its customer logged in, even with the right key.
	 */
	public function test_account_order_needs_its_customer() {
		$owner = $this->create_user( 'customer', 'owner@example.org', 'Owner', 'One' );
		$other = $this->create_user( 'customer', 'other@example.org', 'Other', 'Two' );
		$order = $this->create_order( $owner );

		$this->assertSame( '', $this->render( $order ), 'Logged out visitor.' );

		wp_set_current_user( $other );
		$this->assertSame( '', $this->render( $order ), 'Another customer.' );

		wp_set_current_user( $owner );
		$this->assert_purchase_script( $order, $this->render( $order ) );
	}

	/**
	 * WooCommerce's filter to show account orders without login is honoured.
	 */
	public function test_account_order_verify_known_shoppers_filter() {
		$order = $this->create_order( $this->create_user( 'customer', 'owner@example.org', 'Owner', 'One' ) );
		add_filter( 'woocommerce_order_received_verify_known_shoppers', '__return_false' );

		$this->assert_purchase_script( $order, $this->render( $order ) );
	}

	/**
	 * A guest order past the grace period is hidden from an unknown visitor.
	 */
	public function test_old_guest_order_hidden_from_unknown_visitor() {
		$order = $this->create_order( 0, time() - HOUR_IN_SECONDS );
		$this->use_session( array( 'customer' => array( 'email' => 'someone-else@example.org' ) ) );

		$this->assertSame( '', $this->render( $order ) );
	}

	/**
	 * A guest order past the grace period is shown to the session that placed it.
	 */
	public function test_old_guest_order_shown_to_its_session() {
		$order = $this->create_order( 0, time() - HOUR_IN_SECONDS );

		$this->use_session( array( 'store_api_draft_order' => $order->get_id() ) );
		$this->assert_purchase_script( $order, $this->render( $order ) );

		$this->use_session( array( 'customer' => array( 'email' => strtoupper( self::EMAIL ) ) ) );
		$this->assert_purchase_script( $order, $this->render( $order ) );
	}

	/**
	 * A guest order past the grace period is shown after the billing email is confirmed.
	 */
	public function test_old_guest_order_shown_after_email_confirmation() {
		$order = $this->create_order( 0, time() - HOUR_IN_SECONDS );

		$_POST = array( 'email' => self::EMAIL );
		$this->assertSame( '', $this->render( $order ), 'Email without nonce.' );

		$_POST = array(
			'email'            => 'wrong@example.org',
			'check_submission' => wp_create_nonce( 'wc_verify_email' ),
		);
		$this->assertSame( '', $this->render( $order ), 'Wrong email.' );

		$_POST = array(
			'email'            => self::EMAIL,
			'check_submission' => wp_create_nonce( 'wc_verify_email' ),
		);
		$this->assert_purchase_script( $order, $this->render( $order ) );
	}

	/**
	 * Shop staff and WooCommerce's email verification filter see old guest orders.
	 */
	public function test_old_guest_order_shown_to_shop_manager_and_by_filter() {
		$order = $this->create_order( 0, time() - HOUR_IN_SECONDS );

		add_filter( 'woocommerce_order_email_verification_required', '__return_false' );
		$this->assert_purchase_script( $order, $this->render( $order ) );
		remove_filter( 'woocommerce_order_email_verification_required', '__return_false' );

		wp_set_current_user( $this->create_user( 'shop_manager', 'manager@example.org', 'Shop', 'Manager' ) );
		$this->assert_purchase_script( $order, $this->render( $order ) );
	}

	/**
	 * The Newsman filter can hide the purchase of an order WooCommerce shows.
	 */
	public function test_can_view_order_filter() {
		$order = $this->create_order();
		add_filter( 'newsman_remarketing_action_purchase_can_view_order', '__return_false' );

		$this->assertSame( '', $this->render( $order ) );
	}

	/**
	 * The purchase, its products and page view are sent from one function, after
	 * the guard and identity, all inside one closure.
	 */
	public function test_purchase_script_structure() {
		$order = $this->create_order();
		$js    = $this->render( $order );

		$this->assertStringStartsWith( "(function() {\n", $js );
		$this->assertStringEndsWith( "})();\n\t\t", $js );
		$this->assertStringContainsString( "var nzmOrderId = '" . $order->get_order_number() . "';", $js );
		$this->assertStringContainsString( "var nzmSentKey = 'newsman_purchases_sent';", $js );

		$send_start = strpos( $js, 'function nzmSendPurchase() {' );
		$send_end   = strpos( $js, 'if (nzmAlreadySent) {' );
		$this->assertNotFalse( $send_start );
		$this->assertNotFalse( $send_end );
		foreach ( array( "'ec:addProduct'", "'ec:setAction', 'purchase'", "'send', 'pageview'" ) as $call ) {
			$position = strpos( $js, $call );
			$this->assertGreaterThan( $send_start, $position, $call . ' must be sent from nzmSendPurchase().' );
			$this->assertLessThan( $send_end, $position, $call . ' must be sent from nzmSendPurchase().' );
		}
		$this->assertSame( 1, substr_count( $js, "'ec:setAction', 'purchase'" ) );
	}

	/**
	 * Run the purchase script in Node with the fake tracker.
	 *
	 * @param string $js       Purchase script.
	 * @param array  $scenario Harness scenario.
	 * @return array
	 */
	private function run_in_tracker( $js, $scenario ) {
		$node = getenv( 'NODE_BINARY' ) ? getenv( 'NODE_BINARY' ) : 'node';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Test runner, fixed command.
		exec( escapeshellarg( $node ) . ' --version 2>/dev/null', $version, $status );
		if ( 0 !== $status ) {
			$this->markTestSkipped( 'Node.js is needed to run the purchase script (set NODE_BINARY).' );
		}

		$file = wp_tempnam( 'newsman-purchase' );
		file_put_contents( $file, $js ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$command = escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__ ) . '/js/purchase-guard-harness.js' ) . ' ' .
			escapeshellarg( $file ) . ' ' . escapeshellarg( wp_json_encode( $scenario ) ) . ' 2>&1';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Test runner, fixed command.
		exec( $command, $output, $status );
		wp_delete_file( $file );

		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result, implode( "\n", $output ) );
		foreach ( $result['views'] as $index => $view ) {
			$this->assertSame( array(), $view['errors'], 'JS errors in view ' . $index );
		}
		return $result;
	}

	/**
	 * Orders marked as delivered in the guard storage.
	 *
	 * @param array $result Harness result.
	 * @return array
	 */
	private function sent_orders( $result ) {
		if ( empty( $result['storage']['newsman_purchases_sent'] ) ) {
			return array();
		}
		return array_map( 'strval', array_keys( json_decode( $result['storage']['newsman_purchases_sent'], true ) ) );
	}

	/**
	 * A visitor the tracker does not know yet: identify first, then exactly one
	 * purchase; the order is marked and a reload sends no purchase.
	 */
	public function test_unidentified_visitor_purchase_sent_once_then_reload_skips() {
		$order  = $this->create_order();
		$number = (string) $order->get_order_number();
		$result = $this->run_in_tracker( $this->render( $order ), array( 'views' => array( array(), array() ) ) );

		$first = $result['views'][0];
		$this->assertSame( 1, $first['identifies'] );
		$this->assertSame( 1, $first['purchaseHits'] );
		$this->assertSame( 0, $first['droppedPurchases'] );
		$this->assertSame( array( $number ), $first['purchaseIds'] );
		$this->assertSame( 1, $first['pageviews'] );

		$reload = $result['views'][1];
		$this->assertSame( 0, $reload['purchaseHits'] );
		$this->assertSame( 1, $reload['identifies'] );
		$this->assertSame( 1, $reload['pageviews'] );

		$this->assertSame( array( $number ), $this->sent_orders( $result ) );
	}

	/**
	 * A visitor with a tracking id sends the purchase right away.
	 */
	public function test_identified_visitor_purchase_sent_once() {
		$order  = $this->create_order();
		$result = $this->run_in_tracker( $this->render( $order ), array( 'views' => array( array( 'cookie' => true ) ) ) );

		$this->assertSame( 1, $result['views'][0]['purchaseHits'] );
		$this->assertSame( array( (string) $order->get_order_number() ), $this->sent_orders( $result ) );
	}

	/**
	 * A failed purchase request is not marked and is sent again on the next view.
	 */
	public function test_failed_purchase_is_sent_again() {
		$order  = $this->create_order();
		$result = $this->run_in_tracker(
			$this->render( $order ),
			array(
				'views' => array(
					array( 'response' => 'fail' ),
					array(),
					array(),
				),
			)
		);

		$this->assertSame( 1, $result['views'][0]['purchaseHits'] );
		$this->assertSame( 1, $result['views'][1]['purchaseHits'] );
		$this->assertSame( 0, $result['views'][2]['purchaseHits'] );
		$this->assertSame( array( (string) $order->get_order_number() ), $this->sent_orders( $result ) );
	}

	/**
	 * Blocked t.js or a failed identify loses nothing: the next view sends the purchase.
	 */
	public function test_blocked_tracker_or_failed_identify_is_sent_on_next_view() {
		$order  = $this->create_order();
		$result = $this->run_in_tracker(
			$this->render( $order ),
			array(
				'views' => array(
					array( 'tracker' => 'blocked' ),
					array( 'identify' => 'fail' ),
					array(),
				),
			)
		);

		$this->assertSame( 0, $result['views'][0]['purchaseHits'] );
		$this->assertSame( 0, $result['views'][1]['purchaseHits'] );
		$this->assertSame( 1, $result['views'][1]['droppedPurchases'], 'The 5 s fallback sends it, the tracker drops it.' );
		$this->assertSame( array(), $result['views'][0]['purchaseIds'] );
		$this->assertSame( 1, $result['views'][2]['purchaseHits'] );
		$this->assertSame( array( (string) $order->get_order_number() ), $this->sent_orders( $result ) );
	}

	/**
	 * The legacy flag of 3.7.28 and older counts as sent; other orders' marks stay.
	 */
	public function test_legacy_flag_and_other_orders() {
		$order  = $this->create_order();
		$number = (string) $order->get_order_number();

		$legacy = $this->run_in_tracker(
			$this->render( $order ),
			array( 'storage' => array( $number => '1' ) )
		);
		$this->assertSame( 0, $legacy['views'][0]['purchaseHits'] );

		$others = $this->run_in_tracker(
			$this->render( $order ),
			array( 'storage' => array( 'newsman_purchases_sent' => wp_json_encode( array( 'other-1' => 1700000000000 ) ) ) )
		);
		$this->assertSame( 1, $others['views'][0]['purchaseHits'] );
		$this->assertEqualsCanonicalizing( array( 'other-1', $number ), $this->sent_orders( $others ) );
	}

	/**
	 * Without usable localStorage the purchase is still sent on every view.
	 */
	public function test_storage_unavailable_never_loses_the_purchase() {
		$order  = $this->create_order();
		$result = $this->run_in_tracker(
			$this->render( $order ),
			array(
				'storageThrows' => true,
				'views'         => array( array(), array() ),
			)
		);

		$this->assertSame( 1, $result['views'][0]['purchaseHits'] );
		$this->assertSame( 1, $result['views'][1]['purchaseHits'] );
	}

	/**
	 * An order number with quotes, ampersand and backslash is matched by the guard.
	 */
	public function test_order_number_with_special_characters() {
		$order = $this->create_order();
		add_filter(
			'woocommerce_order_number',
			function () {
				return "A&B'1\\";
			}
		);

		$result = $this->run_in_tracker( $this->render( $order ), array( 'views' => array( array(), array() ) ) );

		// esc_js() encodes the ampersand and strips the backslash, the same way in the purchase id and the guard.
		$this->assertSame( array( "A&amp;B'1" ), $result['views'][0]['purchaseIds'] );
		$this->assertSame( 0, $result['views'][1]['purchaseHits'] );
		$this->assertSame( array( "A&amp;B'1" ), $this->sent_orders( $result ) );
	}
}
