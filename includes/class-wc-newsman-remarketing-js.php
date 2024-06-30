<?php
if (!defined('ABSPATH'))
{
	exit;
}

/**
 * WC_Newsman_Remarketing_JS class
 *
 * JS for recording Google Analytics info
 */
class WC_Newsman_Remarketing_JS
{

	/** @var object Class Instance */
	private static $instance;

	/** @var array Inherited Analytics options */
	private static $options;

	/**
	 * Get the class instance
	 */
	public static function get_instance($options = array())
	{
		return null === self::$instance ? (self::$instance = new self($options)) : self::$instance;
	}

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 */
	public function __construct($options = array())
	{
		self::$options = $options;
	}

	/**
	 * Return one of our options
	 * @param  string $option Key/name for the option
	 * @return string         Value of the option
	 */
	public static function get($option)
	{
		return self::$options[$option];
	}

	/**
	 * Returns the tracker variable this integration should use
	 */
	public static function tracker_var()
	{	
		//return apply_filters('woocommerce_ga_tracker_variable', '_nzm.run');
		return '_nzm.run';
	}

	/**
	 * Generic GA / header snippet for opt out
	 */
	public static function header()
	{
		return "";
	}

	/**
	 * Loads the correct Google Analytics code (classic or universal)
	 * @param  boolean $order Classic analytics needs order data to set the currency correctly
	 * @return string         Analytics loading code
	 */
	public static function load_analytics($order = false)
	{
		//$logged_in = is_user_logged_in() ? 'yes' : 'no';
		if(current_user_can('administrator')){
			return "";		  
		}	 
	
		if (!empty(get_option('newsman_remarketingid')))
		{
			add_action('wp_footer', array('WC_Newsman_Remarketing_JS', 'universal_analytics_footer'));
			return self::load_analytics_universal();
		} else
		{
			add_action('wp_footer', array('WC_Newsman_Remarketing_JS', 'universal_analytics_footer'));
			return self::load_analytics_universal();
		}

		add_action( 'before_woocommerce_init', 'before_woocommerce_hpos' );
		function before_woocommerce_hpos() { 
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) { 
			   \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true ); 
		   } 
		}
	}

	/**
	 * Builds the addImpression object
	 */
	public static function listing_impression($product, $position)
	{
		if(!current_user_can('administrator')){				

			if (isset($_GET['s']))
			{
				$list = "Search Results";
			} else
			{
				$list = "Product List";
			}

			$remarketingid = get_option('newsman_remarketingid');
			if(!empty($remarketingid))
			{				
				wc_enqueue_js("				
					" . self::tracker_var() . "( 'ec:addImpression', {
						'id': '" . esc_js($product->get_id()) . "',
						'name': '" . esc_js($product->get_title()) . "',
						'category': " . self::product_get_category_line($product) . "
						'list': '" . esc_js($list) . "',
						'position': '" . esc_js($position) . "'
					} );
				");
			}

		}
	}

	/**
	 * Builds an addProduct and click object
	 */
	public static function listing_click($product, $position)
	{
		if(!current_user_can('administrator')){		

			if (isset($_GET['s']))
			{
				$list = "Search Results";
			} else
			{
				$list = "Product List";
			}

			$remarketingid = get_option('newsman_remarketingid');
			if(!empty($remarketingid))
			{
				echo("");
			}

		}
	}

	/**
	 * Sends the pageview last thing (needed for things like addImpression)
	 */
	public static function universal_analytics_footer()
	{
		wc_enqueue_js("" . self::tracker_var() . "( 'send', 'pageview' ); ");
	}

	/**
	 * Loads the universal analytics code
	 * @param  string $logged_in 'yes' if the user is logged in, no if not (this is a string so we can pass it to GA)
	 * @return string Universal Analytics Code
	 */
	public static function load_analytics_universal()
	{	  
		$remarketingid = get_option('newsman_remarketingid');

		$ga_snippet_head = "

		//Newsman remarketing tracking code REPLACEABLE

		var remarketingid = '$remarketingid';
		var _nzmPluginInfo = '2.6.6:woocommerce';
		
		//Newsman remarketing tracking code REPLACEABLE

		//Newsman remarketing tracking code  

		var endpoint = 'https://retargeting.newsmanapp.com';
		var remarketingEndpoint = endpoint + '/js/retargeting/track.js';

		var _nzm = _nzm || [];
		var _nzm_config = _nzm_config || [];
		_nzm_config['disable_datalayer'] = 1;
		_nzm_tracking_server = endpoint;
		(function() {
			var a, methods, i;
			a = function(f) {
				return function() {
					_nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
				}
			};
			methods = ['identify', 'track', 'run'];
			for (i = 0; i < methods.length; i++) {
				_nzm[methods[i]] = a(methods[i])
			};
			s = document.getElementsByTagName('script')[0];
			var script_dom = document.createElement('script');
			script_dom.async = true;
			script_dom.id = 'nzm-tracker';
			script_dom.setAttribute('data-site-id', remarketingid);
			script_dom.src = remarketingEndpoint;

			if (_nzmPluginInfo.indexOf('shopify') !== -1) {
				script_dom.onload = function(){
					if (typeof newsmanRemarketingLoad === 'function')
						newsmanRemarketingLoad();
				}
			}
			s.parentNode.insertBefore(script_dom, s);
		})();
		_nzm.run('require', 'ec');

		//Newsman remarketing tracking code 

		//Newsman remarketing auto events REPLACEABLE

		var ajaxurl = 'https://' + document.location.hostname + '?newsman=getCart.json';

		//Newsman remarketing auto events REPLACEABLE

		//Newsman remarketing auto events

		var isProd = true;
		let lastCart = sessionStorage.getItem('lastCart');
		if (lastCart === null)
			lastCart = {};
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
		;startTimePassed();
		function endTimePassed() {
			var flag = false;
			endTime = new Date();
			var timeDiff = endTime - startTime;
			timeDiff /= 1000;
			var seconds = Math.round(timeDiff);
			if (firstLoad)
				flag = true;
			if (seconds >= secondsAllow)
				flag = true;
			return flag;
		}
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
			if (!endTimePassed()) {
				if (!isProd)
					console.log('newsman remarketing: execution stopped at the beginning, ' + secondsAllow + ' seconds didn\"t pass between requests');
				return;
			}
			if (isError && isProd == true) {
				console.log('newsman remarketing: an error occurred, set isProd = false in console, script execution stopped;');
				return;
			}
			let xhr = new XMLHttpRequest()
			if (bufferedXHR || firstLoad) {
				var paramChar = '?t=';
				if (ajaxurl.indexOf('?') >= 0)
					paramChar = '&t=';
				var timestamp = paramChar + Date.now() + timestampGenerator(999, 999999999);
				try {
					xhr.open('GET', ajaxurl + timestamp, true);
				} catch (ex) {
					if (!isProd)
						console.log('newsman remarketing: malformed XHR url');
					isError = true;
				}
				startTimePassed();
				xhr.onload = function() {
					if (xhr.status == 200 || xhr.status == 201) {
						try {
							var response = JSON.parse(xhr.responseText);
						} catch (error) {
							if (!isProd)
								console.log('newsman remarketing: error occured json parsing response');
							isError = true;
							return;
						}
						//check for engine name
						//if shopify
						if (_nzmPluginInfo.indexOf('shopify') !== -1) {
							if (!isProd)
								console.log('newsman remarketing: shopify detected, products will be pushed with custom props');
							var products = [];
							if (response.item_count > 0) {
								response.items.forEach(function(item) {
									products.push({
										'id': item.id,
										'name': item.product_title,
										'quantity': item.quantity,
										'price': parseFloat(item.price)
									});
								});
							}
							response = products;
						}
						lastCart = JSON.parse(sessionStorage.getItem('lastCart'));
						if (lastCart === null) {
							lastCart = {};
							if (!isProd)
								console.log('newsman remarketing: lastCart === null');
						}
						//check cache
						if (lastCart.length > 0 && lastCart != null && lastCart != undefined && response.length > 0 && response != null && response != undefined) {
							var objComparer = response;
							var missingProp = false;
							lastCart.forEach(e=>{
								if (!e.hasOwnProperty('name')) {
									missingProp = true;
								}
							}
							);
							if (missingProp)
								objComparer.forEach(function(v) {
									delete v.name
								});
							if (JSON.stringify(lastCart) === JSON.stringify(objComparer)) {
								if (!isProd)
									console.log('newsman remarketing: cache loaded, cart is unchanged');
								lastCartFlag = true;
							} else {
								lastCartFlag = false;
								if (!isProd)
									console.log('newsman remarketing: cache loaded, cart is changed');
							}
						}
						if (response.length > 0 && lastCartFlag == false) {
							nzmAddToCart(response);
						}//send only when on last request, products existed
						else if (response.length == 0 && lastCart.length > 0 && unlockClearCart) {
							nzmClearCart();
							if (!isProd)
								console.log('newsman remarketing: clear cart sent');
						} else {
							if (!isProd)
								console.log('newsman remarketing: request not sent');
						}
						firstLoad = false;
						bufferedXHR = false;
					} else {
						if (!isProd)
							console.log('newsman remarketing: response http status code is not 200');
						isError = true;
					}
				}
				try {
					xhr.send(null);
				} catch (ex) {
					if (!isProd)
						console.log('newsman remarketing: error on xhr send');
					isError = true;
				}
			} else {
				if (!isProd)
					console.log('newsman remarketing: !buffered xhr || first load');
			}
		}
		function nzmClearCart() {
			_nzm.run('ec:setAction', 'clear_cart');
			_nzm.run('send', 'event', 'detail view', 'click', 'clearCart');
			sessionStorage.setItem('lastCart', JSON.stringify([]));
			unlockClearCart = false;
		}
		function nzmAddToCart(response) {
			_nzm.run('ec:setAction', 'clear_cart');
			if (!isProd)
				console.log('newsman remarketing: clear cart sent, add to cart function');
			detailviewEvent(response);
		}
		function detailviewEvent(response) {
			if (!isProd)
				console.log('newsman remarketing: detailviewEvent execute');
			_nzm.run('send', 'event', 'detail view', 'click', 'clearCart', null, function() {
				if (!isProd)
					console.log('newsman remarketing: executing add to cart callback');
				var products = [];
				for (var item in response) {
					if (response[item].hasOwnProperty('id')) {
						_nzm.run('ec:addProduct', response[item]);
						products.push(response[item]);
					}
				}
				_nzm.run('ec:setAction', 'add');
				_nzm.run('send', 'event', 'UX', 'click', 'add to cart');
				sessionStorage.setItem('lastCart', JSON.stringify(products));
				unlockClearCart = true;
				if (!isProd)
					console.log('newsman remarketing: cart sent');
			});
		}
		function detectClicks() {
			window.addEventListener('click', function(event) {
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
					//own request exclusion
					if (timeValidate) {
						if (_location.indexOf('getCart.json') >= 0 || //magento 2.x
						_location.indexOf('/static/') >= 0 || _location.indexOf('/pub/static') >= 0 || _location.indexOf('/customer/section') >= 0 || //opencart 1
						_location.indexOf('getCart=true') >= 0 || //shopify
						_location.indexOf('cart.js') >= 0) {
							validate = false;
						} else {
							//check for engine name
							if (_nzmPluginInfo.indexOf('shopify') !== -1) {
								validate = true;
							} else {
								if (_location.indexOf(window.location.origin) !== -1)
									validate = true;
							}
						}
						if (validate) {
							bufferedXHR = true;
							if (!isProd)
								console.log('newsman remarketing: ajax request fired and catched from same domain, NewsmanAutoEvents called');
							NewsmanAutoEvents();
						}
					}
					clearInterval(intervalId);
				}, 1);
				return proxied.apply(this, [].slice.call(arguments));
			}
			;
		}

		//Newsman remarketing auto events
        ";
		
		$ga_snippet_require = "";

		if (is_woocommerce() || is_cart() || (is_checkout()))
		{
			$ga_snippet_require .= "" . self::tracker_var() . "( 'require', 'ec' );";
		}
	
		$ga_snippet_head = $ga_snippet_head;		
		$ga_snippet_require = $ga_snippet_require;

		$code = $ga_snippet_head . $ga_snippet_require;

		return $code;
	}

	/**
	 * Used to pass transaction data to Google Analytics
	 * @param object $order WC_Order Object
	 * @return string Add Transaction code
	 */
	function add_transaction($order)
	{
		return self::add_transaction_enhanced($order);
	}

	/**
	 * Enhanced Ecommerce Universal Analytics transaction tracking
	 */
	function add_transaction_enhanced($order)
	{		
		if (defined('WC_VERSION') && version_compare(WC_VERSION, '7.1.0', '>=')) {
			if (function_exists('wc_get_container') && class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableDataStore')) {
				$order_data_store = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableDataStore::class);
			} else {
				$order_data_store = WC_Data_Store::load('order');
			}
		} else {
			$order_data_store = WC_Data_Store::load('order');
		}

		$code = "" . self::tracker_var() . "( 'set', 'currencyCode', '" . esc_js(version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency()) . "' );";
		$email = $order->get_billing_email();
		$f = $order->get_billing_first_name();
		$l = $order->get_billing_last_name();

		// Order items
		if ($order->get_items())
		{
			foreach ($order->get_items() as $item)
			{
				$code .= self::add_item_enhanced($order, $item);
			}
		}

		$code .= "

		var orderV = localStorage.getItem('" . esc_js($order->get_order_number()) . "');

		var orderN = '" . esc_js($order->get_order_number()) . "';
		localStorage.setItem(orderN, 'true');

			if(orderV == undefined || orderV == null)
			{
			" . self::tracker_var() . "( 'ec:setAction', 'purchase', {
				'id': '" . esc_js($order->get_order_number()) . "',
				'affiliation': '" . esc_js(get_bloginfo('name')) . "',
				'revenue': '" . esc_js($order->get_total()) . "',
				'tax': '" . esc_js($order->get_total_tax()) . "',
				'shipping': '" . esc_js($order->get_total_shipping()) . "'
			} );
		
		}
		
		";
		
		wc_enqueue_js($code);

		return $code;
	}

	/**
	 * Add Item (Enhanced, Universal)
	 * @param object $order WC_Order Object
	 * @param array $item The item to add to a transaction/order
	 */
	function add_item_enhanced($order, $item)
	{
		$_product = version_compare(WC_VERSION, '3.0', '<') ? $order->get_product_from_item($item) : $item->get_product();
		$variant = self::product_get_variant_line($_product);

		$code = "" . self::tracker_var() . "( 'ec:addProduct', {";
		$code .= "'id': '" . esc_js($_product->get_id() ? $_product->get_id() : $_product->get_sku()) . "',";
		$code .= "'name': '" . esc_js($item['name']) . "',";
		$code .= "'category': " . self::product_get_category_line($_product);

		if ('' !== $variant)
		{
			$code .= "'variant': " . $variant;
		}

		$code .= "'price': '" . esc_js($order->get_item_total($item)) . "',";
		$code .= "'quantity': '" . esc_js($item['qty']) . "'";
		$code .= "});";

		return $code;
	}

	/**
	 * Returns a 'category' JSON line based on $product
	 * @param  object $product Product to pull info for
	 * @return string          Line of JSON
	 */
	private static function product_get_category_line($_product)
	{
		$out = array();
		$variation_data = version_compare(WC_VERSION, '3.0', '<') ? $_product->variation_data : ($_product->is_type('variation') ? wc_get_product_variation_attributes($_product->get_id()) : '');
		$categories = get_the_terms($_product->get_id(), 'product_cat');

		if (is_array($variation_data) && !empty($variation_data))
		{
			$parent_product = wc_get_product(version_compare(WC_VERSION, '3.0', '<') ? $_product->parent->id : $_product->get_parent_id());
			$categories = get_the_terms($parent_product->get_id(), 'product_cat');
		}

		if ($categories)
		{
			foreach ($categories as $category)
			{
				$out[] = $category->name;
			}
		}

		return "'" . esc_js(join("/", $out)) . "',";
	}

	/**
	 * Returns a 'variant' JSON line based on $product
	 * @param  object $product Product to pull info for
	 * @return string          Line of JSON
	 */
	private static function product_get_variant_line($_product)
	{
		$out = '';
		$variation_data = version_compare(WC_VERSION, '3.0', '<') ? $_product->variation_data : ($_product->is_type('variation') ? wc_get_product_variation_attributes($_product->get_id()) : '');

		if (is_array($variation_data) && !empty($variation_data))
		{
			$out = "'" . esc_js(wc_get_formatted_variation($variation_data, true)) . "',";
		}

		return $out;
	}

	/**
	 * Tracks an enhanced ecommerce remove from cart action
	 */
	function remove_from_cart()
	{
		echo("");
	}

	function add_cart($id, $qty)
	{
		echo("");
	}

	/**
	 * Tracks a product detail view
	 */
	function product_detail($product)
	{
		if (empty($product))
		{
			return;
		}

		wc_enqueue_js("
			" . self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js($product->get_id() ? $product->get_id() : ($product->get_sku())) . "',
				'name': '" . esc_js($product->get_title()) . "',
				'category': " . self::product_get_category_line($product) . "
				'price': '" . esc_js($product->get_price()) . "',
			} );

		" . self::tracker_var() . "( 'ec:setAction', 'detail' );");
	}

	/**
	 * Tracks when the checkout process is started
	 */
	function checkout_process($cart)
	{
		$code = "";

		wc_enqueue_js($code);
	}

	/**
	 * Add to cart
	 *
	 * @param array $parameters associative array of _trackEvent parameters
	 * @param string $selector jQuery selector for binding click event
	 *
	 * @return void
	 */
	public function event_tracking_code($parameters, $selector)
	{
		wc_enqueue_js("");
	}

}
