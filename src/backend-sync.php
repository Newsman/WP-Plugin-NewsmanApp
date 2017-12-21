<?php
//process form submission
$wooCommerceApi = $this->GetWooCommerceApi();

if ($_POST['newsman_submit'] == 'Y')
{
	$userid = get_option("newsman_userid");
	$apikey = get_option("newsman_apikey");
	$list = get_option("newsman_list");

	$this->constructClient($userid, $apikey);

	if (isset($_POST['mailpoet_subscribeBtn']))
	{
		//import mailPoet
		try
		{
			$available_lists = $this->client->list->all();
			$this->importMailPoetSubscribers($list);
		} catch (Exception $e)
		{
			$this->valid_credential = false;
			$this->setMessageBackend('error', 'Invalid Credentials');
		}
	} elseif (isset($_POST['sendPress_subscribeBtn']))
	{
		//import sendPress
		try
		{
			$available_lists = $this->client->list->all();
			$this->importSendPressSubscribers($list);
		} catch (Exception $e)
		{
			$this->valid_credential = false;
			$this->setMessageBackend('error', 'Invalid Credentials');
		}
	} elseif (isset($_POST['wooCommerce_subscribeBtn']))
	{
		try
		{
			$available_lists = $this->client->list->all();
			$this->importWoocommerceSubscribers($list);
		} catch (Exception $e)
		{
			$this->valid_credential = false;
			$this->setMessageBackend('error', 'Invalid Credentials');
		}

		/*
		$consumerKey = $_POST["consumerKey"];
		$consumerSecret = $_POST["consumerSecret"];
		$store_url = 'http://wp.corodeanu.dazoot.ro';

		$options = array(
			'debug' => true,
			'return_as_array' => false,
			'validate_url' => false,
			'timeout' => 30,
			'ssl_verify' => false,
		);

		try
		{
			$client = new WC_API_Client($store_url, $consumerKey, $consumerSecret, $options);

			print_r($client->orders->get());
		} catch (WC_API_Client_Exception $e)
		{
			echo $e->getMessage() . PHP_EOL;
			echo $e->getCode() . PHP_EOL;

			if ($e instanceof WC_API_Client_HTTP_Exception)
			{

				print_r($e->get_request());
				print_r($e->get_response());
			}
		}
		*/

		/*
		$wc_api = new WC_API_Client($consumerKey, $consumerSecret, $store_url);
		$customers = $wc_api->get_products();
		*/
		//print_r($customers);
	} else
	{
		//import wordpress
		try
		{
			$available_lists = $this->client->list->all();
		} catch (Exception $e)
		{
			$this->valid_credential = false;
			$this->setMessageBackend('error', 'Invalid Credentials');
		}

		$this->importWPSubscribers($list);
	}

} else
{
	try
	{
		$available_lists = $this->client->list->all();
	} catch (Exception $e)
	{
		$this->valid_credential = false;
		$this->setMessageBackend('error', 'Invalid Credentials');
	}
}
?>

