<?php
/**
 * Title: Newsman remarketing Store API cart tracking script.
 *
 * Listens for WooCommerce Store API cart mutations (/wc/store/v1/cart/add-item
 * and /wc/store/v1/batch containing an add-item sub-request) and replays the
 * returned cart into the Newsman remarketing `_nzm` cart by clearing it and
 * re-adding every product from the response.
 *
 * Classic themes change the cart through wc-ajax and the cart page form, so
 * the WooCommerce jQuery cart events and a changed cart cookie on page load
 * request the Newsman cart endpoint once and replay it when it changed.
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template partial included within a method; variables are method-local.

/**
 * Current class for output
 *
 * @var \Newsman\Remarketing\Script\Track $this
 */

if ( ! $this->is_woo_commerce_exist() ) {
	return '';
}
$site_url    = get_site_url();
$cart_param  = \Newsman\Remarketing\Cart\Handler\CartAjax::CART_PARAMETER;
$cookie_gate = $this->is_cart_cookie_gate_enabled();
?>
<script<?php esc_js( esc_html( $this->get_script_tag_additional_attributes() ) ); ?>>
var isProd = true;
var NZM_STORE_BATCH_PATH = '/wc/store/v1/batch';
var NZM_STORE_ADD_ITEM_PATH = '/wc/store/v1/cart/add-item';
var NZM_CART_AJAX_URL = '<?php echo esc_url( rtrim( $site_url, '/' ) . '/' ); ?>' + '?newsman_cart=<?php echo esc_html( $cart_param ); ?>';
var NZM_SESSION_SYNC_COOKIE = 'nzm_cart_sync';
var NZM_CLASSIC_CART_EVENTS = 'added_to_cart removed_from_cart updated_wc_div wc_cart_emptied';
var nzmCartCookieGate = <?php echo $cookie_gate ? 'true' : 'false'; ?>;
var nzmClassicEventsHooked = false;
var nzmCartRequestTimer = null;
var nzmCartRequestRunning = false;
var nzmCartRequestPending = false;

function NewsmanDebugLog(message) {
	if ((typeof isProd !== 'undefined') && isProd === true) {
		return;
	}
	console.log(message);
}

function nzmMatchStoreBatch(url) {
	return typeof url === 'string' && url.indexOf(NZM_STORE_BATCH_PATH) !== -1;
}

function nzmMatchStoreAddItem(url) {
	return typeof url === 'string' && url.indexOf(NZM_STORE_ADD_ITEM_PATH) !== -1;
}

function nzmBatchRequestHasAddItem(requestBody) {
	if (!requestBody) {
		return false;
	}
	try {
		var parsed = typeof requestBody === 'string' ? JSON.parse(requestBody) : requestBody;
		if (parsed && Array.isArray(parsed.requests)) {
			for (var i = 0; i < parsed.requests.length; i++) {
				var req = parsed.requests[i];
				if (req && typeof req.path === 'string' &&
					req.path.indexOf(NZM_STORE_ADD_ITEM_PATH) !== -1) {
					return true;
				}
			}
		}
	} catch (e) {}
	return false;
}

function nzmExtractCartFromBatchResponse(responseBody) {
	if (!responseBody || !Array.isArray(responseBody.responses)) {
		return null;
	}
	for (var i = 0; i < responseBody.responses.length; i++) {
		var r = responseBody.responses[i];
		if (r && r.body && Array.isArray(r.body.items)) {
			return r.body;
		}
	}
	return null;
}

function nzmProductsFromCart(cart) {
	var products = [];
	if (!cart || !Array.isArray(cart.items)) {
		return products;
	}
	for (var i = 0; i < cart.items.length; i++) {
		var item = cart.items[i];
		var price = 0;
		if (item.prices && typeof item.prices.price !== 'undefined') {
			var minor = item.prices.currency_minor_unit || 0;
			var raw = parseFloat(item.prices.price);
			if (!isNaN(raw)) {
				price = raw / Math.pow(10, minor);
			}
		}
		products.push({
			id: item.id,
			name: item.name,
			price: price,
			quantity: item.quantity
		});
	}
	return products;
}

function nzmStoreApiDispatch(products) {
	_nzm.run('ec:setAction', 'clear_cart');
	NewsmanDebugLog('newsman remarketing: clear cart sent, store-api add to cart');
	_nzm.run('send', 'event', 'detail view', 'click', 'clearCart', null, function() {
		for (var i = 0; i < products.length; i++) {
			NewsmanDebugLog('ec:addProduct');
			_nzm.run('ec:addProduct', products[i]);
			NewsmanDebugLog(products[i]);
		}
		_nzm.run('ec:setAction', 'add');
		_nzm.run('send', 'event', 'UX', 'click', 'add to cart');
		try { sessionStorage.setItem('lastCart', JSON.stringify(products)); } catch (e) {}
		NewsmanDebugLog('newsman remarketing: cart sent (store-api)');
	});
}

