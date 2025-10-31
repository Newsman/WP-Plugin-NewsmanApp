<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman remarketing class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Remarketing\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remarketing action identify subscriber
 *
 * @class \Newsman\Remarketing\Action\Purchase
 */
class Purchase extends AbstractAction {
	/**
	 * Get JS code
	 *
	 * @return string
	 */
	public function get_js() {
		global $wp;

		if ( ! $this->is_tracking_allowed() ) {
			return '';
		}

		if ( ! is_order_received_page() ) {
			return '';
		}

		$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;
		if ( 0 >= $order_id ) {
			return '';
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return '';
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.1.0', '>=' ) ) {
			if ( function_exists( 'wc_get_container' ) &&
				class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableDataStore' )
			) {
				$order_data_store = wc_get_container()->get(
					\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableDataStore::class
				);
			} else {
				$order_data_store = \WC_Data_Store::load( 'order' );
			}
		} else {
			$order_data_store = \WC_Data_Store::load( 'order' );
		}

		$run           = $this->remarketing_config->get_js_track_run_func();
		$currency_code = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_order_currency() :
			$order->get_currency();

		$js = '_nzm.identify({ email: "' . esc_attr( esc_html( $order->get_billing_email() ) ) . '", ';
		if ( $this->remarketing_config->is_send_telephone() && ! empty( $order->get_billing_phone() ) ) {
			$js .= 'phone: "' . esc_attr( esc_html( $this->telephone->clean( $order->get_billing_phone() ) ) ) . '", ';
		}
		$js .= 'first_name: "' . esc_attr( esc_html( $order->get_billing_first_name() ) ) . '", ' .
			'last_name: "' . esc_attr( esc_html( $order->get_billing_last_name() ) ) . '" });';

		$js .= ' ' . $run . "( 'set', 'currencyCode', '" . esc_js( $currency_code ) . "' );";

		// Add order items.
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$js .= $this->get_item_js( $order, $item );
			}
		}

		if ( method_exists( $order, 'get_shipping_total' ) ) {
			$shipping_total = $order->get_shipping_total();
		} else {
			$shipping_total = $order->get_total_shipping();
		}

		$js .= "
		var orderV = localStorage.getItem('" . esc_js( $order->get_order_number() ) . "');
		var orderN = '" . esc_js( $order->get_order_number() ) . "';
		localStorage.setItem(orderN, 'true');
		if (typeof orderV === 'undefined' || (typeof orderV !== 'undefined' && (orderV === null || orderV === ''))) {
			" . $run . "( 'ec:setAction', 'purchase', {
				'id': '" . esc_js( $order->get_order_number() ) . "',
				'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',
				'revenue': '" . esc_js( $order->get_total() ) . "',
				'tax': '" . esc_js( $order->get_total_tax() ) . "',
				'shipping': '" . esc_js( $shipping_total ) . "',
				'currency': '" . esc_js( $currency_code ) . "'
			} );
		}
		";

		$page_view = new \Newsman\Remarketing\Action\PageView();
		$page_view->set_data( array( \Newsman\Remarketing\Action\PageView::MARK_PAGE_VIEW_SENT_FLAG => true ) );
		$js .= $page_view->get_js();

		return apply_filters(
			'newsman_remarketing_action_purchase_js',
			$js,
			array(
				'order' => $order,
			)
		);
	}

	/**
	 * Add Item (Enhanced, Universal)
	 *
	 * @param \WC_Order            $order \WC_Order Object.
	 * @param array|\WC_Order_Item $item The item to add to a transaction/order.
	 * @return string
	 */
	public function get_item_js( $order, $item ) {
		$run = $this->remarketing_config->get_js_track_run_func();

		$product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) :
			$item->get_product();
		$variant = $this->get_product_variant_line( $order, $item, $product );

		$js  = '' . $run . "( 'ec:addProduct', {";
		$js .= "'id': '" . esc_js( $product->get_id() ? $product->get_id() : $product->get_sku() ) . "',";
		$js .= "'name': '" . esc_js( $item['name'] ) . "',";
		$js .= "'category': " . $this->get_product_category_line( $product );

		if ( '' !== $variant ) {
			$js .= "'variant': " . $variant;
		}

		$js .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$js .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$js .= '});';

		return apply_filters(
			'newsman_remarketing_action_purchase_item_js',
			$js,
			array(
				'order' => $order,
				'item'  => $item,
			)
		);
	}

	/**
	 * Returns a 'variant' JSON line based on product
	 *
	 * @param \WC_Order            $order \WC_Order Object.
	 * @param array|\WC_Order_Item $item The item to add to a transaction/order.
	 * @param \WC_Product          $product Product to pull info for.
	 *
	 * @return string Line of JSON.
	 */
	public function get_product_variant_line( $order, $item, $product ) {
		$variation_data = array();
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$variation_data = $product->variation_data;
		} elseif ( $product->is_type( 'variation' ) ) {
			$variation_data = wc_get_product_variation_attributes( $product->get_id() );
		}

		$js = '';
		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$js = "'" . esc_js( wc_get_formatted_variation( $variation_data, true ) ) . "',";
		}

		return apply_filters(
			'newsman_remarketing_action_purchase_item_variant_js',
			$js,
			array(
				'order'   => $order,
				'item'    => $item,
				'product' => $product,
			)
		);
	}
}
