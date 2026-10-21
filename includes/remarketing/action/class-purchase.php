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
		if ( ! $order || ! $this->can_view_order( $order ) ) {
			return '';
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.1.0', '>=' ) ) {
			if ( function_exists( 'wc_get_container' ) &&
				class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableDataStore' )
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

		$currency_code = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_order_currency() :
			$order->get_currency();

		$order_number = (string) $order->get_order_number();

		$js  = "(function() {\n";
		$js .= $this->get_delivery_guard_js( $order_number );

		if ( '' !== (string) $order->get_billing_email() ) {
			$js .= 'var nzmIdentity = { email: "' . esc_attr( esc_html( $order->get_billing_email() ) ) . '", ';
			if ( $this->remarketing_config->is_send_telephone() && ! empty( $order->get_billing_phone() ) ) {
				$js .= 'phone: "' . esc_attr( esc_html( $this->telephone->clean( $order->get_billing_phone() ) ) ) . '", ';
			}
			$js .= 'first_name: "' . esc_attr( esc_html( $order->get_billing_first_name() ) ) . '", ' .
				'last_name: "' . esc_attr( esc_html( $order->get_billing_last_name() ) ) . '" };';
		} else {
			$js .= 'var nzmIdentity = null;';
		}

		$js .= " _nzm.run( 'set', 'currencyCode', '" . esc_js( $currency_code ) . "' );";

		$page_view = new \Newsman\Remarketing\Action\PageView();
		$page_view->set_data( array( \Newsman\Remarketing\Action\PageView::MARK_PAGE_VIEW_SENT_FLAG => true ) );
		$page_view_js = $page_view->get_js();

		$js .= '
		var nzmPurchaseQueued = false;
		function nzmSendPurchase() {
			if (nzmPurchaseQueued) {
				return;
			}
			nzmPurchaseQueued = true;
			try { nzmWatchDelivery(0); } catch (e) {}
		';

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
			_nzm.run( 'ec:setAction', 'purchase', {
				'id': '" . esc_js( $order_number ) . "',
				'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',
				'revenue': '" . esc_js( $order->get_total() ) . "',
				'tax': '" . esc_js( $order->get_total_tax() ) . "',
				'shipping': '" . esc_js( $shipping_total ) . "',
				'currency': '" . esc_js( $currency_code ) . "'
			} );
			" . $page_view_js . '
		}
		if (nzmAlreadySent) {
			if (nzmIdentity) {
				_nzm.identify(nzmIdentity);
			}
			' . $page_view_js . '
			return;
		}
		// The tracker drops a purchase from a visitor without a tracking id, so a
		// visitor not identified yet sends it once identify has stored the id.
		if (!nzmIdentity) {
			nzmSendPurchase();
		} else if (/(?:^|;\\s*)_nzm_id_[^=]*=[^;]/.test(document.cookie)) {
			_nzm.identify(nzmIdentity);
			nzmSendPurchase();
		} else {
			_nzm.identify(nzmIdentity, undefined, nzmSendPurchase);
			setTimeout(nzmSendPurchase, 5000);
		}
		})();
		';

		return apply_filters(
			'newsman_remarketing_action_purchase_js',
			$js,
			array(
				'order' => $order,
			)
		);
	}

	/**
	 * Can the current visitor see this order on the order received page.
	 * Same rules as WooCommerce: the order key from the URL must match and an
	 * order placed from a customer account needs that customer logged in.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	protected function can_view_order( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The order key is compared with hash_equals(), a nonce cannot apply to links emailed to the customer.
		$order_key = ( empty( $_GET['key'] ) || ! is_string( $_GET['key'] ) ) ? '' : sanitize_text_field( wp_unslash( $_GET['key'] ) );
		$order_key = apply_filters( 'woocommerce_thankyou_order_key', $order_key );

		if ( ! is_string( $order_key ) || '' === $order_key || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
			return false;
		}

		$order_customer_id = (int) $order->get_customer_id();
		if ( $order_customer_id ) {
			$verify_known_shoppers = apply_filters( 'woocommerce_order_received_verify_known_shoppers', true );
			$can_view              = ! $verify_known_shoppers || get_current_user_id() === $order_customer_id;
		} else {
			$can_view = ! $this->guest_should_verify_email( $order );
		}

		return (bool) apply_filters( 'newsman_remarketing_action_purchase_can_view_order', $can_view, array( 'order' => $order ) );
	}

	/**
	 * A guest order is shown without asking for the billing email while it is new
	 * (grace period) or when this visitor's session or posted email matches it.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	protected function guest_should_verify_email( $order ) {
		$billing_email = (string) $order->get_billing_email();
		if ( '' === $billing_email ) {
			return false;
		}

		$grace_period = (int) apply_filters( 'woocommerce_order_email_verification_grace_period', 10 * MINUTE_IN_SECONDS, $order, 'order-received' );
		$date_created = $order->get_date_created();
		if ( $date_created instanceof \WC_DateTime && time() - $date_created->getTimestamp() <= $grace_period ) {
			return false;
		}

		$session = function_exists( 'WC' ) ? WC()->session : null;
		if ( $session instanceof \WC_Session ) {
			if ( (int) $order->get_id() === (int) $session->get( 'store_api_draft_order' ) ) {
				return false;
			}
			$customer = $session->get( 'customer' );
			if ( is_array( $customer ) && ! empty( $customer['email'] ) && 0 === strcasecmp( $customer['email'], $billing_email ) ) {
				return false;
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified right here.
		if ( isset( $_POST['email'] ) ) {
			$nonce = '';
			if ( isset( $_POST['check_submission'] ) ) {
				$nonce = sanitize_key( wp_unslash( $_POST['check_submission'] ) );
			} elseif ( isset( $_POST['_wpnonce'] ) ) {
				$nonce = sanitize_key( wp_unslash( $_POST['_wpnonce'] ) );
			}
			$supplied_email = sanitize_email( wp_unslash( $_POST['email'] ) );
			if ( '' !== $nonce && ( wp_verify_nonce( $nonce, 'wc_verify_email' ) || wp_verify_nonce( $nonce, 'wc_create_account' ) ) &&
				'' !== $supplied_email && 0 === strcasecmp( $supplied_email, $billing_email )
			) {
				return false;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
		if ( current_user_can( 'read_private_shop_orders' ) ) {
			return false;
		}

		return (bool) apply_filters( 'woocommerce_order_email_verification_required', true, $order, 'order-received' );
	}

	/**
	 * Remembers in localStorage the orders whose purchase Newsman received, so a
	 * reload of the order received page does not send the purchase again.
	 *
	 * An order counts as received only after the tracker confirmed a purchase
	 * (_nzm.onPurchase, called on an OK response) while the request for this
	 * order, issued on this page, is no longer waiting in the tracker's
	 * nzm_retry queue. Anything less (tracker blocked, request failed, consent
	 * denied, storage unavailable) leaves the order unmarked and the purchase is
	 * sent again on the next view.
	 *
	 * @param string $order_number Order number sent as the purchase id.
	 * @return string
	 */
	protected function get_delivery_guard_js( $order_number ) {
		$js = <<<'JS'
var nzmOrderId = '__ORDER_ID__';
var nzmSentKey = 'newsman_purchases_sent';
function nzmSentOrders() {
	try {
		var sent = JSON.parse(window.localStorage.getItem(nzmSentKey) || '{}');
		return sent && typeof sent === 'object' ? sent : {};
	} catch (e) {
		return {};
	}
}
function nzmIsOrderPurchase(url) {
	var u = String(url);
	for (var i = 0; i < 3; i++) {
		try {
			var decoded = decodeURIComponent(u);
			if (decoded === u) {
				break;
			}
			u = decoded;
		} catch (e) {
			break;
		}
	}
	var id = nzmOrderId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	return u.indexOf('p[pa]=purchase') !== -1 && new RegExp('[?&]p\\[ti\\]=' + id + '(&|$)').test(u);
}
function nzmPurchaseDelivered() {
	var requests = window._nzm && window._nzm.stats && window._nzm.stats.requests;
	var issued = false;
	for (var i = 0; requests && i < requests.length; i++) {
		if (nzmIsOrderPurchase(requests[i])) {
			issued = true;
		}
	}
	if (!issued) {
		return false;
	}
	var pending = JSON.parse(window.localStorage.getItem('nzm_retry') || '[]');
	if (!Array.isArray(pending)) {
		return false;
	}
	for (var j = 0; j < pending.length; j++) {
		if (!pending[j] || typeof pending[j].url !== 'string' || nzmIsOrderPurchase(pending[j].url)) {
			return false;
		}
	}
	return true;
}
function nzmMarkSent() {
	try {
		if (!nzmPurchaseDelivered()) {
			return;
		}
		var sent = nzmSentOrders();
		var ids = [];
		sent[nzmOrderId] = Date.now();
		for (var id in sent) {
			if (Object.prototype.hasOwnProperty.call(sent, id)) {
				ids.push(id);
			}
		}
		ids.sort(function (a, b) { return sent[b] - sent[a]; });
		for (var k = 50; k < ids.length; k++) {
			delete sent[ids[k]];
		}
		window.localStorage.setItem(nzmSentKey, JSON.stringify(sent));
	} catch (e) {}
}
function nzmWatchDelivery(attempt) {
	var nzm = window._nzm;
	// Until t.js loads _nzm is the stub array, which the tracker then replaces.
	if (!nzm || Array.isArray(nzm)) {
		if (attempt < 600) {
			setTimeout(function () { nzmWatchDelivery(attempt + 1); }, 100);
		}
		return;
	}
	var previous = nzm.onPurchase;
	nzm.onPurchase = function () {
		nzmMarkSent();
		if (typeof previous === 'function') {
			return previous.apply(this, arguments);
		}
	};
}
// Orders viewed before this guard existed were flagged under their bare number.
function nzmLegacySent() {
	try {
		return !!window.localStorage.getItem(nzmOrderId);
	} catch (e) {
		return false;
	}
}
var nzmAlreadySent = !!nzmSentOrders()[nzmOrderId] || nzmLegacySent();

JS;

		return str_replace( '__ORDER_ID__', esc_js( $order_number ), $js );
	}

	/**
	 * Add Item (Enhanced, Universal)
	 *
	 * @param \WC_Order            $order \WC_Order Object.
	 * @param array|\WC_Order_Item $item The item to add to a transaction/order.
	 * @return string
	 */
	public function get_item_js( $order, $item ) {
		$product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) :
			$item->get_product();
		$variant = $this->get_product_variant_line( $order, $item, $product );

		$js  = "_nzm.run( 'ec:addProduct', {";
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