function nzmStoreApiClear() {
	_nzm.run('ec:setAction', 'clear_cart');
	_nzm.run('send', 'event', 'detail view', 'click', 'clearCart');
	try { sessionStorage.setItem('lastCart', JSON.stringify([])); } catch (e) {}
	NewsmanDebugLog('newsman remarketing: clear cart sent (store-api bootstrap)');
}

function nzmGetCookie(name) {
	var needle = name + '=';
	var parts = document.cookie ? document.cookie.split(';') : [];
	for (var i = 0; i < parts.length; i++) {
		var p = parts[i].replace(/^\s+/, '');
		if (p.indexOf(needle) === 0) {
			return p.substring(needle.length);
		}
	}
	return null;
}

function nzmSetSessionCookie(name, value) {
	// No Max-Age / Expires => browser-session lifetime. SameSite=Lax keeps
	// it on ordinary same-site navigations. Cleared when the browser closes.
	document.cookie = name + '=' + value + '; path=/; SameSite=Lax';
}

// Cart state from the WooCommerce cookies: 'empty', 'h:<cart hash>', or
// 'unknown' when the cart has items but there is no cart hash cookie.
function nzmCartCookieToken() {
	var items = nzmGetCookie('woocommerce_items_in_cart');
	if (!items || items === '0') {
		return 'empty';
	}
	var hash = nzmGetCookie('woocommerce_cart_hash');
	if (!hash || hash === '0') {
		return 'unknown';
	}
	return 'h:' + hash;
}

// Products as sorted "id:quantity" pairs, or null when unknown.
function nzmCartSignature(products) {
	if (!Array.isArray(products)) {
		return null;
	}
	var pairs = [];
	for (var i = 0; i < products.length; i++) {
		pairs.push(String(products[i].id) + ':' + String(products[i].quantity));
	}
	return pairs.sort().join(',');
}

function nzmLastCartSignature() {
	try {
		return nzmCartSignature(JSON.parse(sessionStorage.getItem('lastCart')));
	} catch (e) {
		return null;
	}
}

// Request the Newsman cart endpoint and replay the cart. With onlyChanged the
// cart is replayed only when it differs from the last one sent. The sync
// cookie is set unconditionally after the XHR resolves, including on failure,
// so a broken endpoint cannot cause a re-request on every navigation. It holds
// the cart cookie state seen before the request when the cookies are readable.
function nzmRequestCart(onlyChanged, done) {
	var url = NZM_CART_AJAX_URL;
	var sep = url.indexOf('?') >= 0 ? '&t=' : '?t=';
	url = url + sep + Date.now();

	var token = nzmCartCookieGate ? nzmCartCookieToken() : '1';
	var xhr = new XMLHttpRequest();
	var finished = false;
	var markDone = function () {
		if (finished) {
			return;
		}
		finished = true;
		nzmSetSessionCookie(NZM_SESSION_SYNC_COOKIE, token);
		if (typeof done === 'function') {
			done();
		}
	};

	try {
		xhr.open('GET', url, true);
	} catch (e) {
		markDone();
		return;
	}

	xhr.onload = function () {
		try {
			if (xhr.status !== 200 && xhr.status !== 201) {
				return;
			}
			var parsed;
			try { parsed = JSON.parse(xhr.responseText); } catch (e) { parsed = null; }
			if (onlyChanged) {
				if (!Array.isArray(parsed)) {
					return;
				}
				if (nzmCartSignature(parsed) === nzmLastCartSignature()) {
					NewsmanDebugLog('newsman remarketing: cart is unchanged');
					return;
				}
			}
			if (Array.isArray(parsed) && parsed.length > 0) {
				nzmStoreApiDispatch(parsed);
			} else {
				nzmStoreApiClear();
			}
		} finally {
			markDone();
		}
	};

	xhr.onerror = markDone;
	xhr.ontimeout = markDone;

	try {
		xhr.send(null);
	} catch (e) {
		markDone();
	}
}

// Once per browser session, clear the Newsman remarketing cart and replay
// the current WooCommerce cart into it. Runs when no nzm_cart_sync cookie
// is present; ON mode has no equivalent because track-cart.php's
// firstLoad=true branch in NewsmanAutoEvents already polls getCart.json on
// every page. Later page loads request the cart again only when the
// WooCommerce cart cookies changed since the last request, e.g. after a
// classic add to cart form or a cart page update.
function nzmSessionBootstrap() {
	var synced = nzmGetCookie(NZM_SESSION_SYNC_COOKIE);
	if (!synced) {
		nzmRequestCart(false);
		return;
	}
	if (!nzmCartCookieGate) {
		NewsmanDebugLog('newsman remarketing: session sync cookie present, bootstrap skipped');
		return;
	}
	var token = nzmCartCookieToken();
	if (token === 'unknown' || token === synced) {
		NewsmanDebugLog('newsman remarketing: cart cookies unchanged, request not sent');
		return;
	}
	nzmRequestCart(true);
}

