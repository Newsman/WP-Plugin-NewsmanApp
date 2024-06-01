<?php

$this->isOauth();

if (!empty($_POST['newsman_sync']) && $_POST['newsman_sync'] == 'Y')
{		
	$list = (isset($_POST['newsman_list']) && !empty($_POST['newsman_list'])) ? strip_tags(trim($_POST['newsman_list'])) : "";
	$smslist = (isset($_POST['newsman_smslist']) && !empty($_POST['newsman_smslist'])) ? strip_tags(trim($_POST['newsman_smslist'])) : "";
	$segments = (isset($_POST['newsman_segments']) && !empty($_POST['newsman_segments'])) ? strip_tags(trim($_POST['newsman_segments'])) : "";
	
	$this->constructClient($this->userid, $this->apikey);
	
	update_option("newsman_userid", $this->userid);
	update_option("newsman_apikey", $this->apikey);
	update_option("newsman_list", $list);
	update_option("newsman_smslist", $smslist);
	update_option("newsman_segments", $segments);	

	if(isset($_POST['newsman_list']) && !empty($_POST['newsman_list']))
	{
		if (class_exists('WooCommerce')) {
			
			$args = array(
				'stock_status' => 'instock'
			);
			$products = wc_get_products($args);

			if(!empty($products)){

				$url = get_site_url() . "/?newsman=products.json&nzmhash=" . $this->apikey;					

				try{
					$ret = $this->client->feeds->setFeedOnList($list, $url, get_site_url(), "NewsMAN");	
				}
				catch(Exception $ex)
				{			
					$this->setMessageBackend('error', 'Could not update feed list');
				}

			}
		}
	}		

	try
	{
		$available_lists = $this->client->list->all();

		$available_segments = array();
		if (!empty($list))
		{
			$available_segments = $this->client->segment->all($list);
		}

		$available_smslists = $this->client->sms->lists();
		
		$this->setMessageBackend("updated", "Options saved.");
	} catch (Exception $e)
	{
		$this->valid_credential = false;
		$this->setMessageBackend('error', 'Invalid Credentials');
	}
} else
{
	$list = get_option('newsman_list');
	$smslist = get_option('newsman_smslist');
	$segments = get_option('newsman_segments');

	try
	{
		$available_lists = $this->client->list->all();

		$available_segments = array();
		if (!empty($list))
		{
			$available_segments = $this->client->segment->all($list);
		}
		
		$available_smslists = $this->client->sms->lists();		

	} catch (Exception $e)
	{
		$this->valid_credential = false;
		$this->setMessageBackend('error', $e->getMessage());
	}
}

?>

<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" />
	</a>
</div>
<div class="tabset">

<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="tabSync" aria-controls="" checked>
	<label for="tabSync" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>
   
  <div class="tab-panels">
    <section id="tabSync" class="tab-panel">
      
		<div class="wrap wrap-settings-admin-page">
		<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="newsman_sync" value="Y"/>		

			<h2>Sync</h2>

			<div class="<?php echo (is_array($this->message) && array_key_exists("status", $this->message)) ? $this->message["status"] : ""; ?>"><p><strong><?php echo (is_array($this->message) && array_key_exists("message", $this->message)) ? $this->message["message"] : ""; ?></strong>
				</p></div>				
			
			<table class="form-table newsmanTable newsmanTblFixed">			

				<?php //if (isset($available_lists) && !empty($available_lists)) { ?>
					<tr>
						<th scope="row">
							<label for="newsman_list">Select a list</label>
						</th>
						<td>
							<select name="newsman_list" id="">
								<option value="0">-- select list --</option>
								<?php foreach ($available_lists as $l)
								{ ?>
									<option
										value="<?php echo $l['list_id'] ?>" <?php echo $l['list_id'] == $list ? "selected = ''" : ""; ?>><?php echo $l['list_name']; ?></option>
								<?php } ?>
							</select>
							<p class="description">Select a list of subscribers</p>
						</td>
					</tr>
				<?php //} ?>

				<?php //if (isset($available_lists) && !empty($available_lists)) { ?>
					<tr>
						<th scope="row">
							<label for="newsman_smslist">Select an SMS list</label>
						</th>
						<td>
							<select name="newsman_smslist" id="">
								<option value="0">-- select list --</option>
								<?php foreach ($available_smslists as $l)
								{ ?>
									<option
										value="<?php echo $l['list_id'] ?>" <?php echo $l['list_id'] == $smslist ? "selected = ''" : ""; ?>><?php echo $l['list_name']; ?></option>
								<?php } ?>
							</select>
							<p class="description">Select a list of SMS to be synced with phone numbers</p>
						</td>
					</tr>
				<?php //} ?>

				<?php //if (isset($available_segments) && !empty($available_segments)) { ?>
					<tr>
						<th scope="row">
							<label for="newsman_segments">Select a segment</label>
						</th>
						<td>
							<select name="newsman_segments" id="">
								<option value="0">-- select segment (optional) --</option>
								<?php foreach ($available_segments as $l)
								{ ?>
									<option
										value="<?php echo $l['segment_id']; ?>" <?php echo $l['segment_id'] == $segments ? "selected = ''" : ""; ?>><?php echo $l['segment_name']; ?></option>
								<?php } ?>
							</select>
							<p class="description">Select a segment</p>
						</td>
					</tr>
				<?php //} ?>

					<tr>
						<th>
						SYNC via CRON Job (Task scheduler)
						<p class="newsmanP">click the links to begin Sync or setup task scheduler (cron) on your server/hosting<p>
						<br>
						<br>
						<p class="newsmanP">{{limit}} = Sync with newsman from latest number of records (ex: 5000)</p>
						</th>
						<td>
							<?php 
								$wordpressUrl = get_site_url() . "/?newsman=cron.json&method=wordpress&nzmhash=" . $this->apikey . "&start=1&limit=5000&cronlast=true";
								$woocommerceUrl = get_site_url() . "/?newsman=cron.json&method=woocommerce&nzmhash=" . $this->apikey . "&start=1&limit=5000&cronlast=true";

								echo $url = "CRON url Sync wordpress subscribers: <a href='" . $wordpressUrl . "' target='_blank'>" . $wordpressUrl . "</a>";	
								echo "<br><br>";
								echo $url = "CRON url Sync customers with orders completed: <a href='" . $woocommerceUrl . "' target='_blank'>" . $woocommerceUrl . "</a>";		
							?>									
						</td>
					</tr>
				
				</table>
					<th>
					</th>

				</table>
				<div style="padding-top: 5px;">
					<input type="submit" value="Save Changes" class="button button-primary"/>
				</div>
			</form>
		</div>

  	</section>  
  </div>  
</div>
