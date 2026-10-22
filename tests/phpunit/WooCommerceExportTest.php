<?php
/**
 * Integration tests for the WooCommerce based exports (orders, customers and
 * product feed). Skipped when WooCommerce is not installed in the test
 * environment.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Export\Retriever\Customers;
use Newsman\Export\Retriever\Orders;
use Newsman\Export\Retriever\ProductsFeed;

/**
 * WooCommerce export tests.
 */
class WooCommerceExportTest extends NewsmanTestCase {
	/**
	 * Skip everything without WooCommerce.
	 */
	public function set_up() {
		parent::set_up();
		$this->require_woocommerce();
	}

	/**
	 * Create a published simple product.
	 *
	 * @param string $name  Product name.
	 * @param string $price Regular price.
	 * @param string $sku   SKU.
	 * @return \WC_Product_Simple
	 */
	private function create_product( $name, $price, $sku = '' ) {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( $price );
		$product->set_status( 'publish' );
		if ( $sku ) {
			$product->set_sku( $sku );
		}
		$product->save();
		return $product;
	}

	/**
	 * Create an order for a product with the given billing details.
	 *
	 * @param \WC_Product $product  Product.
	 * @param int         $quantity Quantity.
	 * @param string      $email    Billing email.
	 * @param string      $status   Order status (without wc- prefix).
	 * @param int         $customer Customer user ID.
	 * @return \WC_Order
	 */
	private function create_order( $product, $quantity, $email, $status = 'completed', $customer = 0 ) {
		$order = wc_create_order( array( 'customer_id' => $customer ) );
		$order->add_product( $product, $quantity );
		$order->set_billing_first_name( 'Ion' );
		$order->set_billing_last_name( 'Popescu' );
		$order->set_billing_email( $email );
		$order->set_billing_phone( '+40 (721) 000-111' );
		$order->set_currency( 'RON' );
		$order->calculate_totals();
		$order->set_status( $status );
		$order->save();
		return $order;
	}

	/**
	 * Order rows carry the fields Newsman consumes.
	 */
	public function test_orders_export_row() {
		$product = $this->create_product( 'Blue Mug', '12.50', 'MUG-BLUE' );
		$order   = $this->create_order( $product, 2, 'ion@example.org' );

		$rows = ( new Orders() )->process( array() );
		$this->assertCount( 1, $rows );
		$row = $rows[0];

		$this->assertSame( $order->get_order_number(), $row['id'] );
		$this->assertSame( 'Ion Popescu', $row['billing_name'] );
		$this->assertSame( 'ion@example.org', $row['customer_email'] );
		$this->assertSame( '', $row['customer_id'] );
		$this->assertSame( 25.0, $row['total_amount'] );
		$this->assertSame( 25.0, $row['subtotal_amount'] );
		$this->assertSame( 'RON', $row['currency'] );
		$this->assertSame( 'completed', $row['status'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['date_created'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['date_modified'] );
		$this->assertMatchesRegularExpression( '/^\+?\d+$/', $row['billing_phone'] );

		$this->assertCount( 1, $row['products'] );
		$this->assertSame( $product->get_id(), $row['products'][0]['id'] );
		$this->assertSame( 'Blue Mug', $row['products'][0]['name'] );
		$this->assertSame( 2, $row['products'][0]['quantity'] );
		$this->assertSame( 12.5, $row['products'][0]['unit_price'] );
	}

	/**
	 * Single order export and unknown order ID.
	 */
	public function test_orders_export_single_order() {
		$product = $this->create_product( 'Pen', '3' );
		$order   = $this->create_order( $product, 1, 'a@example.org' );
		$this->create_order( $product, 1, 'b@example.org' );

		$rows = ( new Orders() )->process( array( 'order_id' => $order->get_id() ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'a@example.org', $rows[0]['customer_email'] );

		$this->assertSame( array(), ( new Orders() )->process( array( 'order_id' => 999999 ) ) );
	}

	/**
	 * Paginated order export returns every order once, ordered by ID; drafts are excluded.
	 */
	public function test_orders_pagination_and_status_filter() {
		$product  = $this->create_product( 'Pen', '3' );
		$expected = array();
		foreach ( array( 'completed', 'processing', 'on-hold', 'cancelled', 'completed' ) as $i => $status ) {
			$expected[] = (int) $this->create_order( $product, 1, 'o' . $i . '@example.org', $status )->get_id();
		}
		$this->create_order( $product, 1, 'draft@example.org', 'checkout-draft' );

		$ids = $this->walk_pages(
			function ( $start, $limit ) {
				return ( new Orders() )->process(
					array(
						'start' => $start,
						'limit' => $limit,
					)
				);
			},
			'id',
			2
		);
		sort( $expected );
		$this->assertSame( $expected, $ids );

		$rows = ( new Orders() )->process( array( 'status' => 'completed' ) );
		$this->assertCount( 2, $rows );

		$rows = ( new Orders() )->process( array( 'order_ids' => array( $expected[1], $expected[3] ) ) );
		$this->assertSame( array( $expected[1], $expected[3] ), array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );

		$rows = ( new Orders() )->process( array( 'email' => 'o2@example.org' ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'on-hold', $rows[0]['status'] );

		$this->assertSame( array(), ( new Orders() )->process( array( 'created_at' => array( 'from' => '2099-01-01' ) ) ) );
	}

	/**
	 * Customers export covers users with the customer role.
	 */
	public function test_customers_export() {
		$customer_id = $this->create_user( 'customer', 'maria@example.org', 'Maria', 'Ionescu' );
		$this->create_user( 'subscriber', 'sub@example.org', 'Sub', 'Scriber' );

		$rows = ( new Customers() )->process( array() );
		$this->assertCount( 1, $rows );
		$this->assertSame( $customer_id, (int) $rows[0]['customer_id'] );
		$this->assertSame( 'maria@example.org', $rows[0]['email'] );
		$this->assertSame( 'Maria', $rows[0]['firstname'] );
		$this->assertSame( 'Ionescu', $rows[0]['lastname'] );
		$this->assertSame( 'WooCommerce users role customer', $rows[0]['source'] );
	}

	/**
	 * Product feed exports published products with stock and category data.
	 */
	public function test_products_feed_export() {
		$this->create_product( 'Blue Mug', '12.50', 'MUG-BLUE' );
		$second = $this->create_product( 'Red Mug', '13.00', 'MUG-RED' );
		$second->set_manage_stock( true );
		$second->set_stock_quantity( 0 );
		$second->set_stock_status( 'outofstock' );
		$second->save();

		$draft = $this->create_product( 'Hidden Mug', '1' );
		$draft->set_status( 'draft' );
		$draft->save();

		$rows = ( new ProductsFeed() )->process( array() );
		$this->assertCount( 2, $rows );

		$by_sku = array();
		foreach ( $rows as $row ) {
			$by_sku[ $row['sku'] ] = $row;
		}
		$this->assertSame( 'Blue Mug', $by_sku['MUG-BLUE']['name'] );
		$this->assertSame( '1', $by_sku['MUG-BLUE']['in_stock'] );
		$this->assertSame( '0', $by_sku['MUG-RED']['in_stock'] );
		$this->assertStringContainsString( 'http', $by_sku['MUG-BLUE']['url'] );

		$ids = $this->walk_pages(
			function ( $start, $limit ) {
				return ( new ProductsFeed() )->process(
					array(
						'start' => $start,
						'limit' => $limit,
					)
				);
			},
			'id',
			1
		);
		$this->assertCount( 2, $ids );
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );
	}
}
