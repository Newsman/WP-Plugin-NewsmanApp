<?php

/*
Plugin Name: NewsmanApp for Wordpress
Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
Description: NewsmanApp for Wordpress (sign up widget, subscribers sync, create and send newsletters from blog posts)
Version: 2.7.3
Author: Newsman
Author URI: https://www.newsman.com
*/

    if (!defined('ABSPATH')) {
        exit;
    }

    require_once 'vendor/Newsman/Client.php';   

    class WP_Newsman
    {      
        /*
        * @var Newsman_Client
        * Instance of a Newsman_Client
        */
        public $client;

        /*
        * @var array
        * First element in array is the type of message (success or error)
        * Second element in array is the message string
        */
        public $message;

        /*
        * @var integer
        * The user id of the Newsman_Client
        */
        public $userid;

        /*
        * @var string
        * The api key of the Newsman_Client
        */
        public $apikey;

        /*
        * @var boolean
        * If credentials (combination of user id and api key) are correct true, else false.
        */
        public $valid_credentials = true;

        /*
        * @var array
        * Array containing the names of the html files found in the templates directory (as defined by the templates_dir constant)
        */
        public $templates = array();    
 
        public $batchSize = 9000;

        public $wpSync, $mailpoetSync, $sendpressSync, $wooCommerce = false;

        public static $endpoint = "https://retargeting.newsmanapp.com/js/retargeting/track.js";
        public static $endpointHost = "https://retargeting.newsmanapp.com";

        public function __construct()
        {  
            $this->constructClient();
            $this->initHooks();    
        }

        public function isOauth($insideOauth = false){

            if($insideOauth)
            {
                if(!empty(get_option('newsman_userid')))
                    wp_redirect("https://" . $_SERVER["HTTP_HOST"] . "/wp-admin/admin.php?page=NewsmanSettings");
                
                return;
            }

            if(empty(get_option('newsman_userid')))
                wp_redirect("https://" . $_SERVER["HTTP_HOST"] . "/wp-admin/admin.php?page=NewsmanOauth");
        }

        /*
        * Set's up the Newsman_Client instance
        * @param integer | string $userid The user id for Newsman (default's to null)
        * @param string $apikey The api key for Newsman (default's to null)
        * @return nothing
        */
        public function constructClient($userid = null, $apikey = null)
        {
            $this->userid = (!is_null($userid)) ? $userid : get_option('newsman_userid');
            $this->apikey = (!is_null($apikey)) ? $apikey : get_option('newsman_apikey');

            try {
                $this->client = new Newsman_Client($this->userid, $this->apikey);
                $this->client->setCallType("rest");
            } catch (Exception $e) {
                $this->valid_credentials = false;
            }

        }

        /*
        * Tests the Newsman Client Instance for valid credentials
        * @return boolean
        */
        public function showOnFront()
        {
            try {
                $test = $this->client->list->all();
                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        public function _json($obj)
        {
            header('Content-Type: application/json');
            echo json_encode($obj, JSON_PRETTY_PRINT);
            exit;
        }

        public function newsmanFetchData()
        {    
            $newsman = (empty($_GET["newsman"])) ? "" : $_GET["newsman"];
            $apikey = (empty($_GET["nzmhash"])) ? "" : $_GET["nzmhash"];

            $authorizationHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
            if (strpos($authorizationHeader, 'Bearer') !== false) {
                $apikey = trim(str_replace('Bearer', '', $authorizationHeader));
            }

    	    $start = (!empty($_GET["start"]) && $_GET["start"] > 0) ? $_GET["start"] : 1;
            $limit = (empty($_GET["limit"])) ? 1000 : $_GET["limit"];
            $order_id = (empty($_GET["order_id"])) ? "" : $_GET["order_id"];
            $product_id = (empty($_GET["product_id"])) ? "" : $_GET["product_id"];
            $method = (empty($_GET["method"])) ? "" : $_GET["method"];
            
            $cronLast = (empty($_GET["cronlast"])) ? "" : $_GET["cronlast"];
            if(!empty($cronLast))
	        $cronLast = ($cronLast == "true") ? true : false;

            if (!empty($newsman) && !empty($apikey)) {

                $allowAPI = get_option('newsman_api');  

                if ($allowAPI != "on") {
                    $this->_json(array("status" => 403, "message" => "API setting is not enabled in plugin"));
                    return;
                }

                $currApiKey = get_option('newsman_apikey');

                if ($apikey != $currApiKey) {
                    $this->_json(array("status" => 403));
                    return;
                }

                if (!class_exists('WooCommerce')) {
                    wp_send_json(array("error" => "WooCommerce is not installed"));
                }

                switch ($_GET["newsman"]) {
                    case "orders.json":

                        $orders = null;

                        $args = array(
                            'limit' => $limit,
                            'offset' => $start
                        );

                        if(!empty($order_id))
                        {
                            $orders = wc_get_order($order_id);
                            $orders = array(
                                $orders
                            );                

                            if(empty($orders[0]))
                            {
                                $this->_json(array());
                                return;
                            }
                        }
                        else{
                            if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                                $query = new WC_Order_Query(array(
                                    'limit' => $limit,
                                    'offset' => $start
                                ));
                                $orders = $query->get_orders();
                            } else {
                                $orders = wc_get_orders($args);
                            }
                        }                        

                        $ordersObj = array();

                        foreach ($orders as $item) {
                            $user = get_userdata($item->get_user_id());

                            $products = $item->get_items();
                            $productsJson = array();

                            $itemData = $item->get_data();

                            foreach ($products as $prod) {
                                $_prod = wc_get_product($prod['product_id']);

                                $image_id  = $_prod->get_image_id();
                                $image_url = wp_get_attachment_image_url( $image_id, 'full' );
                                $url = get_permalink( $_prod->get_id() );   

                                $_price = 0;
                                $_price_old = 0;
    
                                if(empty($_prod->get_sale_price()))
                                {
                                    $_price = $_prod->get_price();
                                }
                                else{
                                    $_price = $_prod->get_sale_price();
                                    $_price_old = $_prod->get_regular_price();
                                }

                                $productsJson[] = array(
                                    "id" => (string)$prod['product_id'],
                                    "name" => $prod['name'],
                                    "quantity" => (int)$prod['quantity'],
                                    "price" => (float)$_price,
                                    "price_old" => (float)$_price_old,
                                    "image_url" => $image_url,
                                    "url" => $url
                                );
                            }                         

                            $date = $item->get_date_created();
                            $date = $date->getTimestamp();                       

                            $ordersObj[] = array(
                                "order_no" => $item->get_order_number(),
                                "date" => $date,
                                "status" => $item->get_status(),
                                "lastname" => (empty($user) ? $item->get_billing_last_name() : $user->last_name),
                                "firstname" => (empty($user) ? $item->get_billing_first_name() : $user->first_name),
                                "email" => (empty($user) ? $item->get_billing_email() : $user->first_name),
                                "phone" => $itemData['billing']['phone'],
                                "state" => $itemData['billing']['state'],
                                "city" => $itemData['billing']['city'],
                                "address" => $itemData['billing']['address_1'],
                                "discount" => (empty($itemData['billing']['discount_total'])) ? 0 : (float)$itemData['billing']['discount_total'],
                                "discount_code" => "",
                                "shipping" => (float)$itemData["shipping_total"],
                                "fees" => 0,
                                "rebates" => 0,
                                "total" => (float)wc_format_decimal($item->get_total(), 2),
                                "products" => $productsJson
                            );
                        }

                        $this->_json($ordersObj);
                        exit;
                        return;

                        break;

                    case "products.json":                        

                        $products = null;

                        $args = array(
                            'stock_status' => 'instock',
                            'limit' => $limit,
                            'offset' => $start - 1
                        );  

                        if(!empty($product_id))
                        {
                            $products = wc_get_product($product_id);
                            $products = array(
                                $products
                            );                

                            if(empty($products[0]))
                            {
                                $this->_json(array());
                                return;
                            }
                        }
                        else{
                            $products = wc_get_products($args);
                        }                        

                        $productsJson = array();

                        foreach ($products as $prod) {

                            $image_id  = $prod->get_image_id();
                            $image_url = wp_get_attachment_image_url( $image_id, 'full' );
                            $url = get_permalink( $prod->get_id() );                        

                            $_price = 0;
                            $_price_old = 0;

                            if(empty($prod->get_sale_price()))
                            {
                                $_price = $prod->get_price();
                            }
                            else{
                                $_price = $prod->get_sale_price();
                                $_price_old = $prod->get_regular_price();
                            }

                            $cat_ids = $prod->get_category_ids();
                            $category = "";
                        
                            foreach ( (array) $cat_ids as $cat_id) {
                                $cat_term = get_term_by('id', (int)$cat_id, 'product_cat');
                                if($cat_term){
                                    $category = $cat_term->name; 
                                    break;
                                }
                            }
                            
                            $productsJson[] = array(
                                "id" => (string)$prod->get_id(),
                                "category" => $category,
                                "name" => $prod->get_name(),
                                "stock_quantity" => (empty($prod->get_stock_quantity())) ? null : (float)$prod->get_stock_quantity(),
                                "price" => (float)$_price,
                                "price_old" => (float)$_price_old,
                                "image_url" => $image_url,
                                "url" => $url
                            );
                        }

                        $this->_json($productsJson);
                        exit;
                        return;

                        break;

                    case "customers.json":

                        $args = array("role" => "customer", "offset" => $start, "number" => $limit);
                        $wp_cust = get_users($args);
                        $custs = array();

                        foreach ($wp_cust as $users => $user) {
                            $data = get_user_meta($user->data->ID);

                            $custs[] = array(
                                "email" => $user->data->user_email,
                                "firstname" => $data["first_name"][0],
                                "lastname" => $data["last_name"][0]
                            );
                        }

                        $this->_json($custs);
                        exit;
                        return;

                        break;

                    case "subscribers.json":

                        $args = array("role" => "subscriber", "offset" => $start, "number" => $limit);
                        $wp_subscribers = get_users($args);
                        $subs = array();

                        foreach ($wp_subscribers as $users => $user) {
                            $data = get_user_meta($user->data->ID);

                            $subs[] = array(
                                "email" => $user->data->user_email,
                                "firstname" => $data["first_name"][0],
                                "lastname" => $data["last_name"][0]
                            );
                        }

                        $this->_json($subs);
                        exit;
                        return;

                        break;

                        case "version.json":                        
                            
                            $version = array(
                                "version" => "Wordpress " . get_bloginfo('version')
                            );                                                                                                       

                            echo json_encode($version, JSON_PRETTY_PRINT);
                            exit;
                            return;
    
                        break;

                        case "cron.json":

                            $list = get_option("newsman_list");
                            $segments = get_option("newsman_segments");                  

                            if(empty($list))
                                $this->_json(array("status" => "List setup incomplete"));

                            switch($method)
                            {
                                case "woocommerce":

                                    if (class_exists( 'WooCommerce' )) {         
                                        $this->importWoocommerceSubscribers($list, $segments, $start, $limit, $cronLast);                            

                                        $json = array(
                                            "status" => "success"
                                        );
                    
                                        $this->_json($json);
                                     }
                                     else{
                                         $this->_json(array("status" => "woocommerce is not installed"));
                                     }

                                    exit;
                                    return;
                                     
                                break;

                                case "wordpress":
                           
                                   $this->importWPSubscribers($list, $segments, $start, $limit, $cronLast);

                                   $json = array(
                                    "status" => "success"
                                );
            
                                $this->_json($json);

                                exit;
                                return;

                                break;
                            }     
                            
                            $this->_json(array("status" => "method does not exist"));
        
                            return;
        
                        break;
                        
                        case "coupons.json":

                        try{

                            if ( !class_exists( 'WC_Coupon' ) )
                                include_once( WC()->plugin_path() . '/includes/class-wc-coupon.php' );
                            
                            $discountType = !isset($_GET["type"]) ? -1 : (int)$_GET["type"];
                            $value = !isset($_GET["value"]) ? -1 : (int)$_GET["value"];
                            $batch_size = !isset($_GET["batch_size"]) ? 1 : (int)$_GET["batch_size"];
                            $prefix = !isset($_GET["prefix"]) ? "" : $_GET["prefix"];
                            $expire_date = isset($_GET['expire_date']) ? $_GET['expire_date'] : null;
                            $min_amount = !isset($_GET["min_amount"]) ? -1 : (float)$_GET["min_amount"];
                            $currency = isset($_GET['currency']) ? $_GET['currency'] : "";

                            if($discountType == -1)
                            {
                                $this->_json(
                                    array(
                                        "status" => 0,
                                        "msg" => "Missing type param"
                                    )
                                );
                            }
                            elseif($value == -1)
                            {
                                $this->_json(
                                    array(
                                        "status" => 0,
                                        "msg" => "Missing value param"
                                    )
                                );
                            }

                            $couponsList = array();

                            for($int = 0; $int < $batch_size; $int++)
                            {
                                $coupon = new WC_Coupon();

                                switch($discountType)
                                {
                                    case 1:
                                        $coupon->set_discount_type('percent');
                                        break;
                                    case 0:
                                        $coupon->set_discount_type('fixed_cart');
                                        break;
                                }

                                $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                                $coupon_code = '';
                            
                                do {
                                    $coupon_code = '';
                                    for ($i = 0; $i < 8; $i++) {
                                        $coupon_code .= $characters[rand(0, strlen($characters) - 1)];
                                    }
                                    $full_coupon_code = $prefix . $coupon_code;
                                    $existing_coupon_id = wc_get_coupon_id_by_code($full_coupon_code);
                                } while ($existing_coupon_id != 0);
                        
                                $coupon->set_code($full_coupon_code); 
                                $coupon->set_description( 'NewsMAN generated coupon code' );
                                $coupon->set_amount($value); 

                                if($expire_date != null)
                                { 
                                    $formatted_expire_date = date('Y-m-d H:i:s', strtotime($expire_date));
                                    $coupon->set_date_expires(strtotime($formatted_expire_date));
                                }

                                if($min_amount != -1)
                                    $coupon->set_minimum_amount($min_amount);

                                //set default
                                /*if(empty($currency))
                                    $currency = "RON";

                                $coupon->set_discount_currency($currency);*/
                                
                                //usage limit denied for now
                                //$coupon->set_usage_limit( 1 );
                        
                                $coupon->save();

                                array_push($couponsList, $coupon->get_code());
                            }

                            $this->_json(
                                array(
                                    "status" => 1,
                                    "codes" => $couponsList
                                )
                            );
                        }
                        catch(Exception $exception){
                            $this->_json(
                                array(
                                    "status" => 0,
                                    "msg" => $exception->getMessage()
                                )
                            );
                        }

                        break;
                }
            }
        }

        /*
        * Imports subscribers from Wordpress Into Newsman and creates a message
        * @param integer | string 	The id of the list into which to import the subscribers
        */
        public function importWPSubscribers($list, $segments, $start = 1, $limit = 1000, $cronLast = false)
        {
            //get wordpress subscribers as array

            if($cronLast)
            {
                $args = array("role" => "subscriber");
                $wp_subscribers = get_users($args); 

                $data = count($wp_subscribers);

                $start = $data - $limit;

                if($start < 1)
                {
                    $start = 1;
                }             
            }

            $args = array("role" => "subscriber", "offset" => $start, "number" => $limit);
            $wp_subscribers = get_users($args);    

            //sync with newsman
            try {
                $_segments = (!empty($segments)) ? array($segments) : "";
                $customers_to_import = array();

                foreach ($wp_subscribers as $users => $user) {
                    $customers_to_import[] = array(
                        "email" => $user->data->user_email,
                        "firstname" => $user->data->display_name,
                        "lastname" => "",
                        "tel" => ""
                    );
                    if ((count($customers_to_import) % $this->batchSize) == 0) {
                        $this->_importData($customers_to_import, $list, $this->client, "newsman plugin wordpress subscribers CRON", $_segments);
                    }
                }
                if (count($customers_to_import) > 0) {
                    $this->_importData($customers_to_import, $list, $this->client, "newsman plugin wordpress subscribers CRON", $_segments);
                }
                              
                unset($customers_to_import);

                $this->wpSync = true;
                $this->setMessageBackend("updated", 'Subscribers synced with Newsman.');

            } catch (Exception $e) {
                $this->setMessageBackend("error", "Failure to sync subscribers with Newsman." . $e->getMessage());
            }

            if (empty($this->message)) {
                $this->setMessageBackend("updated", 'Options saved.');
            }
        }

        public function importWoocommerceSubscribers($list, $segments, $start = 1, $limit = 1000, $cronLast = false)
        {
            $wp_subscribers = array();

            if($cronLast)
            {
                $woocommerceFilter = array(
                    'status' => 'completed',
                );    

                $allOrders = wc_get_orders($woocommerceFilter);
                $data = count($allOrders);

                $start = $data - $limit;

                if($start < 1)
                {
                    $start = 1;
                }             
            }

            $woocommerceFilter = array(
                'status' => 'completed',
                'limit' => $limit,
                'offset' => $start
            );    

            $allOrders = wc_get_orders($woocommerceFilter);                          

            try {
                $_segments = (!empty($segments)) ? array($segments) : array();         

                $customers_to_import = array();

                foreach ($allOrders as $user) {     
                    
                    $data = json_decode(json_encode($user->data));
                    
                    if (empty($data)) 
                        continue;

                    if(!array_key_exists("billing", $user->data))
                        continue;
                          
                    $customers_to_import[] = array(
                        "email" => $user->data["billing"]["email"],
                        "firstname" => ($user->data["billing"]["first_name"] != null) ? $user->data["billing"]["first_name"] : "",
                        "lastname" => ($user->data["billing"]["first_name"] != null) ? $user->data["billing"]["last_name"] : "",
                        "tel" => ($user->data["billing"]["phone"] != null) ? $user->data["billing"]["phone"] : "",
                    );                             

                    if ((count($customers_to_import) % $this->batchSize) == 0) {
                        $this->_importData($customers_to_import, $list, $this->client, "newsman plugin wordpress woocommerce CRON", $_segments);
                    }
                }
                if (count($customers_to_import) > 0) {
                    $this->_importData($customers_to_import, $list, $this->client, "newsman plugin wordpress woocommerce CRON", $_segments);
                }          

                unset($customers_to_import);

                $this->wooCommerce = true;
                $this->setMessageBackend("updated ", 'WooCommerce customers synced with Newsman.');

            } catch (Exception $e) {
                $this->setMessageBackend("error ", "Failed to sync Woocommerce customers with Newsman.");
            }

            if (empty($this->message)) {
                $this->setMessageBackend("updated", 'Options saved.');
            }
        }        

        /*
        * Initializes wordpress hooks
        */   

        public function pending($order_id){
            $this->saveOrderNewsman($order_id, 'pending');
        }

        public function failed($order_id){
            $this->saveOrderNewsman($order_id, 'failed');
        }

        //on-hold
        public function hold($order_id){
            $this->saveOrderNewsman($order_id, 'on-hold');
        }

        public function processing($order_id){
            $this->saveOrderNewsman($order_id, 'processing');
        }

        public function completed($order_id){
            $this->saveOrderNewsman($order_id, 'completed');
        }

        public function refunded($order_id){
            $this->saveOrderNewsman($order_id, 'refunded');
        }

        public function cancelled($order_id){
            $this->saveOrderNewsman($order_id, 'cancelled');
        } 

        public function saveOrderNewsman($order_id, $status){

            $newsman_usesms = get_option('newsman_usesms');
            $newsman_smslist = get_option('newsman_smslist');
            $newsman_smstest = get_option('newsman_smstest');
            $newsman_smstestnr = get_option('newsman_smstestnr');

            $sendSms = false;
            $newsman_smstext = "";
            
            $newsman_smspending = get_option('newsman_smspendingactivate');
            if($status == "pending" && $newsman_smspending == "on")
            {
                $sendSms = true;
                $newsman_smstext = get_option("newsman_smspendingtext");
            }
            $newsman_smsfailed = get_option('newsman_smsfailedactivate');
            if($status == "failed" && $newsman_smsfailed == "on")
            {
                $sendSms = true;
                $newsman_smstext = get_option("newsman_smsfailedtext");
            }
            $newsman_smsonhold = get_option('newsman_smsonholdactivate');
            if($status == "on-hold" && $newsman_smsonhold == "on")
            {
                $sendSms = true;
                $newsman_smstext = get_option("newsman_smsonholdtext");
            }
            $newsman_smsprocessing = get_option('newsman_smsprocessingactivate');
            if($status == "processing" && $newsman_smsprocessing == "on")
            {
                $sendSms = true;
                $newsman_smstext = get_option("newsman_smsprocessingtext");
            }
            $newsman_smscompleted = get_option('newsman_smscompletedactivate');
            if($status == "completed" && $newsman_smscompleted == "on")
            {
                $sendSms = true;
                $newsman_smstext = get_option("newsman_smscompletedtext");
            }
            $newsman_smsrefunded = get_option('newsman_smsrefundedactivate');
            if($status == "refunded" && $newsman_smsrefunded == "on")
            {
                $sendSms = true;
                $newsman_smstext = get_option("newsman_smsrefundedtext");
            }
            $newsman_smscancelled = get_option('newsman_smscancelledactivate');
            if($status == "cancelled" && $newsman_smscancelled == "on")
            {
                $sendSms = true;
                $newsman_smstext = get_option("newsman_smscancelledtext");
            }

            if($sendSms)
            {
                try{
                    if(!empty($newsman_usesms) && $newsman_usesms == "on" && !empty($newsman_smslist))
                    {                                                                                        
                        $order = wc_get_order($order_id);  
                        $itemData = $order->get_data(); 

                        $date = $order->get_date_created()->date("F j, Y");         
                                     
                        $newsman_smstext = str_replace("{{billing_first_name}}", $itemData["billing"]["first_name"], $newsman_smstext);
                        $newsman_smstext = str_replace("{{billing_last_name}}", $itemData["billing"]["last_name"], $newsman_smstext);
                        $newsman_smstext = str_replace("{{shipping_first_name}}", $itemData["shipping"]["first_name"], $newsman_smstext);
                        $newsman_smstext = str_replace("{{shipping_last_name}}", $itemData["shipping"]["last_name"], $newsman_smstext);
                        $newsman_smstext = str_replace("{{email}}", $itemData["billing"]["email"], $newsman_smstext);                    
                        $newsman_smstext = str_replace("{{order_number}}", $itemData["id"], $newsman_smstext);           
                        $newsman_smstext = str_replace("{{order_date}}", $date, $newsman_smstext);       
                        $newsman_smstext = str_replace("{{order_total}}", $itemData["total"], $newsman_smstext);       
                        $phone = '4' . $itemData["billing"]["phone"];      
                        
                        if($newsman_smstest)
                            $phone = '4' . $newsman_smstestnr;         

                        $this->client->sms->sendone($newsman_smslist, $newsman_smstext, $phone);                    
                    }   
                }
                catch(Exception $e)
                {
                    error_log($e->getMessage());
                }
            }                      

            $list = get_option("newsman_remarketingid");
            $list = explode("-", $list);
            $list = $list[1];        

            $url = "https://ssl.newsman.app/api/1.2/rest/" . $this->userid . "/" . $this->apikey . "/remarketing.setPurchaseStatus.json?list_id=" . $list . "&order_id=" . $order_id . "&status=" . $status;        

            $response = wp_remote_get(
                esc_url_raw($url),
                array(
             
                )
            );           
            
        }

        public function newsmanCheckout(){
            
            $checkout = get_option('newsman_checkoutnewsletter');
            
            if(!empty($checkout) && $checkout == "on")
            {
                $msg = get_option('newsman_checkoutnewslettermessage');                
                $default = get_option('newsman_checkoutnewsletterdefault');     
                $checked = '';
                
                if(!empty($default) && $default == "on")
                {
                    $default = 1;
                    $checked = 'checked';
                }
                else{
                    $default = 0;
                }                      

                woocommerce_form_field( 'newsmanCheckoutNewsletter', array(
                    'type'          => 'checkbox',
                    'class'         => array('form-row newsmanCheckoutNewsletter'),
                    'label_class'   => array('woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'),
                    'input_class'   => array('woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'),
                    'required'      => false,
                    'label'         => $msg,
                    'default'       => $default,
                    'checked'       => $checked
                    ));
            }

        }

        public function newsmanCheckoutAction($order_id){
                   
            if(!empty($_POST["newsmanCheckoutNewsletter"]) && $_POST["newsmanCheckoutNewsletter"] == 1)
            {

                $checkoutNewsletter = get_option('newsman_checkoutnewsletter');
                $checkoutSMS = get_option('newsman_checkoutsms');
                $checkoutNewsletterType = get_option('newsman_checkoutnewslettertype');
                $list = get_option('newsman_list');
                $smslist = get_option('newsman_smslist');

                $order = wc_get_order($order_id);            
                $order_data = $order->get_data();    

                $props = array();

                try{
                    $metadata = $order->get_meta_data();                                       

                    foreach($metadata as $_metadata)
                    {
                        if($_metadata->key == "_billing_functia" || $_metadata->key == "billing_functia")
                        {
                            $props["functia"] = $_metadata->value;
                        }
                        if($_metadata->key == "_billing_sex" || $_metadata->key == "billing_sex")
                        {
                            $props["sex"] = $_metadata->value;
                        }                         
                    }                     
                }
                catch (Exception $e)
                {
                    //custom fields not found
                }

                $email = $order_data["billing"]["email"];
                $first_name =  $order_data["billing"]["first_name"];
                $last_name = $order_data["billing"]["last_name"];

                $phone = (!empty($order_data["billing"]["phone"])) ? $order_data["billing"]["phone"] : "";                

                $props["phone"] = $phone; 

                $options = array();
        
                $segments = get_option('newsman_segments');
                $rawSegments = $segments;
                if(!empty($segments))
                    $segments = array("segments" => array($segments));       
                    
                $options["segments"] = array($rawSegments);
                
                $form_id = get_option('newsman_form_id');
                if(!empty($form_id))
                    $options["form_id"] = $form_id;

                $checkoutType = get_option('newsman_checkoutnewslettertype'); 

                try{             
                    if($checkoutType == "init")
                    {

                        $ret = $this->client->subscriber->initSubscribe(
                            $list,
                            $email,
                            $first_name,
                            $last_name,
                            $this->getUserIP(),
                            $props, 
                            $options
                        );

                    }
                    elseif($checkoutType == "save"){

                        $subId = $this->client->subscriber->saveSubscribe(
                        $list,
                        $email,
                        $first_name,
                        $last_name,
                        $this->getUserIP(), 
                        $props);
                        
                        if(!empty($segments))
                        {
                            $segments = $segments["segments"][0];
                        }

                        $ret = $this->client->segment->addSubscriber($segments, $subId);

                    }   
                    
                    //SMS sync
                    if(!empty($checkoutSMS) && $checkoutSMS == "on")
                    {

                        if(!empty($phone))
                            $ret = $this->client->sms->saveSubscribe($smslist, $phone, $first_name, $last_name, $this->getUserIP(), $props);

                    }
                
                }
                catch (Exception $e)
                {
                    error_log($e->getMessage());
                    //non relevant error occurred
                }

            }
            
        }

        public function initHooks()
        {          
            add_action('init', array($this, 'newsmanFetchData'));     
            add_action('woocommerce_review_order_before_submit', array($this, 'newsmanCheckout'));                   
            add_action('woocommerce_checkout_order_processed', array($this, 'newsmanCheckoutAction'), 10, 2);
            //order status change hooks        
            add_action( 'woocommerce_order_status_pending', array($this, 'pending'));
            add_action( 'woocommerce_order_status_failed', array($this, 'failed'));
            add_action( 'woocommerce_order_status_on-hold', array($this, 'hold'));
            add_action( 'woocommerce_order_status_processing', array($this, 'processing'));
            add_action( 'woocommerce_order_status_completed', array($this, 'completed'));
            add_action( 'woocommerce_order_status_refunded', array($this, 'refunded'));
            add_action( 'woocommerce_order_status_cancelled', array($this, 'cancelled'));  
            add_action( 'before_woocommerce_init', 'before_woocommerce_hpos' );
            function before_woocommerce_hpos() { 
                if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) { 
                   \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true ); 
               } 
            }   
            #admin menu hook
            add_action('admin_menu', array($this, "adminMenu"));
            #add links to plugins page
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'pluginLinks'));
            #enqueue plugin styles
            //add_action('wp_enqueue_scripts', array($this, 'registerPluginStyles'));
            #enqueue plugin styles in admin
            add_action('admin_enqueue_scripts', array($this, 'registerPluginStyles'));
            #enqueue wordpress ajax library
            add_action('wp_head', array($this, 'addAjaxLibrary'));
            #enqueue plugin scripts
            //add_action('wp_enqueue_scripts', array($this, 'registerPluginScripts'));
            #enqueue plugin scripts in admin
            add_action('admin_enqueue_scripts', array($this, 'registerPluginScripts'));
            #do ajax form subscribe
            add_action('wp_ajax_nopriv_newsman_ajax_subscribe', array($this, "newsmanAjaxSubscribe"));
            add_action('wp_ajax_newsman_ajax_subscribe', array($this, "newsmanAjaxSubscribe")); 
            #check if plugin is active
            add_action('wp_ajax_newsman_ajax_check_plugin', array($this, "newsmanAjaxCheckPlugin"));         
            #widget auto init        
            add_action( 'init', array($this, 'init_widgets') );            
        }      

        function generateWidget($atts){
        
			if(empty($atts) || !is_array($atts) || !array_key_exists("formid", $atts))
			{
				return '';
			}

            $c = substr_count($atts["formid"], '-');

            //backwards compatible
            if($c == 2)
            {
                return '<div id="' . $atts["formid"] . '"></div>';
            }
            else{
                $atts["formid"] = str_replace("nzm-container-", '', $atts["formid"]);

                return '<script async src="https://retargeting.newsmanapp.com/js/embed-form.js" data-nzmform="' . $atts["formid"] . '"></script>';
            }
        }

        function init_widgets() {
                add_shortcode( "newsman_subscribe_widget", array($this,'generateWidget' ));                
        }

        /*
        * Adds a menu item for Newsman on the Admin page
        */
        public function adminMenu()
        {
            add_menu_page("Newsman", "Newsman", "administrator", "Newsman", array($this, "includeAdminPage"), plugin_dir_url(__FILE__) . "src/img/newsman-mini.png");
            add_submenu_page("Newsman", "Sync", "Sync", "administrator", "NewsmanSync", array($this, "includeAdminSyncPage"));
            add_submenu_page("Newsman", "Remarketing", "Remarketing", "administrator", "NewsmanRemarketing", array($this, "includeAdminRemarketingPage"));
            add_submenu_page("Newsman", "SMS", "SMS", "administrator", "NewsmanSMS", array($this, "includeAdminSMSPage"));
            add_submenu_page("Newsman", "Settings", "Settings", "administrator", "NewsmanSettings", array($this, "includeAdminSettingsPage"));
            add_submenu_page("Newsman", "Widget", "Widget", "administrator", "NewsmanWidget", array($this, "includeAdminWidgetPage"));
            add_submenu_page("Newsman", "Oauth", "Oauth", "administrator", "NewsmanOauth", array($this, "includeOauthPage"));
        }

        /*
        * Includes the html for the admin page
        */
        public function includeAdminPage()
        {
            include 'src/backend.php';
        }

        /*
        * Includes the html for the admin settings page
        */
        public function includeAdminSettingsPage()
        {
            include 'src/backend-settings.php';
        }

        /*
        * Includes the html for the admin sync page
        */
        public function includeAdminSyncPage()
        {
            include 'src/backend-sync.php';
        }

        public function includeOauthPage(){
            include 'src/backend-oauth.php';
        }

        /*
        * Includes the html for the admin remarketing page
        */
        public function includeAdminRemarketingPage()
        {
            include 'src/backend-remarketing.php';
        }

        /*
        * Includes the html for the admin SMS page
        */
        public function includeAdminSMSPage()
        {
            include 'src/backend-sms.php';
        }

        /*
        * Includes the html for the admin widget page
        */
        public function includeAdminWidgetPage()
        {
            include 'src/backend-widget.php';
        }

        /*
        * Binds the Newsman menu item to the menu
        */
        public function pluginLinks($links)
        {
            $custom_links = array(
                '<a href="' . admin_url('admin.php?page=NewsmanSettings') . '">Settings</a>'
            );
            return array_merge($links, $custom_links);
        }

        /*
        * Register plugin custom css
        */
        public function registerPluginStyles()
        {
            //wp_register_style('jquery-ui-css', '//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css');
            wp_register_style('newsman_css', plugins_url('newsmanapp/src/css/style.css'));
            //wp_enqueue_style('bootstrap-css');
            //wp_enqueue_style('jquery-ui-css');
            wp_enqueue_style('newsman_css');
        }

        /*
        * Register plugin custom javascript
        */
        public function registerPluginScripts()
        {
            //wp_register_script('jquery-ui', "//code.jquery.com/ui/1.11.4/jquery-ui.js", array('jquery'));
            wp_register_script('newsman_js', plugins_url('newsmanapp/src/js/script.js'), array('jquery'));  
            wp_enqueue_script('newsman_js');
            //wp_enqueue_script('bootstrap-js');
            //wp_enqueue_script('jquery-ui');
        }

        /*
        * Includes ajax library that wordpress uses for processing ajax requests
        */
        public function addAjaxLibrary()
        {
            $html = '<script type="text/javascript">';
            $html .= 'var ajaxurl = "' . admin_url('admin-ajax.php') . '"';
            $html .= '</script>';

            if ( ! class_exists( 'WooCommerce' ) ) {         
                $remarketingid = get_option('newsman_remarketingid');
                if(!empty($remarketingid))
                    $html .= "
                    <script type='text/javascript'>
                    var _nzm = _nzm || []; var _nzm_config = _nzm_config || []; _nzm_tracking_server = '" . self::$endpointHost . "';
                    (function() {var a, methods, i;a = function(f) {return function() {_nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
                    }};methods = ['identify', 'track', 'run'];for(i = 0; i < methods.length; i++) {_nzm[methods[i]] = a(methods[i])};
                    s = document.getElementsByTagName('script')[0];var script_dom = document.createElement('script');script_dom.async = true;
                    script_dom.id = 'nzm-tracker';script_dom.setAttribute('data-site-id', '" . esc_js($remarketingid) . "');
                    script_dom.src = '" . self::$endpoint . "';s.parentNode.insertBefore(script_dom, s);})();
                    </script>
                    ";
            }            

            echo $html;
        }

        /*
        * Precess ajax request for the subscription form
        * Initializes the subscription process for a new user
        */
        public function newsmanAjaxSubscribe()
        {
            if (isset($_POST['email']) && !empty($_POST['email'])) {

                $email = strip_tags(trim($_POST['email']));
                $name = strip_tags(trim($_POST['name']));
                $prename = strip_tags(trim($_POST['prename']));
                $list = get_option('newsman_list');
                try {
                    if ($this->newsmanListEmailExists($email, $list)) {
                        $message = "Email deja inscris la newsletter";
                        $this->sendMessageFront('error', $message);
                        die();
                    }

                    $ret = $this->client->subscriber->initSubscribe(
                        $list, /* The list id */
                        $email, /* Email address of subscriber */
                        $prename, /* Firstname of subscriber, can be null. */
                        $name, /* Lastname of subscriber, can be null. */
                        $this->getUserIP(), /* IP address of subscriber */
                        null, /* Hash array with props (can be later used to build segment criteria) */
                        null
                    );

                    $message = get_option("newsman_widget_confirm");

                    $this->sendMessageFront('success', $message);

                } catch (Exception $e) {
                    $message = get_option("newsman_widget_infirm");
                    $this->sendMessageFront('error', $message);
                }

            }
            die();
        }

        public function newsmanListEmailExists($email, $list)
        {
            $bool = false;

            try {
                $ret = $this->client->subscriber->getByEmail(
                    $list, /* The list id */
                    $email /* The email address */
                );

                if ($ret["status"] == "subscribed") {
                    $bool = true;
                }

                return $bool;
            } catch (Exception $e) {
                return $bool;
            }
        }      

        /*
        * Creates and return a message for frontend (because of the echo statement)
        * @var string $status 		The status of the message (the css class of the message)
        * @var string $string 		The actual message
        * @return echoes the array as json object
        */
        public function sendMessageFront($status, $string)
        {
            $this->message = json_encode(array(
                'status' => $status,
                'message' => $string
            ));

            echo $this->message;
        }

        /*
        * Creates and return a message for backend
        * @var string $status 		The status of the message (the css class of the message)
        * @var string $string 		The actual message
        * @return the array
        */
        public function setMessageBackend($status, $string)
        {
            $this->message = array(
                'status' => $status,
                'message' => $string
            );
        }

        /*
        * Returns the current message for the backend
        * @return array 	The message array
        */
        public function getBackendMessage()
        {
            return $this->message;
        }

        /*
        * Get the subscriber ip address. (Necessary for Newsman subscription)
        * @return string 	The ip address
        */
        public function getUserIP()
        {
            $cl = @$_SERVER['HTTP_CLIENT_IP'];
            $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
            $remote = $_SERVER['REMOTE_ADDR'];

            if (filter_var($cl, FILTER_VALIDATE_IP)) {
                $ip = $cl;
            } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
                $ip = $forward;
            } else {
                $ip = $remote;
            }
            return $ip;
        }

        /*
        * Includes the html for the subscription form
        */
        public function newsmanDisplayForm()
        {
            include 'src/frontend.php';
        }

        public function newsmanAjaxCheckPlugin()
        {
            $activePlugins = get_option('active_plugins');

            $plugin = $_POST["plugin"];

            if (in_array($plugin, $activePlugins)) {
                echo json_encode(array("status" => 1));
                exit();
            }
            echo json_encode(array("status" => 0));
            exit();
        }                  

        function safeForCsv($str)
        {
            return '"' . str_replace('"', '""', $str) . '"';
        }

        function _importData(&$data, $list, $client, $source, $segments = null)
        {
            $csv = '"email","firstname","lastname","tel","source"' . PHP_EOL;
            foreach ($data as $_dat) {
                $csv .= sprintf(
                    "%s,%s,%s,%s",
                    $this->safeForCsv($_dat["email"]),
                    $this->safeForCsv($_dat["firstname"]),
                    $this->safeForCsv($_dat["lastname"]),
                    $this->safeForCsv($_dat["tel"]),
                    $this->safeForCsv($source)
                );
                $csv .= PHP_EOL;
            }
            $ret = null;
            try {
                if (is_array($segments) && count($segments) > 0) {
                    $ret = $client->import->csv($list, $segments, $csv);
                } else {
                    $ret = $client->import->csv($list, array(), $csv);
                }
                if ($ret == "") {
                    throw new Exception("Import failed");
                }
            } catch (Exception $e) {
                throw new Exception("Import failed");
            }
            $data = array();
        }


    }

    $wp_newsman = new WP_Newsman();

    //include the widget
    //include 'newsman_widget.php';

    ?>
