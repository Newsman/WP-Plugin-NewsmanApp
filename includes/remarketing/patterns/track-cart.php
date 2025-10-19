<?php
/**
 * Title: Newsman remarketing tracking script
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
$run        = $this->remarketing_config->get_js_track_run_func();
$cart_param = \Newsman\Remarketing\Cart\Handler\CartAjax::CART_PARAMETER;
?>
<script<?php esc_js( esc_html( $this->get_script_tag_additional_attributes() ) ); ?>>
var ajaxurl = document.location.protocol + '://' + document.location.hostname + '?newsman_cart=<?php echo esc_html( $cart_param ); ?>';
var isProd = true;
let lastCart = sessionStorage.getItem('lastCart');
if (lastCart === null) {
	lastCart = {};
}
var lastCartFlag = false;
var firstLoad = true;
var bufferedXHR = false;
var unlockClearCart = true;
var isError = false;
let secondsAllow = 5;
let msRunAutoEvents = 5000;
let msClick = new Date();
var documentComparer = document.location.hostname;
var documentUrl = document.URL;
var sameOrigin = (documentUrl.indexOf(documentComparer) !== -1);
let startTime, endTime;

function startTimePassed() {
	startTime = new Date();
}

function endTimePassed() {
	var flag = false,
		timeDiff,
		seconds;
	
	endTime = new Date();
	timeDiff = endTime - startTime;
	timeDiff /= 1000;
	
	if (firstLoad) {
		flag = true;
	}

	seconds = Math.round(timeDiff);
	if (seconds >= secondsAllow) {
		flag = true;
	}

	return flag;
}

startTimePassed();

if (sameOrigin) {
	NewsmanAutoEvents();
	setInterval(NewsmanAutoEvents, msRunAutoEvents);
	detectClicks();
	detectXHR();
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
		NewsmanDebugLog('newsman remarketing: execution stopped at the beginning, ' + secondsAllow + ' seconds did not pass between requests');
		return;
	}
	
	if (isError && isProd === true) {
		console.log('newsman remarketing: an error occurred, set isProd = false in console, script execution stopped;');
		return;
	}
	
	let xhr = new XMLHttpRequest()
	if (bufferedXHR || firstLoad) {
		paramChar = '?t=';
		
		if (ajaxurl.indexOf('?') >= 0) {
			paramChar = '&t=';
		}
		
		timestamp = paramChar + Date.now() + timestampGenerator(999, 999999999);
		
		try {
			xhr.open('GET', ajaxurl + timestamp, true);
		} catch (ex) {
			NewsmanDebugLog('newsman remarketing: malformed XHR url');
			isError = true;
		}
		
		startTimePassed();
		
		xhr.onload = function() {
			if (xhr.status == 200 || xhr.status == 201) {
				try {
					var response = JSON.parse(xhr.responseText);
				} catch (error) {
					NewsmanDebugLog('newsman remarketing: error occurred json parsing response');
					isError = true;
					return;
				}
				
				//check for engine name
				lastCart = JSON.parse(sessionStorage.getItem('lastCart'));
				if (lastCart === null) {
					lastCart = {};
					NewsmanDebugLog('newsman remarketing: lastCart === null');
				}
				
				//check cache
				if ((typeof lastCart !== 'undefined') && lastCart != null && lastCart.length > 0 && (typeof response !== 'undefined') && response != null && response.length > 0) {
					var objComparer = response;
					var missingProp = false;
					
					lastCart.forEach(e=>{
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
					
					if (JSON.stringify(lastCart) === JSON.stringify(objComparer)) {
						NewsmanDebugLog('newsman remarketing: cache loaded, cart is unchanged');
						lastCartFlag = true;
					} else {
						lastCartFlag = false;
						NewsmanDebugLog('newsman remarketing: cache loaded, cart is changed');
					}
				}
				
				if (response.length > 0 && lastCartFlag == false) {
					nzmAddToCart(response);
				} else if (!response.length && lastCart.length > 0 && unlockClearCart) {
					//send only when on last request, products existed
					nzmClearCart();
					NewsmanDebugLog('newsman remarketing: clear cart sent');
				} else {
					NewsmanDebugLog('newsman remarketing: request not sent');
				}
				
				firstLoad = false;
				bufferedXHR = false;
			} else {
				NewsmanDebugLog('newsman remarketing: response http status code is not 200');
				isError = true;
			}
		}
		try {
			xhr.send(null);
		} catch (ex) {
			NewsmanDebugLog('newsman remarketing: error on xhr send');
			isError = true;
		}
	} else {
		NewsmanDebugLog('newsman remarketing: !buffered xhr || first load');
	}
}
function nzmClearCart() {
	<?php echo esc_js( esc_html( $run ) ); ?>('ec:setAction', 'clear_cart');
	<?php echo esc_js( esc_html( $run ) ); ?>('send', 'event', 'detail view', 'click', 'clearCart');
	sessionStorage.setItem('lastCart', JSON.stringify([]));
	unlockClearCart = false;
}
function nzmAddToCart(response) {
	<?php echo esc_js( esc_html( $run ) ); ?>('ec:setAction', 'clear_cart');
	NewsmanDebugLog('newsman remarketing: clear cart sent, add to cart function');
	detailviewEvent(response);
}

function detailviewEvent(response) {
	NewsmanDebugLog('newsman remarketing: detailviewEvent execute');

	<?php echo esc_js( esc_html( $run ) ); ?>('send', 'event', 'detail view', 'click', 'clearCart', null, function() {
		var products = [],
			item;
		
		NewsmanDebugLog('newsman remarketing: executing add to cart callback');
		
		for (item in response) {
			if (response[item].hasOwnProperty('id')) {
				NewsmanDebugLog('ec:addProduct');
				<?php echo esc_js( esc_html( $run ) ); ?>('ec:addProduct', response[item]);
				products.push(response[item]);
				NewsmanDebugLog(response[item]);
			}
		}
		<?php echo esc_js( esc_html( $run ) ); ?>('ec:setAction', 'add');
		<?php echo esc_js( esc_html( $run ) ); ?>('send', 'event', 'UX', 'click', 'add to cart');
		sessionStorage.setItem('lastCart', JSON.stringify(products));
		unlockClearCart = true;
		
		NewsmanDebugLog('newsman remarketing: cart sent');
	});
}

function detectClicks() {
	window.addEventListener('click', function() {
		msClick = new Date();
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
			var timeDiff = msClickPassed.getTime() - msClick.getTime();
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
					bufferedXHR = true;
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
function NewsmanDebugLog($message) {
	if ((typeof isProd !== 'undefined') && isProd === true) {
		return;
	}
	console.log($message);
}

<?php
if ( $this->is_woo_commerce_page() ) {
	echo esc_js( esc_html( $run ) ) . "( 'require', 'ec' );";
}
?>
</script>
