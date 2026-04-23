<?php
/**
 * Title: Newsman remarketing Store API cart tracking script.
 *
 * Listens for WooCommerce Store API cart mutations (/wc/store/v1/cart/add-item
 * and /wc/store/v1/batch containing an add-item sub-request) and replays the
 * returned cart into the Newsman remarketing `_nzm` cart by clearing it and
 * re-adding every product from the response.
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Remarketing\Script\Track $this
 */

if ( ! $this->is_woo_commerce_exist() ) {
	return '';
}
?>
<script<?php esc_js( esc_html( $this->get_script_tag_additional_attributes() ) ); ?>>
var isProd = true;
var NZM_STORE_BATCH_PATH = '/wc/store/v1/batch';
var NZM_STORE_ADD_ITEM_PATH = '/wc/store/v1/cart/add-item';

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
		sessionStorage.setItem('lastCart', JSON.stringify(products));
		NewsmanDebugLog('newsman remarketing: cart sent (store-api)');
	});
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
}

<?php
if ( $this->remarketing_config->is_woo_commerce_page() ) {
	echo "_nzm.run( 'require', 'ec' );";
}
?>
</script>
