<?php

/*
 Plugin Name: NewsmanApp for Wordpress
 Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 Description: NewsmanApp for Wordpress (sign up widget, subscribers sync, create and send newsletters from blog posts)
 Version: 1.2
 Author: newsmanapp
 Author URI: https://www.newsmanapp.com
 */

if (!defined('ABSPATH'))
{
	exit;
}

require_once 'vendor/Newsman/Client.php';

$upload_dir = wp_upload_dir();

define('templates_dir', __DIR__ . "/src/email_templates/");
define('templates_default_dir', __DIR__ . "/src/email_templates/");
define('template_img_dir', "/wp-content/plugins/newsmanapp/src/email_templates/");

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

	/*
	 * @var TemplateFactory object
	 * object that renders a php template
	 */
	public $engine;

	/*
	 * Initializes the object
	 * 1. Creates the Newsman_Client instance.
	 * 2. Initializes wordpress hooks.
	 * 3. Loads templates from directory 
	 */

	/*
	 * integer maximum value 9999, by default 5000
	 */
	public $batchSize = 5000;

	public $wpSync, $mailpoetSync, $sendpressSync, $wooCommerce = false;

	public function __construct()
	{
		$this->constructClient();
		$this->initHooks();
		$this->findTemplates();

		require_once 'src/templating_engine/TemplateFactory.php';
		$this->engine = new TemplateFactory();
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

		try
		{
			$this->client = new Newsman_Client($this->userid, $this->apikey);
			$this->client->setCallType("rest");
		} catch (Exception $e)
		{
			$this->valid_credentials = false;
		}

	}

	/*
	 * Tests the Newsman Client Instance for valid credentials
	 * @return boolean
	 */
	public function showOnFront()
	{
		try
		{
			$test = $this->client->list->all();
			return true;
		} catch (Exception $e)
		{
			return false;
		}
	}

	/*
	 * Initializes wordpress hooks
	 */
	public function initHooks()
	{
		#admin menu hook
		add_action('admin_menu', array($this, "adminMenu"));
		#add links to plugins page
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'pluginLinks'));
		#enqueue plugin styles
		add_action('wp_enqueue_scripts', array($this, 'registerPluginStyles'));
		#enqueue plugin styles in admin
		add_action('admin_enqueue_scripts', array($this, 'registerPluginStyles'));
		#enqueue wordpress ajax library
		add_action('wp_head', array($this, 'addAjaxLibrary'));
		#enqueue plugin scripts
		add_action('wp_enqueue_scripts', array($this, 'registerPluginScripts'));
		#enqueue plugin scripts in admin
		add_action('admin_enqueue_scripts', array($this, 'registerPluginScripts'));
		#do ajax form subscribe
		add_action('wp_ajax_nopriv_newsman_ajax_subscribe', array($this, "newsmanAjaxSubscribe"));
		add_action('wp_ajax_newsman_ajax_subscribe', array($this, "newsmanAjaxSubscribe"));
		#preview template
		add_action('wp_ajax_newsman_ajax_preview_template', array($this, "newsmanAjaxTemplatePreview"));
		#check if plugin is active
		add_action('wp_ajax_newsman_ajax_check_plugin', array($this, "newsmanAjaxCheckPlugin"));
		#send newsletter
		add_action('wp_ajax_newsman_ajax_send_newsletter', array($this, "newsmanAjaxSendNewsletter"));
		#load template source code for editing
		add_action('wp_ajax_newsman_ajax_template_editor_selection', array($this, "newsmanAjaxTemplateEditorSelection"));
		#save changes made to the source code of the template
		add_action('wp_ajax_newsman_ajax_template_editor_save', array($this, "newsmanAjaxTemplateEditorSave"));
		#subscribe from front
	}

	/*
	 * Adds a menu item for Newsman on the Admin page 
	 */
	public function adminMenu()
	{
		add_menu_page("Newsman", "Newsman", "administrator", "Newsman", array($this, "includeAdminPage"), plugin_dir_url(__FILE__) . "src/img/newsman-mini.png");
		add_submenu_page("Newsman", "Settings", "Settings", "administrator", "NewsmanSettings", array($this, "includeAdminSettingsPage"));
		add_submenu_page("Newsman", "Sync", "Sync", "administrator", "NewsmanSync", array($this, "includeAdminSyncPage"));
		add_submenu_page("Newsman", "Widget", "Widget", "administrator", "NewsmanWidget", array($this, "includeAdminWidgetPage"));
		add_submenu_page("Newsman", "Newsletter", "Newsletter", "administrator", "NewsmanNewsletter", array($this, "includeAdminNewsletterPage"));
		add_submenu_page("Newsman", "Templates", "Templates", "administrator", "NewsmanNewsletterTemplates", array($this, "includeNewsletterTemplatesPage"));
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

	/*
	 * Includes the html for the admin widget page
	 */
	public function includeAdminWidgetPage()
	{
		include 'src/backend-widget.php';
	}

	/*
	 * Includes the html for the admin newsletter page
	 */
	public function includeAdminNewsletterPage()
	{
		include 'src/backend-newsletter.php';
	}

	/*
	 *Includes the html for the templates page 
	 */
	public function includeNewsletterTemplatesPage()
	{
		include 'src/backend-templates.php';
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
		wp_register_style('jquery-ui-css', '//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css');
		wp_register_style('newsman_css', plugins_url('newsmanapp/src/css/style.css'));
		wp_enqueue_style('bootstrap-css');
		wp_enqueue_style('jquery-ui-css');
		wp_enqueue_style('newsman_css');
	}

	/*
	 * Register plugin custom javascript
	 */
	public function registerPluginScripts()
	{
		wp_register_script('jquery-ui', "//code.jquery.com/ui/1.11.4/jquery-ui.js", array('jquery'));
		wp_register_script('newsman_js', plugins_url('newsmanapp/src/js/script.js'), array('jquery'));
		wp_register_script('bootstrap-js', "https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js", array('jquery'));
		wp_enqueue_script('newsman_js');
		wp_enqueue_script('bootstrap-js');
		wp_enqueue_script('jquery-ui');
	}

	/*
	 * Includes ajax library that wordpress uses for processing ajax requests
	 */
	public function addAjaxLibrary()
	{
		$html = '<script type="text/javascript">';
		$html .= 'var ajaxurl = "' . admin_url('admin-ajax.php') . '"';
		$html .= '</script>';

		echo $html;
	}

	/*
	 * Precess ajax request for the subscription form
	 * Initializes the subscription process for a new user
	 */
	public function newsmanAjaxSubscribe()
	{
		if (isset($_POST['email']) && !empty($_POST['email']))
		{

			$email = strip_tags(trim($_POST['email']));
			$list = get_option('newsman_list');
			try
			{
				if ($this->newsmanListEmailExists($email, $list))
				{
					$message = "Email deja inscris la newsletter";
					$this->sendMessageFront('error', $message);
					die();
				}

				$ret = $this->client->subscriber->initSubscribe(
					$list, /* The list id */
					$email, /* Email address of subscriber */
					null, /* Firstname of subscriber, can be null. */
					null, /* Lastname of subscriber, can be null. */
					$this->getUserIP(), /* IP address of subscriber */
					null, /* Hash array with props (can be later used to build segment criteria) */
					null
				);

				$message = get_option("newsman_widget_confirm");

				$this->sendMessageFront('success', $message);

			} catch (Exception $e)
			{
				$message = get_option("newsman_widget_infirm");
				$this->sendMessageFront('error', $message);
			}

		}
		die();
	}

	public function newsmanListEmailExists($email, $list)
	{
		$bool = false;

		try
		{
			$ret = $this->client->subscriber->getByEmail(
				$list, /* The list id */
				$email /* The email address */
			);

			if ($ret["status"] == "subscribed")
			{
				$bool = true;
			}

			return $bool;
		} catch (Exception $e)
		{
			return $bool;
		}
	}

	/*
	 * Process ajax request for previewing templates 
	 */
	public function newsmanAjaxTemplatePreview()
	{
		if (isset($_POST['template'], $_POST['posts']) && !empty($_POST['template']) && !empty($_POST['posts']))
		{

			$template = $_POST['template'];
			$posts = $_POST['posts'];

			if (is_numeric($posts))
			{
				$html = $this->constructTemplateEditorPreview($posts, $template);
			} else
			{
				$html = $this->constructTemplate($posts, $template);
			}
			echo json_encode(array('html' => $html));
		}
		die();
	}

	/*
	 * Process ajax request for template selection for editing
	 */
	public function newsmanAjaxTemplateEditorSelection()
	{
		if (isset($_POST['template']) && !empty($_POST['template']))
		{
			$template = $_POST['template'];
			$source = $this->getTemplateSource($template);
			echo json_encode(array('source' => $source));
		}
		die();
	}

	/*
	 * Process ajax request for template editor saving
	 */
	public function newsmanAjaxTemplateEditorSave()
	{
		if (isset($_POST['template'], $_POST['source']) && !empty($_POST['template']))
		{

			$template = $_POST['template'];
			$source = $_POST['source'];
			$was_saved = $this->saveTemplateSource($template, $source);

			if ($was_saved)
			{
				$response = array(
					"error" => false,
					'message' => '&#10003; Changes saved!'
				);
			} else
			{
				$response = array(
					"error" => true,
					'message' => '&#x02717; Could not write to file!'
				);
			}

			echo json_encode(array('response' => $response));
		}
		die();
	}

	/*
	 * Process ajax request for sending a newsletter
	 * Creates a new newsletter and confirm it
	 */
	public function newsmanAjaxSendNewsletter()
	{
		if (isset($_POST['template'], $_POST['subject'], $_POST['list'], $_POST['posts']) && !empty($_POST['template']) && !empty($_POST['subject']) && !empty($_POST['list']) && !empty($_POST['posts']))
		{
			//send newsletter

			$html = $this->constructTemplate($_POST['posts'], $_POST['template']);

			try
			{
				$newsletter_id = $this->client->newsletter->create(
					$_POST['list'], /* The list id */
					$html,/* The html content or false if no html present */
					false, /* The text alternative or false if no text present */
					array(
						"encoding" => "UTF-8",
						"subject" => $_POST['subject'] /* the newsletter subject. will be encoding using encoding. required */
					));
				$this->client->newsletter->confirm(
					$newsletter_id
				);

				echo json_encode(array("status" => 1));
			} catch (Exception $e)
			{
				echo json_encode(array("status" => 0));
			}
		}
		die();
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

		if (filter_var($cl, FILTER_VALIDATE_IP))
		{
			$ip = $cl;
		} elseif (filter_var($forward, FILTER_VALIDATE_IP))
		{
			$ip = $forward;
		} else
		{
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

		if (in_array($plugin, $activePlugins))
		{
			echo json_encode(array("status" => 1));
			exit();
		}
		echo json_encode(array("status" => 0));
		exit();
	}

	public function importWoocommerceSubscribers($list, $segments)
	{
		$woocommerceFilter = array();
		$wpSubscriberFilter = false;
		$wp_subscribers = array();

		switch ($_POST["woocommerceSelect"])
		{
			case "customernewsletter":
				$wpSubscriberFilter = true;
				$woocommerceFilter = array();
				break;
			case "customerscompleted":
				$woocommerceFilter = array(
					'status' => 'completed'
				);
				break;
			case "customers":
				$woocommerceFilter = array();
				break;
		}

		$woocommerceCustomers = array();

		$allOrders = wc_get_orders($woocommerceFilter);

		//Filter orders with customer Subscriber
		if ($wpSubscriberFilter)
		{
			$subscribers = get_users("role=subscriber");

			foreach ($subscribers as $k => $s)
			{
				$wp_subscribers[] = $s->data->user_email;
			}
		}
		//Filter orders with customer Subscriber

		$ordersCount = count($allOrders);

		$email = array();


		if ($ordersCount > 0)
		{
			for ($int = 0; $int < $ordersCount; $int++)
			{
				if (in_array($allOrders[$int]->data["billing"]["email"], $wp_subscribers))
				{
					$email[] = $allOrders[$int]->data["billing"]["email"];
				}
			}
		} else
		{
			$this->setMessageBackend("error ", "No woocommerce customers found or plugin is not installed.");
			return;
		}

		foreach ($email as $_email)
		{
			$woocommerceCustomers[]['email'] = $_email;
		}

		$subscribers = array();
		foreach ($woocommerceCustomers as $k => $s)
		{
			$subscribers[$k]['email'] = $s['email'];
		}

		/*$csv = "email" . PHP_EOL;
		foreach ($subscribers as $s)
		{
			$csv .= $s['email'];
			$csv .= PHP_EOL;
		}

		$csv = utf8_encode($csv);*/

		try
		{
			$_segments = array($segments);
			$customers_to_import = array();

			foreach ($subscribers as $user)
			{
				$customers_to_import[] = array(
					"email" => $user["email"],
					"firstname" => ""
				);
				if ((count($customers_to_import) % $this->batchSize) == 0)
				{
					$this->_importData($customers_to_import, $list, $_segments, $this->client, "newsman plugin wordpress subscribers");
				}
			}
			if (count($customers_to_import) > 0)
			{
				$this->_importData($customers_to_import, $list, $_segments, $this->client, "newsman plugin wordpress subscribers");
			}
			unset($customers_to_import);

				$this->wooCommerce = true;
				$this->setMessageBackend("updated ", 'WooCommerce customers synced with Newsman.');

		} catch (Exception $e)
		{
			$this->setMessageBackend("error ", "Failed to sync Woocommerce customers with Newsman.");
		}

		if (empty($this->message))
		{
			$this->setMessageBackend("updated", 'Options saved.');
		}
	}

	public function importSendPressSubscribers($list, $segments)
	{
		global $wpdb;

		$con = mysqli_connect("localhost", DB_USER, DB_PASSWORD, DB_NAME);
		$sql = "SELECT email, firstname FROM `" . $wpdb->prefix . "sendpress_subscribers`";
		$result = $con->query($sql);

		$sendpr = array();

		if ($result->num_rows > 0)
		{
			while ($row = $result->fetch_assoc())
			{
				//$email[] = $row['email'];
				//$firstname[] = $row['firstname'];

				$sendpr[] = array(
					"email" => $row["email"],
					"firstname" => $row["firstname"]
				);
			}
		} else
		{
			$con->close();
			$this->setMessageBackend("error ", "No SendPress subscribers found or plugin is not installed.");
			return;
		}
		$con->close();

		/*foreach ($email as $_email)
		{
			$sendpress_subscribers[]['email'] = $_email;
		}

		foreach ($firstname as $_firstname)
		{
			$sendpress_subscribers[]['first_name'] = $_firstname;
		}

		$subscribers = array();
		foreach ($sendpress_subscribers as $k => $s)
		{
			$subscribers[$k]['first_name'] = $s['first_name'];
			$subscribers[$k]['email'] = $s['email'];
		}

		$csv = "email, firstname" . PHP_EOL;
		foreach ($subscribers as $s)
		{
			$csv .= $s['email'];
			$csv .= ", ";
			$csv .= $s['first_name'];
			$csv .= PHP_EOL;
		}

		$csv = utf8_encode($csv);*/

		try
		{
			$_segments = array($segments);
			$customers_to_import = array();

			foreach ($sendpr as $user)
			{
				$customers_to_import[] = array(
					"email" => $user["email"],
					"firstname" => $user["firstname"]
				);
				if ((count($customers_to_import) % $this->batchSize) == 0)
				{
					$this->_importData($customers_to_import, $list, $_segments, $this->client, "newsman plugin wordpress subscribers");
				}
			}
			if (count($customers_to_import) > 0)
			{
				$this->_importData($customers_to_import, $list, $_segments, $this->client, "newsman plugin wordpress subscribers");
			}
			unset($customers_to_import);

				$this->sendpressSync = true;
				$this->setMessageBackend("updated ", 'SendPress subscribers synced with Newsman.');

		} catch (Exception $e)
		{
			$this->setMessageBackend("error ", "Failed to sync Sendpress subscribers with Newsman.");
		}

		if (empty($this->message))
		{
			$this->setMessageBackend("updated", 'Options saved.');
		}
	}

	public function importMailPoetSubscribers($list, $segments)
	{
		global $wpdb;

		//get mailpoet subscribers as array
		$mailpoet_subscribers = array();

		$email = array();
		$firstname = array();

		$con = mysqli_connect("localhost", DB_USER, DB_PASSWORD, DB_NAME);
		$sql = "SELECT email, first_name FROM `" . $wpdb->prefix . "mailpoet_subscribers` where `status` = 'subscribed'";
		$result = $con->query($sql);

		if ($result->num_rows > 0)
		{
			// output data of each row
			while ($row = $result->fetch_assoc())
			{
				$mailpoet_subscribers[] = array(
					"email" => $row["email"],
					"firstname" => $row["firstname"]
				);

			//	$email[] = $row['email'];
			//	$firstname[] = $row['firstname'];
			}
		} else
		{
			$con->close();
			$this->setMessageBackend("error", "No MailPoet subscribers found or plugin is not installed.");
			return;
		}
		$con->close();

		/*foreach ($email as $_email)
		{
			$mailpoet_subscribers[]['email'] = $_email;
		}

		foreach ($firstname as $_firstname)
		{
			$mailpoet_subscribers[]['first_name'] = $_firstname;
		}

		$subscribers = array();
		foreach ($mailpoet_subscribers as $k => $s)
		{
			$subscribers[$k]['first_name'] = $s['first_name'];
			$subscribers[$k]['email'] = $s['email'];
		}

		//construct csv string
		$csv = "email, firstname" . PHP_EOL;
		foreach ($subscribers as $s)
		{
			$csv .= $s['email'];
			$csv .= ", ";
			$csv .= $s['first_name'];
			$csv .= PHP_EOL;
		}

		$csv = utf8_encode($csv);*/

		//sync with Newsman from mailpoet
		try
		{
			$_segments = array($segments);
			$customers_to_import = array();

			foreach ($mailpoet_subscribers as $user)
			{
				$customers_to_import[] = array(
					"email" => $user["email"],
					"firstname" => $user["firstname"]
				);
				if ((count($customers_to_import) % $this->batchSize) == 0)
				{
					$this->_importData($customers_to_import, $list, $_segments, $this->client, "newsman plugin wordpress subscribers");
				}
			}
			if (count($customers_to_import) > 0)
			{
				$this->_importData($customers_to_import, $list, $_segments, $this->client, "newsman plugin wordpress subscribers");
			}
			unset($customers_to_import);

			$this->mailpoetSync = true;
			$this->setMessageBackend("updated", 'MailPoet subscribers synced with Newsman.');

		} catch (Exception $e)
		{
			$this->setMessageBackend("error", "Failure to sync MailPoet subscribers with Newsman.");
		}

		if (empty($this->message))
		{
			$this->setMessageBackend("updated", 'Options saved.');
		}
	}

	/*
	 * Imports subscribers from Wordpress Into Newsman and creates a message
	 * @param integer | string 	The id of the list into which to import the subscribers
	 */
	public function importWPSubscribers($list, $segments)
	{
		//get wordpress subscribers as array
		$wp_subscribers = get_users("role=subscriber");
		//$subscribers = array();
		/*foreach ($wp_subscribers as $k => $s)
		{
			$subscribers[$k]['firstname'] = $s->data->display_name;
			$subscribers[$k]['email'] = $s->data->user_email;
		}*/

		//construct csv string
		/*$csv = "email, firstname" . PHP_EOL;
		foreach ($subscribers as $s)
		{
			$csv .= $s['email'];
			$csv .= ", ";
			$csv .= $s['firstname'];
			$csv .= PHP_EOL;
		}

		$csv = utf8_encode($csv);*/

		//sync with newsman
		try
		{
			$_segments = array($segments);
			$customers_to_import = array();

			foreach ($wp_subscribers as $users => $user)
			{
				$customers_to_import[] = array(
					"email" => $user->data->user_email,
					"firstname" => $user->data->display_name
				);
				if ((count($customers_to_import) % $this->batchSize) == 0)
				{
					$this->_importData($customers_to_import, $list, $_segments, $this->client, "newsman plugin wordpress subscribers");
				}
			}
			if (count($customers_to_import) > 0)
			{
				$this->_importData($customers_to_import, $list, $_segments, $this->client, "newsman plugin wordpress subscribers");
			}
			unset($customers_to_import);

			$this->wpSync = true;
			$this->setMessageBackend("updated", 'Subscribers synced with Newsman.');

		} catch (Exception $e)
		{
			$this->setMessageBackend("error", "Failure to sync subscribers with Newsman.");
		}

		if (empty($this->message))
		{
			$this->setMessageBackend("updated", 'Options saved.');
		}
	}

	/*
	 * Loads templates from templates directory (filenames with .php extension)
	 */
	public function findTemplates()
	{

		//check custom templates folder for templates
		if (is_dir(templates_dir))
		{
			if ($handle = opendir(templates_dir))
			{
				while (false !== ($entry = readdir($handle)))
				{
					if ($entry != "." && $entry != ".." && strtolower(substr($entry, strrpos($entry, '.') + 1)) == 'php')
					{
						$template = array();
						$template['name'] = ($r = explode('.', $entry)) ? $r[0] : "";
						$template['filename'] = $entry;
						$this->templates[] = $template;
					}
				}
				closedir($handle);
			}
		}

		//check default templates folder for templates
		//if the templates exists in the custom template folder, it is omitted
		if ($handle = opendir(templates_default_dir))
		{
			while (false !== ($entry = readdir($handle)))
			{
				if ($entry != "." && $entry != ".." && strtolower(substr($entry, strrpos($entry, '.') + 1)) == 'php')
				{
					$template = array();
					$template['name'] = ($r = explode('.', $entry)) ? $r[0] : "";
					$template['filename'] = $entry;
					$exists = false;
					foreach ($this->templates as $item)
					{
						if ($item == $template)
						{
							$exists = true;
						}
					}
					if (!$exists)
					{
						$this->templates[] = $template;
					}
				}
			}
			closedir($handle);
		}

		sort($this->templates);
	}

	/*
	 * @return array The array of template filenames
	 */
	public function getTemplates()
	{
		return $this->templates;
	}

	/*
	 * Merges the template with wordpress posts
	 * @param string $posts_ids 	the number of posts to use (starting with the lastest going backwards)
	 * @param string $template 		the filename of the template
	 * @return string 				The html of the template after including the posts
	 */
	public function constructTemplate($posts_ids, $template)
	{

		$posts = array();

		$posts_ids = explode(",", $posts_ids);
		array_pop($posts_ids);

		foreach ($posts_ids as $id)
		{
			$posts[] = get_post($id);
		}
		foreach ($posts as $k => $post)
		{
			$post->image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'single-post-thumbnail');
			$post->image = $post->image[0];
		}

		$html = $this->engine->render($template, $posts);
		return $html;
	}

	/*
	 * Build Template with a number of posts for previewing in template editor
	 * @param integer $posts 	the number of posts to use (starting with the lastest going backwards)
	 * @param string $template 		the filename of the template
	 * @return string 				The html of the template after including the posts
	 */
	public function constructTemplateEditorPreview($post_nr, $template)
	{
		$posts = wp_get_recent_posts(array(
			'numberposts' => $post_nr,
			'post_type' => 'post',
			'post_status' => 'publish'
		), OBJECT);

		foreach ($posts as $k => $post)
		{
			$post->image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'single-post-thumbnail');
			$post->image = $post->image[0];
			$post->post_author = get_userdata($post->post_author)->display_name;
		}

		$html = $this->engine->render($template, $posts);
		return $html;
	}

	/*
	 * Return the source code of a template
	 * @param string $filename The filename of the template
	 * @return string $source The source of the file
	 */
	protected function getTemplateSource($filename = null)
	{
		if ($filename)
		{
			//look for the template in the upload dir, if it's not there look in plugins folder
			if (file_exists(templates_dir . $filename))
			{
				$filename = templates_dir . $filename;
			} elseif (file_exists(templates_default_dir . $filename))
			{
				$filename = templates_default_dir . $filename;
			} else
			{
				return false;
			}

			$source = file_get_contents($filename);
			return $source;
		}
	}


	/*
	 * Saves changes made to the php template source code
	 * @param string $filename The name of the template (with the php extension)
	 * @param string $source The modified source of the template
	 */
	protected function saveTemplateSource($filename = null, $source)
	{
		if ($filename)
		{
			$upload_dir = wp_upload_dir();
			//check if the upload directory exists if not create it
			if (!is_dir(templates_dir))
			{
				mkdir(templates_dir, 0755, true);
			}

			$status = file_put_contents(templates_dir . $filename, stripcslashes($source));
			if ($status)
			{
				return true;
			} else
			{
				return false;
			}
		}
	}

	function safeForCsv($str)
	{
		return '"' . str_replace('"', '""', $str) . '"';
	}

	function _importData(&$data, $list, $segments = null, $client, $source)
	{
		$csv = '"email","firstname","source"' . PHP_EOL;
		foreach ($data as $_dat)
		{
			$csv .= sprintf(
				"%s,%s,%s",
				$this->safeForCsv($_dat["email"]),
				$this->safeForCsv($_dat["firstname"]),
				$this->safeForCsv($source)
			);
			$csv .= PHP_EOL;
		}
		$ret = null;
		try
		{
			if (is_array($segments) && count($segments) > 0)
			{
				$ret = $client->import->csv($list, $segments, $csv);
			} else
			{
				$ret = $client->import->csv($list, array(), $csv);
			}
			if ($ret == "")
			{
				throw new Exception("Import failed");
			}
		} catch (Exception $e)
		{
			throw new Exception("Import failed");
		}
		$data = array();
	}


}

$wp_newsman = new WP_Newsman();

//include the widget
include 'newsman_widget.php';
?>