<?php
/**
 * Tests for the customer identity written into the inline remarketing scripts:
 * the order received page purchase script and the logged in subscriber identify.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Remarketing\Action\IdentifySubscriber;
use Newsman\Remarketing\Action\Purchase;

/**
 * Identity script tests.
 */
class IdentityScriptTest extends NewsmanTestCase {
	/**
	 * First name ending in a backslash, which escaped the closing quote of the JS string.
	 */
	public const FIRST_NAME = 'Trail\\';

	/**
	 * Last name continuing the script once the first name string is broken.
	 */
	public const LAST_NAME = '};document.documentElement.dataset.xss=1;alert(document.domain);//</script><b>\'&"';

	/**
	 * Decode the JSON literal assigned or passed in the script, failing when it is not a single literal.
	 *
	 * @param string $js     Generated JS.
	 * @param string $prefix Text before the literal.
	 * @param string $suffix Text after the literal.
	 * @return array
	 */
	private function decode_literal( $js, $prefix, $suffix ) {
		$this->assertStringStartsWith( $prefix, $js );
		$this->assertStringEndsWith( $suffix, $js );
		$literal = substr( $js, strlen( $prefix ), -strlen( $suffix ) );
		$this->assertDoesNotMatchRegularExpression( '/[<>\'&]/', $literal );

		$decoded = json_decode( $literal, true );
		$this->assertIsArray( $decoded, 'Not a single JSON literal: ' . $literal );
		return $decoded;
	}

	/**
	 * Billing names from a guest checkout stay data in the purchase script.
	 */
	public function test_purchase_identity_keeps_billing_names_as_data() {
		$this->require_woocommerce();
		update_option( 'newsman_remarketingsendtelephone', 'on' );

		$order = wc_create_order();
		$order->set_billing_email( 'guest@example.org' );
		$order->set_billing_phone( '0721 000 111' );
		$order->set_billing_first_name( self::FIRST_NAME );
		$order->set_billing_last_name( self::LAST_NAME );
		$order->save();

		$identity = $this->decode_literal( ( new Purchase() )->get_identity_js( $order ), 'var nzmIdentity = ', ';' );

		$this->assertSame( 'guest@example.org', $identity['email'] );
		$this->assertArrayHasKey( 'phone', $identity );
		$this->assertSame( self::FIRST_NAME, $identity['first_name'] );
		$this->assertSame( self::LAST_NAME, $identity['last_name'] );
	}

	/**
	 * Orders without a billing email send no identity.
	 */
	public function test_purchase_identity_null_without_email() {
		$this->require_woocommerce();
		$order = wc_create_order();
		$this->assertSame( 'var nzmIdentity = null;', ( new Purchase() )->get_identity_js( $order ) );
	}

	/**
	 * The logged in user names stay data in the identify script.
	 */
	public function test_identify_subscriber_keeps_user_names_as_data() {
		// User meta is unslashed on save, as for the slashed $_POST of a profile form.
		wp_set_current_user( $this->create_user( 'customer', 'customer@example.org', wp_slash( self::FIRST_NAME ), wp_slash( self::LAST_NAME ) ) );

		$action = new class() extends IdentifySubscriber {
			/**
			 * Tracking allowed without a configured remarketing ID.
			 *
			 * @return bool
			 */
			public function is_tracking_allowed() {
				return true;
			}

			/**
			 * Not on the checkout page.
			 *
			 * @return bool
			 */
			protected function is_checkout_context() {
				return false;
			}
		};

		$identity = $this->decode_literal( $action->get_js(), '_nzm.identify(', ');' );

		// WordPress strips tags from user names on save, the backslash and the splice remain.
		$this->assertSame( 'customer@example.org', $identity['email'] );
		$this->assertSame( self::FIRST_NAME, $identity['first_name'] );
		$this->assertSame( wp_get_current_user()->user_lastname, $identity['last_name'] );
		$this->assertStringStartsWith( '};document.documentElement.dataset.xss=1;', $identity['last_name'] );
	}
}
