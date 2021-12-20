<?php
if (!defined('ABSPATH'))
{
	exit; // Exit if accessed directly
}

/**
 * Newsman Remarketing
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class   WC_Class_Newsman_Remarketing
 * @extends WC_Integration
 */
class WC_Class_Newsman_Remarketing extends WC_Integration
{

	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->id = 'newsman_remarketing';
		$this->method_title = __('Newsman Remarketing', 'newsman-remarketing-translate');
		$this->method_description = __('Setup your Newsman Remarketing <a href="/wp-admin/admin.php?page=NewsmanRemarketing">here</a>.', 'newsman-remarketing-translate');
		$this->dismissed_info_banner = get_option('woocommerce_dismissed_info_banner');

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();

		// Contains snippets/JS tracking code
		include_once('class-wc-newsman-remarketing-js.php');
		WC_Newsman_Remarketing_JS::get_instance($constructor);

		// Display an info banner on how to configure WooCommerce
		/*
		if (is_admin())
		{
			include_once('class-wc-newsman-remarketing-info-banner.php');
			$remarketingid = get_option('newsman_remarketingid');
			WC_Newsman_Remarketing_Info_Banner::get_instance($this->dismissed_info_banner, $remarketingid);
		}
		*/

		// Admin Options
		//add_filter('woocommerce_tracker_data', array($this, 'track_options'));
		//add_action('woocommerce_update_options_integration_newsman_remarketing', array($this, 'process_admin_options'));
		//add_action('woocommerce_update_options_integration_newsman_remarketing', array($this, 'show_options_info'));
		add_action('admin_enqueue_scripts', array($this, 'load_admin_assets'));

		// Tracking code
		add_action('wp_head', array($this, 'tracking_code_display'), 999999);

		// Event tracking code
		add_action('woocommerce_after_add_to_cart_button', array($this, 'add_to_cart'));
		add_action('wp_footer', array($this, 'loop_add_to_cart'));
		add_action('woocommerce_after_cart', array($this, 'remove_from_cart'));
		add_action('woocommerce_after_mini_cart', array($this, 'remove_from_cart'));
		add_filter('woocommerce_cart_item_remove_link', array($this, 'remove_from_cart_attributes'), 10, 2);
		add_action('woocommerce_after_shop_loop_item', array($this, 'listing_impression'));
		add_action('woocommerce_after_shop_loop_item', array($this, 'listing_click'));
		add_action('woocommerce_after_single_product', array($this, 'product_detail'));
		add_action('woocommerce_after_checkout_form', array($this, 'checkout_process'));

