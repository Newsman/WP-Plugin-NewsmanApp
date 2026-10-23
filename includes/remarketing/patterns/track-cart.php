<?php
/**
 * Title: Newsman remarketing tracking script
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
const newsmanCartAjaxUrl = "<?php echo esc_url( rtrim( $site_url, '/' ) . '/' ); ?>" + '?newsman_cart=<?php echo esc_html( $cart_param ); ?>';
var nzmCartCookieGate = <?php echo $cookie_gate ? 'true' : 'false'; ?>;
var nzmisProd = true;
let nzmlastCart = sessionStorage.getItem('lastCart');
if (nzmlastCart === null) {
	nzmlastCart = {};
}
var nzmlastCartFlag = false;
var nzmfirstLoad = true;
var nzmbufferedXHR = false;
var nzmCartMutationXHR = false;
var nzmunlockClearCart = true;
var nzmisError = false;
let nzmsecondsAllow = 5;
let nzmmsRunAutoEvents = 5000;
let nzmmsClick = new Date();
var nzmdocumentComparer = document.location.hostname;
var nzmdocumentUrl = document.URL;
var nzmsameOrigin = (nzmdocumentUrl.indexOf(nzmdocumentComparer) !== -1);
let nzmstartTime, nzmendTime;

function startTimePassed() {
	nzmstartTime = new Date();
}

function endTimePassed() {
	var flag = false,
		timeDiff,
		seconds;
	
	nzmendTime = new Date();
	timeDiff = nzmendTime - nzmstartTime;
	timeDiff /= 1000;
	
	if (nzmfirstLoad) {
		flag = true;
	}

	seconds = Math.round(timeDiff);
	if (seconds >= nzmsecondsAllow) {
		flag = true;
	}

	return flag;
}

startTimePassed();

if (nzmsameOrigin) {
	NewsmanAutoEvents();
	setInterval(NewsmanAutoEvents, nzmmsRunAutoEvents);
	detectClicks();
	detectXHR();
	detectFetch();
}

function nzmGetCookie(name) {
	var needle = name + '=';
	var parts = document.cookie ? document.cookie.split(';') : [];
	for (var i = 0; i < parts.length; i++) {
		var p = parts[i].replace(/^\s+/, '');
		if (p.indexOf(needle) === 0) {
			try { return decodeURIComponent(p.substring(needle.length)); } catch (e) { return p.substring(needle.length); }
		}
	}
	return null;
}

function nzmSessionGet(key) {
	try { return sessionStorage.getItem(key); } catch (e) { return null; }
}

function nzmSessionSet(key, value) {
	try { sessionStorage.setItem(key, value); } catch (e) {}
}

function nzmCookieGateOff() {
	try { return localStorage.getItem('nzm_cart_cookie_off') === '1'; } catch (e) { return false; }
}

function nzmIsMutationMethod(method) {
	method = String(method || 'GET').toUpperCase();
	return method !== 'GET' && method !== 'HEAD';
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

function nzmLastCartHasItems() {
	try {
		var last = JSON.parse(nzmSessionGet('lastCart'));
		return Array.isArray(last) && last.length > 0;
	} catch (e) {
		return false;
	}
}

// Decide whether getCart.json must be requested. An empty cart that was
// already known as empty needs no request, unless a POST-like AJAX request
// could have changed it. On page load an unchanged cart hash needs no request
// either. After an AJAX request the cart is always requested when it has
// items, because WooCommerce does not refresh the cart hash cookie on every
// AJAX cart change (e.g. Store API quantity update).
function nzmShouldRequestCart(token, isPageLoad) {
	if (!nzmCartCookieGate || nzmCookieGateOff()) {
		return true;
	}
	var lastToken = nzmSessionGet('nzm_cart_token');
	if (token === 'empty') {
		if (lastToken !== 'empty' && (lastToken !== null || nzmLastCartHasItems())) {
			return true;
		}
		return !isPageLoad && nzmCartMutationXHR;
	}
	if (!isPageLoad || token === 'unknown') {
		return true;
	}
	return token !== lastToken;
}

function timestampGenerator(min, max) {
	min = Math.ceil(min);
	max = Math.floor(max);
	return Math.floor(Math.random() * (max - min + 1)) + min;
}

function NewsmanAutoEvents() {
	var paramChar,
		timestamp;
	
	if (!endTimePassed()) {
		NewsmanDebugLog('newsman remarketing: execution stopped at the beginning, ' + nzmsecondsAllow + ' seconds did not pass between requests');
		return;
	}
	
	if (nzmisError && nzmisProd === true) {
		console.log('newsman remarketing: an error occurred, set nzmisProd = false in console, script execution stopped;');
		return;
	}
	
	let xhr = new XMLHttpRequest()
	if (nzmbufferedXHR || nzmfirstLoad) {
		var nzmCartToken = nzmCartCookieToken();
		var nzmRequestCart = nzmShouldRequestCart(nzmCartToken, nzmfirstLoad);
		nzmCartMutationXHR = false;
		if (!nzmRequestCart) {
			NewsmanDebugLog('newsman remarketing: cart cookies unchanged, request not sent');
			nzmfirstLoad = false;
			nzmbufferedXHR = false;
			return;
		}

		paramChar = '?t=';
		
		if (newsmanCartAjaxUrl.indexOf('?') >= 0) {
			paramChar = '&t=';
		}
		
		timestamp = paramChar + Date.now() + timestampGenerator(999, 999999999);
		
		try {
			xhr.open('GET', newsmanCartAjaxUrl + timestamp, true);
		} catch (ex) {
			NewsmanDebugLog('newsman remarketing: malformed XHR url');
			nzmisError = true;
		}
		
		startTimePassed();
		// Reset when the request is sent, not when it completes, so a cart
		// change finished while it is in flight triggers one more request.
		nzmfirstLoad = false;
		nzmbufferedXHR = false;
		
		xhr.onload = function() {
			if (xhr.status == 200 || xhr.status == 201) {
				try {
					var response = JSON.parse(xhr.responseText);
				} catch (error) {
					NewsmanDebugLog('newsman remarketing: error occurred json parsing response');
					nzmisError = true;
					return;
				}

				// Items in the cart without the WooCommerce cookie means the cookies
				// can't be read on this site, so stop skipping requests.
				if (nzmCartToken === 'empty' && response.length > 0) {
					try { localStorage.setItem('nzm_cart_cookie_off', '1'); } catch (e) {}
				}
				nzmSessionSet('nzm_cart_token', nzmCartToken);

				//check for engine name
				nzmlastCart = JSON.parse(sessionStorage.getItem('lastCart'));
				if (nzmlastCart === null) {
					nzmlastCart = {};
					NewsmanDebugLog('newsman remarketing: nzmlastCart === null');
				}
				
				//check cache
				if ((typeof nzmlastCart !== 'undefined') && nzmlastCart != null && nzmlastCart.length > 0 && (typeof response !== 'undefined') && response != null && response.length > 0) {
					var objComparer = response;
					var missingProp = false;
					
					nzmlastCart.forEach(e=>{
							if (!e.hasOwnProperty('name')) {
								missingProp = true;
							}
						}
					);
					
					if (missingProp) {
						objComparer.forEach(function (v) {
							delete v.name
						});
					}
					
					if (JSON.stringify(nzmlastCart) === JSON.stringify(objComparer)) {
						NewsmanDebugLog('newsman remarketing: cache loaded, cart is unchanged');
						nzmlastCartFlag = true;
					} else {
						nzmlastCartFlag = false;
						NewsmanDebugLog('newsman remarketing: cache loaded, cart is changed');
					}
				}
				
				if (response.length > 0 && nzmlastCartFlag == false) {
					nzmAddToCart(response);
				} else if (!response.length && nzmlastCart.length > 0 && nzmunlockClearCart) {
					//send only when on last request, products existed
					nzmClearCart();
					NewsmanDebugLog('newsman remarketing: clear cart sent');
				} else {
					NewsmanDebugLog('newsman remarketing: request not sent');
				}
			} else {
				NewsmanDebugLog('newsman remarketing: response http status code is not 200');
				nzmisError = true;
			}
		}
		try {
			xhr.send(null);
		} catch (ex) {
			NewsmanDebugLog('newsman remarketing: error on xhr send');
			nzmisError = true;
		}
	} else {
		NewsmanDebugLog('newsman remarketing: !buffered xhr || first load');
	}
}
function nzmClearCart() {
	_nzm.run('ec:setAction', 'clear_cart');
	_nzm.run('send', 'event', 'detail view', 'click', 'clearCart');
	sessionStorage.setItem('lastCart', JSON.stringify([]));
	nzmunlockClearCart = false;
}
function nzmAddToCart(response) {
	_nzm.run('ec:setAction', 'clear_cart');
	NewsmanDebugLog('newsman remarketing: clear cart sent, add to cart function');
	detailviewEvent(response);
}

function detailviewEvent(response) {
	NewsmanDebugLog('newsman remarketing: detailviewEvent execute');

	_nzm.run('send', 'event', 'detail view', 'click', 'clearCart', null, function() {
		var products = [],
			item;

		NewsmanDebugLog('newsman remarketing: executing add to cart callback');

		for (item in response) {
			if (response[item].hasOwnProperty('id')) {
				NewsmanDebugLog('ec:addProduct');
				_nzm.run('ec:addProduct', response[item]);
				products.push(response[item]);
				NewsmanDebugLog(response[item]);
			}
		}
		_nzm.run('ec:setAction', 'add');
		_nzm.run('send', 'event', 'UX', 'click', 'add to cart');
		sessionStorage.setItem('lastCart', JSON.stringify(products));
		nzmunlockClearCart = true;

		NewsmanDebugLog('newsman remarketing: cart sent');
	});
}

function detectClicks() {
	window.addEventListener('click', function() {
		nzmmsClick = new Date();
	}, false);
}

function detectXHR() {
	var proxied = window.XMLHttpRequest.prototype.send;
	var proxiedOpen = window.XMLHttpRequest.prototype.open;

	window.XMLHttpRequest.prototype.open = function(method) {
		try { this.nzmMethod = method; } catch (e) {}
		return proxiedOpen.apply(this, arguments);
	};

	window.XMLHttpRequest.prototype.send = function() {
		var pointer = this;
		var validate = false;
		var timeValidate = false;
		
		var intervalId = window.setInterval(function() {
			if (pointer.readyState != 4) {
				return;
			}
			var msClickPassed = new Date();
			var timeDiff = msClickPassed.getTime() - nzmmsClick.getTime();
			if (timeDiff > 5000) {
				validate = false;
			} else {
				timeValidate = true;
			}

			var _location = pointer.responseURL;

			// Own request exclusion.
			if (timeValidate) {
				if (_location.indexOf('<?php echo esc_html( $cart_param ); ?>') !== -1) {
					validate = false;
				} else if (_location.indexOf(window.location.origin) !== -1) {
					validate = true;
				}

				if (validate) {
					nzmbufferedXHR = true;
					if (nzmIsMutationMethod(pointer.nzmMethod)) {
						nzmCartMutationXHR = true;
					}
					NewsmanDebugLog('newsman remarketing: ajax request fired and caught from same domain, NewsmanAutoEvents called');
					NewsmanAutoEvents();
				}
			}

			clearInterval(intervalId);
		}, 1);

		return proxied.apply(this, [].slice.call(arguments));
	}
	;
}
function detectFetch() {
	if (typeof window.fetch !== 'function') {
		return;
	}
	var origFetch = window.fetch;

	window.fetch = function() {
		var reqUrl = '';
		var reqMethod = 'GET';
		try {
			var a0 = arguments[0];
			var a1 = arguments[1];
			reqUrl = typeof a0 === 'string' ? a0 : (a0 && a0.url) || '';
			reqMethod = (a1 && a1.method) || (a0 && typeof a0 !== 'string' && a0.method) || 'GET';
		} catch (e) {}

		var promise = origFetch.apply(this, arguments);

		promise.then(function(response) {
			var validate = false;
			var timeValidate = false;

			var msClickPassed = new Date();
			var timeDiff = msClickPassed.getTime() - nzmmsClick.getTime();
			if (timeDiff > 5000) {
				validate = false;
			} else {
				timeValidate = true;
			}

			var _location = (response && response.url) || reqUrl;

			if (timeValidate) {
				if (_location.indexOf('<?php echo esc_html( $cart_param ); ?>') !== -1) {
					validate = false;
				} else if (_location.indexOf(window.location.origin) !== -1) {
					validate = true;
				}

				if (validate) {
					nzmbufferedXHR = true;
					if (nzmIsMutationMethod(reqMethod)) {
						nzmCartMutationXHR = true;
					}
					NewsmanDebugLog('newsman remarketing: fetch fired and caught from same domain, NewsmanAutoEvents called');
					NewsmanAutoEvents();
				}
			}
		}).catch(function(){});

		return promise;
	};
}
function NewsmanDebugLog($message) {
	if ((typeof nzmisProd !== 'undefined') && nzmisProd === true) {
		return;
	}
	console.log($message);
}

<?php
if ( $this->remarketing_config->is_woo_commerce_page() ) {
	echo "_nzm.run( 'require', 'ec' );";
}
?>
</script>