<div class="wrap">
	<form method="post" enctype="multipart/form-data">
		<div class="container" style="margin-top: 20px;">
			<h2>Select source to sync with Newsman account:</h2>
			<div class="pluginsItem">
				<input type="button" name="newsman_Panel" id="newsman_Panel" value="Wordpress Sync"
				       class="button button-primary pluginsItemBtn"/>
			</div>
			<div class="pluginsItem">
				<input type="button" name="newsman_mailPoetPanel" id="newsman_mailPoetPanel" value="MailPoet Sync"
				       class="button button-primary pluginsItemBtn"/>
			</div>
			<div class="pluginsItem">
				<input type="button" name="newsman_sendPressPanel" id="newsman_sendPressPanel" value="SendPress Sync"
				       class="button button-primary pluginsItemBtn"/>
			</div>
			<div class="pluginsItem">
				<input type="button" name="newsman_wooCommercePanel" id="newsman_wooCommercePanel"
				       value="WooCommerce Sync"
				       class="button button-primary pluginsItemBtn"/>
			</div>
			<div class="pluginsHolder">
				<div class="pluginsItem">
					<ul id="submitNewsman" style="display: none;">
						<li>
							<input type="hidden" name="newsman_submit" value="Y"/>
							<h2>Wordpress import subscribers</h2>
						</li>
						<li>
							<div>
								<label for="import_subscribers_from_wordpress">Clicking the button below automatically
									imports
									your wordpress subscribers into Newsman.</label>
							</div>
							<?php if (isset($list))
							{ ?>
							<table class="form-table">
								<tr scope="row">
									<?php if (isset($_POST['newsman_subscribeBtn'])) : ?>
										<?php if ($_POST['newsman_submit'] == 'Y'): ?>
											<?php if ($this->wpSync): ?>
												<th>
													<div id="nws-message" class="updated"><p><strong>Imported
																Successfully from WordPress</strong></p>
													</div>
												</th>
											<?php else: ?>
												<th>
													<div id="nws-message" class="error"><p><strong>
																<?php
																$arr = $this->getBackendMessage();
																foreach ($arr as $array => $s)
																	echo $s;
																?></strong></p></div>
												</th>
											<?php endif; ?>
										<?php endif; ?>
									<?php endif; ?>
								</tr>
								<?php } ?>
							</table>
							<input type="submit" name="newsman_subscribeBtn" value="Import Subscribers"
							       class="button button-primary"/>
						</li>
					</ul>
				</div>
				<div class="pluginsItem">
					<ul id="submitMailPoet" style="display: none;">
						<li><h2>MailPoet import subscribers</h2></li>
						<li>
							<div>
								<label for="import_subscribers_from_wordpress">Clicking the button below automatically
									imports
									your mailpoet subscribers into Newsman.</label>
							</div>
							<?php if (isset($_POST['mailpoet_subscribeBtn']))
							{ ?>
								<?php if ($this->mailpoetSync): ?>
								<table class="form-table">
								<tr scope="row">
								<th>
									<div id="nws-message" class="updated"><p><strong>Imported Successfully from MailPoet
												plugin</strong></p>
									</div>
								</th>
							<?php else: ?>
								<th>
									<div id="nws-message" class="error"><p><strong>
												<?php
												$arr = $this->getBackendMessage();
												foreach ($arr as $array => $s)
													echo $s;
												?></strong></p></div>
								</th>
								</tr>
								</table>
							<?php endif; ?>
							<?php } ?>
							<input type="submit" name="mailpoet_subscribeBtn" value="Import MailPoet Subscribers"
							       class="button button-primary"/>
						</li>
					</ul>
				</div>
				<div class="pluginsItem">
					<ul id="submitSendPress" style="display: none;">
						<li><h2>SendPress import subscribers</h2></li>
						<li>
							<div>
								<label for="import_subscribers_from_wordpress">Clicking the button below automatically
									imports
									your sendpress subscribers into Newsman.</label>
							</div>
							<?php if (isset($_POST['sendPress_subscribeBtn']))
							{ ?>
								<?php if ($this->sendpressSync): ?>
								<table class="form-table">
								<tr scope="row">
								<th>
									<div id="nws-message" class="updated"><p><strong>Imported Successfully from
												SendPress
												plugin</strong></p>
									</div>
								</th>
							<?php else: ?>
								<th>
									<div id="nws-message" class="error"><p><strong>
												<?php
												$arr = $this->getBackendMessage();
												foreach ($arr as $array => $s)
													echo $s;
												?></strong></p></div>
								</th>
								</tr>
								</table>
							<?php endif; ?>
							<?php } ?>
							<input type="submit" name="sendPress_subscribeBtn" value="Import SendPress Subscribers"
							       class="button button-primary"/>
						</li>
					</ul>
				</div>
				<div class="pluginsItem">
					<ul id="submitWooCommerce" style="display: none;">
						<li><h2>WooCommerce import subscribers</h2></li>
					    <!--
						<li>
							<div>
								<input type="button" name="wooCommerce_connectBtn" id="wooCommerce_connectBtn" value="Connect"
								       class="button button-primary"/>
							</div>
						</li>
						<li>
							<div>
								<label>Consumer key</label>
								<select id="consumerKey" name="consumerKey">
									<?php
									$int = count($wooCommerceApi["consumer_key"]);
									for ($no = 0; $no < $int; $no++)
									{ ?>
										<option
											value="<?php echo $wooCommerceApi["consumer_key"][$no]; ?>"><?php echo $wooCommerceApi["consumer_key"][$no]; ?></option>
									<?php } ?>
								</select>
							</div>
							<div>
								<label>Consumer secret</label>
								<select id="consumerSecret" name="consumerSecret">
									<?php
									$int = count($wooCommerceApi["consumer_secret"]);
									for ($no = 0; $no < $int; $no++)
									{ ?>
										<option
											value="<?php echo $wooCommerceApi["consumer_secret"][$no]; ?>"><?php echo $wooCommerceApi["consumer_secret"][$no]; ?></option>
									<?php } ?>
								</select>
							</div>
							-->
							<?php if (isset($_POST['wooCommerce_subscribeBtn']))
							{ ?>
								<?php if ($this->wooCommerce): ?>
								<table class="form-table">
								<tr scope="row">
								<th>
									<div id="nws-message" class="updated"><p><strong>Imported Successfully from
												WooCommerce
												plugin</strong></p>
									</div>
								</th>
							<?php else: ?>
								<th>
									<div id="nws-message" class="error"><p><strong>
												<?php
												$arr = $this->getBackendMessage();
												foreach ($arr as $array => $s)
													echo $s;
												?></strong></p></div>
								</th>
								</tr>
								</table>
							<?php endif; ?>
							<?php } ?>
							<input type="submit" name="wooCommerce_subscribeBtn" value="Import WooCommerce Subscribers"
							       class="button button-primary"/>
						</li>
					</ul>
				</div>
				<div class="pluginsItem">
					<ul id="submitSendPress" style="display: none;">
						<li><h2>SendPress import subscribers</h2></li>
						<li>
							<div>
								<label for="import_subscribers_from_wordpress">Clicking the button below automatically
									imports
									your sendpress subscribers into Newsman.</label>
							</div>
							<?php if (isset($_POST['sendPress_subscribeBtn']))
							{ ?>
								<?php if ($this->sendpressSync): ?>
								<table class="form-table">
								<tr scope="row">
								<th>
									<div id="nws-message" class="updated"><p><strong>Imported Successfully from
												SendPress
												plugin</strong></p>
									</div>
								</th>
							<?php else: ?>
								<th>
									<div id="nws-message" class="error"><p><strong>
												<?php
												$arr = $this->getBackendMessage();
												foreach ($arr as $array => $s)
													echo $s;
												?></strong></p></div>
								</th>
								</tr>
								</table>
							<?php endif; ?>
							<?php } ?>
							<input type="submit" name="sendPress_subscribeBtn" value="Import SendPress Subscribers"
							       class="button button-primary"/>
						</li>
					</ul>
				</div>
				<div id="activatedPluginMsg" class="error" style="display: none;">
					<strong>
					</strong>
				</div>
			</div>
		</div>
	</form>
</div>