		// utm_nooverride parameter for Google AdWords
		add_filter('woocommerce_get_return_url', array($this, 'utm_nooverride'));
	}

	public function init_options()
	{
		$options = array(
			'remarketingid',
			/*'ga_set_domain_name',*/
			/*'ga_standard_tracking_enabled',
			'ga_support_display_advertising',
			'ga_support_enhanced_link_attribution',
			'ga_use_universal_analytics',
			'ga_anonymize_enabled',
			'ga_404_tracking_enabled',
			'ga_ecommerce_tracking_enabled',
			'ga_enhanced_ecommerce_tracking_enabled',
			'ga_enhanced_remove_from_cart_enabled',
			'ga_enhanced_product_impression_enabled',
			'ga_enhanced_product_click_enabled',
			'ga_enhanced_checkout_process_enabled',
			'ga_enhanced_product_detail_view_enabled',
			'ga_event_tracking_enabled'*/
		);

		$constructor = array();
		foreach ($options as $option)
		{
			$constructor[$option] = $this->$option = $this->get_option($option);
		}

		return $constructor;
	}

	/**
	 * Tells WooCommerce which settings to display under the "integration" tab
	 */
	public function init_form_fields()
	{
		/*
		$this->form_fields = array(		
			'ga_ecommerce_tracking_enabled' => array(
				'title' => __('Tracking Options', 'newsman-remarketing-translate'),
				'label' => __('Purchase Transactions', 'newsman-remarketing-translate'),
				'description' => __('This requires a payment gateway that redirects to the thank you/order received page after payment. Orders paid with gateways which do not do this will not be tracked.', 'newsman-remarketing-translate'),
				'type' => 'checkbox',
				'checkboxgroup' => 'start',
				'default' => get_option('woocommerce_ga_ecommerce_tracking_enabled') ? get_option('woocommerce_ga_ecommerce_tracking_enabled') : 'yes'  // Backwards compat
			),
			'ga_event_tracking_enabled' => array(
				'label' => __('Add to Cart Events', 'newsman-remarketing-translate'),
				'type' => 'checkbox',
				'checkboxgroup' => '',
				'default' => 'yes'
			),		

			'ga_enhanced_remove_from_cart_enabled' => array(
				'label' => __('Remove from Cart Events', 'newsman-remarketing-translate'),
				'type' => 'checkbox',
				'checkboxgroup' => '',
				'default' => 'yes',
				'class' => ''
			),

			'ga_enhanced_product_impression_enabled' => array(
				'label' => __('Product Impressions from Listing Pages', 'newsman-remarketing-translate'),
				'type' => 'checkbox',
				'checkboxgroup' => '',
				'default' => 'yes',
				'class' => ''
			),

			'ga_enhanced_product_click_enabled' => array(
				'label' => __('Product Clicks from Listing Pages', 'newsman-remarketing-translate'),
				'type' => 'checkbox',
				'checkboxgroup' => '',
				'default' => 'yes',
				'class' => ''
			),

			'ga_enhanced_product_detail_view_enabled' => array(
				'label' => __('Product Detail Views', 'newsman-remarketing-translate'),
				'type' => 'checkbox',
				'checkboxgroup' => '',
				'default' => 'yes',
				'class' => ''
			),

			'ga_enhanced_checkout_process_enabled' => array(
				'label' => __('Checkout Process Initiated', 'newsman-remarketing-translate'),
				'type' => 'checkbox',
				'checkboxgroup' => '',
				'default' => 'yes',
				'class' => ''
			),
		);
		*/
	}

	/**
	 * Shows some additional help text after saving the Google Analytics settings
	 */
	function show_options_info()
	{
		/*$this->method_description .= "<div class='notice notice-info'><p>" . __('Please allow time for Newsman Remarketing to start displaying results.', 'newsman-remarketing-translate') . "</p></div>";*/

		/*if ( isset( $_REQUEST['woocommerce_google_analytics_ga_ecommerce_tracking_enabled'] ) && true === (bool) $_REQUEST['woocommerce_google_analytics_ga_ecommerce_tracking_enabled'] ) {
			$this->method_description .= "<div class='notice notice-info'><p>" . __( 'Please note, for transaction tracking to work properly, you will need to use a payment gateway that redirects the customer back to a WooCommerce order received/thank you page.', 'newsman-remarketing-translate' ) . "</div>";
		}*/
	}

	function track_options($data)
	{
		/*$data['wc-google-analytics'] = array(
			'standard_tracking_enabled' => $this->ga_standard_tracking_enabled,
			'support_display_advertising' => $this->ga_support_display_advertising,
			'support_enhanced_link_attribution' => $this->ga_support_enhanced_link_attribution,
			'use_universal_analytics' => $this->ga_use_universal_analytics,
			'anonymize_enabled' => $this->ga_anonymize_enabled,
			'ga_404_tracking_enabled' => $this->ga_404_tracking_enabled,
			'ecommerce_tracking_enabled' => $this->ga_ecommerce_tracking_enabled,
			'event_tracking_enabled' => $this->ga_event_tracking_enabled
		);
		return $data;*/
	}

	/**
	 *
	 */
	function load_admin_assets()
	{
		$screen = get_current_screen();
		if ('woocommerce_page_wc-settings' !== $screen->id)
		{
			return;
		}

		if (empty($_GET['tab']))
		{
			return;
		}

		if ('integration' !== $_GET['tab'])
		{
			return;
		}
	}

	/**
	 * Display the tracking codes
	 * Acts as a controller to figure out which code to display
	 */
	public function tracking_code_display()
	{
		global $wp;
		$display_ecommerce_tracking = false;

		if ($this->disable_tracking())
		{
			return;
		}

		// Check if is order received page and stop when the products and not tracked
		if (is_order_received_page() && 'yes')
		{
			$order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 0;
			
			if (0 < $order_id)
			{
				$display_ecommerce_tracking = true;
				echo $this->get_ecommerce_tracking_code($order_id);
			}
		}

	//ENABLE all pages	if (is_woocommerce() || is_cart() || (is_checkout() && !$display_ecommerce_tracking))
	//	{
			$display_ecommerce_tracking = true;
			echo $this->get_standard_tracking_code();
	//	}
	}

	/**
	 * Standard Google Analytics tracking
	 */
	protected function get_standard_tracking_code()
	{
		$scriptLogic = "";

		if (is_user_logged_in())
		{
			$current_user = wp_get_current_user();
			$scriptLogic .= "

			/*
			//obsolete

			function wait_to_load_and_identify() {
				if (typeof _nzm.get_tracking_id === 'function') {
					if (_nzm.get_tracking_id() == '') {
						_nzm.identify({ email: \"" . esc_attr($current_user->user_email) . "\", first_name: \"" . esc_attr($current_user->user_firstname) . "\", last_name: \"" . esc_attr($current_user->user_lastname) . "\" });
					}
				} else {
					setTimeout(function() {wait_to_load_and_identify()}, 50)
				}
			}
		    
			wait_to_load_and_identify();
			*/

			_nzm.identify({ email: \"" . esc_attr($current_user->user_email) . "\", first_name: \"" . esc_attr($current_user->user_firstname) . "\", last_name: \"" . esc_attr($current_user->user_lastname) . "\" });
			";
		}

		return "
		" . WC_Newsman_Remarketing_JS::get_instance()->header() . "
		<script type='text/javascript'>" . WC_Newsman_Remarketing_JS::get_instance()->load_analytics() .
		$scriptLogic .
		"</script>
		";
	}

	/**
	 * eCommerce tracking
	 *
	 * @param int $order_id
	 */
	protected function get_ecommerce_tracking_code($order_id)
	{
		// Get the order and output tracking code.
		$order = wc_get_order($order_id);

		// Make sure we have a valid order object.
		if (!$order)
		{
			return '';
		}

		$code = WC_Newsman_Remarketing_JS::get_instance()->load_analytics($order);
		$code .= WC_Newsman_Remarketing_JS::get_instance()->add_transaction($order);

		// Mark the order as tracked.
		//update_post_meta($order_id, '_ga_tracked', 1);

		/*
		return "
		<!-- WooCommerce Newsman Remarketing -->
		" . WC_Newsman_Remarketing_JS::get_instance()->header() . "
		<script type='text/javascript'>$code</script>
		<!-- /WooCommerce Newsman Remarketing -->
		";
		*/
		
		return "";
	}

	/**
	 * Check if tracking is disabled
	 *
	 * @param string $type The setting to check
	 *
	 * @return bool True if tracking for a certain setting is disabled
	 */
	private function disable_tracking()
	{
		$remarketingid = get_option('newsman_remarketingid');

		if (is_admin() || current_user_can('manage_options') || empty($remarketingid))
		{
			return true;
		}
	}

	/**
	 * Newsman Remarketing event tracking for single product add to cart
	 *
	 * @return void
	 */
	public function add_to_cart()
	{
		if ($this->disable_tracking())
		{
			return;
		}
		if (!is_single())
		{
			return;
		}

		global $product;

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters = array();
		$parameters['category'] = "'" . __('Products', 'newsman-remarketing-translate') . "'";
		$parameters['action'] = "'" . __('Add to Cart', 'newsman-remarketing-translate') . "'";
		$parameters['label'] = "'" . esc_js($product->get_sku() ? __('ID:', 'newsman-remarketing-translate') . ' ' . $product->get_sku() : "#" . $product->get_id()) . "'";

		if (!$this->disable_tracking())
		{
			$code = "" . WC_Newsman_Remarketing_JS::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			//$code .= "'id': '" . esc_js($product->get_sku() ? $product->get_sku() : ('#' . $product->get_id())) . "',";
			$code .= "'id': '" . esc_js(($product->get_id()) ? ($product->get_id()) : $product->get_sku()) . "',";
			$code .= "'name': '" . esc_js($product->get_title()) . "',";
			$code .= "'quantity': $( 'input.qty' ).val() ? $( 'input.qty' ).val() : '1'";
			$code .= "} );";
			$parameters['enhanced'] = $code;
		}

		WC_Newsman_Remarketing_JS::get_instance()->event_tracking_code($parameters, '.single_add_to_cart_button');
	}

	/**
	 * Enhanced Analytics event tracking for removing a product from the cart
	 */
	public function remove_from_cart()
	{
		if ($this->disable_tracking())
		{
			return;
		}

		WC_Newsman_Remarketing_JS::get_instance()->remove_from_cart();
	}

	/**
	 * Adds the product ID and SKU to the remove product link if not present
	 */
	public function remove_from_cart_attributes($url, $key)
	{
		if (strpos($url, 'data-product_id') !== false)
		{
			return $url;
		}

		if (!is_object(WC()->cart))
		{
			return $url;
		}

		$item = WC()->cart->get_cart_item($key);
		$product = $item['data'];

		if (!is_object($product))
		{
			return $url;
		}

		$url = str_replace('href=', 'data-product_id="' . esc_attr($product->get_id()) . '" data-product_sku="' . esc_attr($product->get_sku()) . '" href=', $url);
		return $url;
	}

	/**
	 * Newsman Remarketing event tracking for loop add to cart
	 *
	 * @return void
	 */
	public function loop_add_to_cart()
	{
		if ($this->disable_tracking())
		{
			return;
		}

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters = array();
		$parameters['category'] = "'" . __('Products', 'newsman-remarketing-translate') . "'";
		$parameters['action'] = "'" . __('Add to Cart', 'newsman-remarketing-translate') . "'";
		$parameters['label'] = "($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id'))"; // Product SKU or ID

		if (!$this->disable_tracking())
		{
			$code = "" . WC_Newsman_Remarketing_JS::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			//$code .= "'id': ($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id')),";
			$code .= "'id': ($(this).data('product_id')) ?  ($(this).data('product_id')) : ($(this).data('product_sku')),";
			$code .= "'quantity': $(this).data('quantity')";
			$code .= "} );";
			$parameters['enhanced'] = $code;
		}

		WC_Newsman_Remarketing_JS::get_instance()->event_tracking_code($parameters, '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)');
	}

	/**
	 * Measures a listing impression (from search results)
	 */
	public function listing_impression()
	{
		if ($this->disable_tracking())
		{
			return;
		}

		global $product, $woocommerce_loop;
		WC_Newsman_Remarketing_JS::get_instance()->listing_impression($product, $woocommerce_loop['loop']);
	}

	/**
	 * Measure a product click from a listing page
	 */
	public function listing_click()
	{
		if ($this->disable_tracking())
		{
			return;
		}

		global $product, $woocommerce_loop;
		WC_Newsman_Remarketing_JS::get_instance()->listing_click($product, $woocommerce_loop['loop']);
	}

	/**
	 * Measure a product detail view
	 */
	public function product_detail()
	{
		if ($this->disable_tracking())
		{
			return;
		}

		global $product;
		WC_Newsman_Remarketing_JS::get_instance()->product_detail($product);
	}

	/**
	 * Tracks when the checkout form is loaded
	 */
	public function checkout_process($checkout)
	{
		if ($this->disable_tracking())
		{
			return;
		}

		WC_Newsman_Remarketing_JS::get_instance()->checkout_process(WC()->cart->get_cart());
	}

	/**
	 *
	 * @param  string $return_url WooCommerce Return URL
	 *
	 * @return string URL
	 */
	public function utm_nooverride($return_url)
	{
		// We don't know if the URL already has the parameter so we should remove it just in case
		$return_url = remove_query_arg('utm_nooverride', $return_url);

		// Now add the utm_nooverride query arg to the URL
		$return_url = add_query_arg('utm_nooverride', '1', $return_url);

		return $return_url;
	}
}
