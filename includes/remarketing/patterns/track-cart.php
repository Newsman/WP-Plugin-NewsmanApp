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
$site_url   = get_site_url();
$cart_param = \Newsman\Remarketing\Cart\Handler\CartAjax::CART_PARAMETER;
?>
<script<?php esc_js( esc_html( $this->get_script_tag_additional_attributes() ) ); ?>>
const newsmanCartAjaxUrl = "<?php echo esc_url( rtrim( $site_url, '/' ) . '/' ); ?>" + '?newsman_cart=<?php echo esc_html( $cart_param ); ?>';
var nzmisProd = true;
let nzmlastCart = sessionStorage.getItem('lastCart');
if (nzmlastCart === null) {
	nzmlastCart = {};
}
var nzmlastCartFlag = false;
var nzmfirstLoad = true;
var nzmbufferedXHR = false;
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
		
		xhr.onload = function() {
			if (xhr.status == 200 || xhr.status == 201) {
				try {
					var response = JSON.parse(xhr.responseText);
				} catch (error) {
					NewsmanDebugLog('newsman remarketing: error occurred json parsing response');
					nzmisError = true;
					return;
				}
				
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
				
				nzmfirstLoad = false;
				nzmbufferedXHR = false;
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
		try {
			var a0 = arguments[0];
			reqUrl = typeof a0 === 'string' ? a0 : (a0 && a0.url) || '';
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