// One cart request for a burst of classic cart events, and one more when an
// event arrives while a request is in flight.
function nzmScheduleCartRequest() {
	if (nzmCartRequestTimer) {
		clearTimeout(nzmCartRequestTimer);
	}
	nzmCartRequestTimer = setTimeout(function () {
		nzmCartRequestTimer = null;
		if (nzmCartRequestRunning) {
			nzmCartRequestPending = true;
			return;
		}
		nzmCartRequestRunning = true;
		nzmRequestCart(true, function () {
			nzmCartRequestRunning = false;
			if (nzmCartRequestPending) {
				nzmCartRequestPending = false;
				nzmScheduleCartRequest();
			}
		});
	}, 300);
}

// WooCommerce classic scripts (wc-ajax add to cart and mini cart remove, cart
// page updates) trigger jQuery events only, so they are hooked through jQuery
// once it is loaded.
function nzmHookClassicCartEvents() {
	var attach = function () {
		if (nzmClassicEventsHooked || !window.jQuery || !document.body) {
			return;
		}
		nzmClassicEventsHooked = true;
		window.jQuery(document.body).on(NZM_CLASSIC_CART_EVENTS, function () {
			nzmScheduleCartRequest();
		});
	};
	attach();
	if (!nzmClassicEventsHooked) {
		document.addEventListener('DOMContentLoaded', attach);
		window.addEventListener('load', attach);
	}
}

function nzmHandleStoreApiResponse(url, requestBody, responseText) {
	var responseBody;
	try {
		responseBody = JSON.parse(responseText);
	} catch (e) {
		return;
	}

	var cart = null;
	if (nzmMatchStoreBatch(url)) {
		if (!nzmBatchRequestHasAddItem(requestBody)) {
			return;
		}
		cart = nzmExtractCartFromBatchResponse(responseBody);
	} else if (nzmMatchStoreAddItem(url)) {
		if (responseBody && Array.isArray(responseBody.items)) {
			cart = responseBody;
		}
	}
	if (!cart) {
		return;
	}

	nzmStoreApiDispatch(nzmProductsFromCart(cart));
	if (nzmCartCookieGate && nzmGetCookie(NZM_SESSION_SYNC_COOKIE)) {
		nzmSetSessionCookie(NZM_SESSION_SYNC_COOKIE, nzmCartCookieToken());
	}
}

function nzmHookFetch() {
	if (typeof window.fetch !== 'function') {
		return;
	}
	var origFetch = window.fetch;
	window.fetch = function() {
		var args = arguments;
		var reqUrl = '';
		var reqBody = null;
		try {
			var a0 = args[0];
			reqUrl = typeof a0 === 'string' ? a0 : (a0 && a0.url) || '';
			var a1 = args[1];
			if (a1 && typeof a1.body !== 'undefined') {
				reqBody = a1.body;
			} else if (a0 && typeof a0 !== 'string' && typeof a0.body !== 'undefined') {
				reqBody = a0.body;
			}
		} catch (e) {}

		var promise = origFetch.apply(this, args);

		if (!nzmMatchStoreBatch(reqUrl) && !nzmMatchStoreAddItem(reqUrl)) {
			return promise;
		}

		return promise.then(function(response) {
			try {
				response.clone().text().then(function(text) {
					nzmHandleStoreApiResponse(reqUrl, reqBody, text);
				}).catch(function() {});
			} catch (e) {}
			return response;
		});
	};
}

function nzmHookXHR() {
	var origOpen = window.XMLHttpRequest.prototype.open;
	var origSend = window.XMLHttpRequest.prototype.send;

	window.XMLHttpRequest.prototype.open = function(method, url) {
		try { this.__nzmUrl = url; } catch (e) {}
		return origOpen.apply(this, arguments);
	};

	window.XMLHttpRequest.prototype.send = function(body) {
		var xhr = this;
		var url = xhr.__nzmUrl || '';
		try { xhr.__nzmBody = (typeof body !== 'undefined') ? body : null; } catch (e) {}

		if (nzmMatchStoreBatch(url) || nzmMatchStoreAddItem(url)) {
			xhr.addEventListener('load', function() {
				if (xhr.status >= 200 && xhr.status < 300) {
					nzmHandleStoreApiResponse(url, xhr.__nzmBody, xhr.responseText);
				}
			});
		}
		return origSend.apply(this, arguments);
	};
}

var documentComparer = document.location.hostname;
var documentUrl = document.URL;
var sameOrigin = (documentUrl.indexOf(documentComparer) !== -1);

if (sameOrigin) {
	nzmHookFetch();
	nzmHookXHR();
	nzmSessionBootstrap();
	nzmHookClassicCartEvents();
}

<?php
if ( $this->remarketing_config->is_woo_commerce_page() ) {
	echo "_nzm.run( 'require', 'ec' );";
}
?>
</script>
