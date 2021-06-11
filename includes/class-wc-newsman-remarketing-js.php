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

	/**
	 * @var string
	 */
	public static $endpoint = "https://retargeting.newsmanapp.com/js/retargeting/track.js";
	public static $endpointHost = "https://retargeting.newsmanapp.com";

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
		return apply_filters('woocommerce_ga_tracker_variable', '_nzm.run');
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
		$logged_in = is_user_logged_in() ? 'yes' : 'no';
		if(current_user_can('administrator')){
			return "";		  
		}	 
	
		if (!empty(get_option('newsman_remarketingid')))
		{
			add_action('wp_footer', array('WC_Newsman_Remarketing_JS', 'universal_analytics_footer'));
			return self::load_analytics_universal($logged_in);
		} else
		{
			add_action('wp_footer', array('WC_Newsman_Remarketing_JS', 'universal_analytics_footer'));
			return self::load_analytics_universal($logged_in);
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
				echo("
					<script>
					(function($) {
						$( '.products .post-" . esc_js($product->get_id()) . " a' ).click( function() {
							if ( true === $(this).hasClass( 'add_to_cart_button' ) ) {
								return;
							}

							" . self::tracker_var() . "( 'ec:addProduct', {
								'id': '" . esc_js($product->get_id()) . "',
								'name': '" . esc_js($product->get_title()) . "',
								'category': " . self::product_get_category_line($product) . "
								'position': '" . esc_js($position) . "'
							});

							" . self::tracker_var() . "( 'send', 'pageview', '_ecommerce', 'pageview', ' " . esc_js($list) . "' );
						});
					})(jQuery);
					</script>
				");
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
	public static function load_analytics_universal($logged_in)
	{	  
		$remarketingid = get_option('newsman_remarketingid');

		$ga_snippet_head = "
		var _nzm = _nzm || []; var _nzm_config = _nzm_config || [];
		_nzm_config['disable_datalayer'] = 1;
		_nzm_tracking_server = '" . self::$endpointHost . "';
        (function() {var a, methods, i;a = function(f) {return function() {_nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
        }};methods = ['identify', 'track', 'run'];for(i = 0; i < methods.length; i++) {_nzm[methods[i]] = a(methods[i])};
        s = document.getElementsByTagName('script')[0];var script_dom = document.createElement('script');script_dom.async = true;
        script_dom.id = 'nzm-tracker';script_dom.setAttribute('data-site-id', '" . esc_js($remarketingid) . "');
        script_dom.src = '" . self::$endpoint . "';s.parentNode.insertBefore(script_dom, s);})();
        ";
		
		$ga_snippet_require = "";

		if (is_woocommerce() || is_cart() || (is_checkout()))
		{
			$ga_snippet_require .= "" . self::tracker_var() . "( 'require', 'ec' );";
		}

		$ga_snippet_head = apply_filters('woocommerce_ga_snippet_head', $ga_snippet_head);		
		$ga_snippet_require = apply_filters('woocommerce_ga_snippet_require', $ga_snippet_require);
	
		$code = $ga_snippet_head . $ga_snippet_require;
		$code = apply_filters('woocommerce_ga_snippet_output', $code);

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
		$code = "" . self::tracker_var() . "( 'set', 'currencyCode', '" . esc_js(version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency()) . "' );";
		$email = $order->get_billing_email();
		$f = $order->get_billing_first_name();
		$l = $order->get_billing_last_name();

		$code .= "
		/*
		//obsolete
		function wait_to_load_and_identifypurchase() {
			if (typeof _nzm.get_tracking_id === 'function') {
				if (_nzm.get_tracking_id() == '') {
		 _nzm.identify({ email: \"$email\", first_name: \"$f\", last_name: \"$l\" });
				}
			} else {
				setTimeout(function() {wait_to_load_and_identifypurchase()}, 50)
			}
		}
		wait_to_load_and_identifypurchase();
		*/
		
		_nzm.identify({ email: \"$email\", first_name: \"$f\", last_name: \"$l\" });
		";

		// Order items
		if ($order->get_items())
		{
			foreach ($order->get_items() as $item)
			{
				$code .= self::add_item_enhanced($order, $item);
			}
		}

		$code .= "" . self::tracker_var() . "( 'ec:setAction', 'purchase', {
			'id': '" . esc_js($order->get_order_number()) . "',
			'affiliation': '" . esc_js(get_bloginfo('name')) . "',
			'revenue': '" . esc_js($order->get_total()) . "',
			'tax': '" . esc_js($order->get_total_tax()) . "',
			'shipping': '" . esc_js($order->get_total_shipping()) . "'
		} );";
		
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
		//$code .= "'id': '" . esc_js($_product->get_sku() ? $_product->get_sku() : $_product->get_id()) . "',";
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
		echo("
			<script>

			(function($) {
			
			$('.remove').each(function(index) {
  		   		 $(this).unbind('click').click( function(){					 

			        " . self::tracker_var() . "( 'ec:addProduct', {
						'id': ($(this).data('product_id')) ? ($(this).data('product_id')) : ($(this).data('product_sku')),
						'quantity': $(this).parent().parent().find( '.qty' ).val() ? $(this).parent().parent().find( '.qty' ).val() : '1',
					} );
					" . self::tracker_var() . "( 'ec:setAction', 'remove' );
					" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'remove from cart' );
	 
				});
			});						

			})(jQuery);
			</script>

				<script>
			(function($) {
			//$( document.body ).on( 'updated_cart_totals', function(){
			$('button[name=\"update_cart\"]').click(function(){

  			 	$('.shop_table tr.cart_item').each(function () {

					var id = $(this).find('.product-remove a').attr('data-product_id');
					var qty = $(this).find('.product-quantity input').val();

        				" . self::tracker_var() . "( 'ec:addProduct', {
						'id': id,
					} );
					" . self::tracker_var() . "( 'ec:setAction', 'remove' );
					" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'remove from cart' );


						" . self::tracker_var() . "( 'ec:addProduct', {
						'id': id,
						'quantity': qty,
					} );
					" . self::tracker_var() . "( 'ec:setAction', 'add' );
					" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'add to cart' );

			    });
			  
			});
			//});

			})(jQuery);
			</script>
		");
	}

	function add_cart($id, $qty)
	{
		echo("
			<script>
			(function($) {
					$('button[name=\"update_cart\"]').click(function(){
					" . self::tracker_var() . "( 'ec:addProduct', {
						'id': '" . $id . "',
						'quantity': '" . $qty . "',
					} );
					" . self::tracker_var() . "( 'ec:setAction', 'add' );
					" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'add to cart' );
				});
			})(jQuery);
			</script>
		");
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
	//	'id': '" . esc_js($product->get_sku() ? $product->get_sku() : ('#' . $product->get_id())) . "',
	function checkout_process($cart)
	{
		$code = "";

		foreach ($cart as $cart_item_key => $cart_item)
		{
			$product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
			$variant = self::product_get_variant_line($product);
			$code .= "" . self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js($product->get_id() ? $product->get_id() : ($product->get_sku())) . "',
				'name': '" . esc_js($product->get_title()) . "',
				'category': " . self::product_get_category_line($product);

			if ('' !== $variant)
			{
				$code .= "'variant': " . $variant;
			}

			$code .= "'price': '" . esc_js($product->get_price()) . "',
				'quantity': '" . esc_js($cart_item['quantity']) . "'
			} );";
		}

		$code .= "" . self::tracker_var() . "( 'ec:setAction','checkout' );";

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
		$parameters = apply_filters('woocommerce_ga_event_tracking_parameters', $parameters);	

		wc_enqueue_js("		

					$( '" . $selector . "' ).click( function() {				
						" . $parameters['enhanced'] . "
						" . self::tracker_var() . "( 'ec:setAction', 'add' );
						" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'add to cart' );					
					});
				");
	}

}
