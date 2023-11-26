	<?php

$this->isOauth();

	if (!empty($_POST['newsman_submit']) && $_POST['newsman_submit'] == 'Y')
	{		
		$userid = (isset($_POST['newsman_userid']) && !empty($_POST['newsman_userid'])) ? strip_tags(trim($_POST['newsman_userid'])) : "";
		$apikey = (isset($_POST['newsman_apikey']) && !empty($_POST['newsman_apikey'])) ? strip_tags(trim($_POST['newsman_apikey'])) : "";	
		$allowAPI = (isset($_POST['newsman_api']) && !empty($_POST['newsman_api'])) ? strip_tags(trim($_POST['newsman_api'])) : "";
		$checkoutSMS = (isset($_POST['newsman_checkoutsms']) && !empty($_POST['newsman_checkoutsms'])) ? strip_tags(trim($_POST['newsman_checkoutsms'])) : "";
		$checkoutNewsletter = (isset($_POST['newsman_checkoutnewsletter']) && !empty($_POST['newsman_checkoutnewsletter'])) ? strip_tags(trim($_POST['newsman_checkoutnewsletter'])) : "";
		$checkoutNewsletterType = (isset($_POST['newsman_checkoutnewslettertype']) && !empty($_POST['newsman_checkoutnewslettertype'])) ? strip_tags(trim($_POST['newsman_checkoutnewslettertype'])) : "";
		$newsman_checkoutnewslettermessage = (isset($_POST['newsman_checkoutnewslettermessage']) && !empty($_POST['newsman_checkoutnewslettermessage'])) ? strip_tags(trim($_POST['newsman_checkoutnewslettermessage'])) : "";		
		$checkoutNewsletterDefault = (isset($_POST['newsman_checkoutnewsletterdefault']) && !empty($_POST['newsman_checkoutnewsletterdefault'])) ? strip_tags(trim($_POST['newsman_checkoutnewsletterdefault'])) : "";
		$form_id = (isset($_POST['newsman_form_id']) && !empty($_POST['newsman_form_id'])) ? strip_tags(trim($_POST['newsman_form_id'])) : "";

		$this->constructClient($userid, $apikey);

		update_option("newsman_userid", $this->userid);
		update_option("newsman_apikey", $this->apikey);
		update_option("newsman_api", $allowAPI);
		update_option("newsman_checkoutsms", $checkoutSMS);
		update_option("newsman_checkoutnewsletter", $checkoutNewsletter);
		update_option("newsman_checkoutnewslettertype", $checkoutNewsletterType);				
		update_option("newsman_checkoutnewslettermessage", $newsman_checkoutnewslettermessage);
		update_option("newsman_checkoutnewsletterdefault", $checkoutNewsletterDefault);
		update_option("newsman_form_id", $form_id);

		$this->isOauth();

		try
		{
			$available_lists = $this->client->list->all();

			$available_segments = array();
			if (!empty($list))
			{
				$available_segments = $this->client->segment->all($list);
			}			
			
			$this->setMessageBackend("updated", "Options saved.");
		} catch (Exception $e)
		{
			$this->valid_credential = false;
			$this->setMessageBackend('error', 'Invalid Credentials');
		}
	} else
	{	
		$userid = get_option('newsman_userid');
		$apikey = get_option('newsman_apikey');
		$allowAPI = get_option('newsman_api');
		$checkoutSMS = get_option('newsman_checkoutsms');
		$checkoutNewsletter = get_option('newsman_checkoutnewsletter');
		$checkoutNewsletterType = get_option('newsman_checkoutnewslettertype');
		$newsman_checkoutnewslettermessage = get_option('newsman_checkoutnewslettermessage');
		$checkoutNewsletterDefault = get_option('newsman_checkoutnewsletterdefault');
		$form_id = get_option('newsman_form_id');

		try
		{
			$available_lists = $this->client->list->all();

			$available_segments = array();
			if (!empty($list))
			{
				$available_segments = $this->client->segment->all($list);
			}					

		} catch (Exception $e)
		{
			$this->valid_credential = false;
			$this->setMessageBackend('error', 'Invalid Credentials');
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
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="tabSettings" aria-controls="" checked>
	<label for="tabSettings" id="settingsBtn">Settings</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>
   
  <div class="tab-panels">
    <section id="tabSettings" class="tab-panel">
      
	<div class="wrap wrap-settings-admin-page">
		<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="newsman_submit" value="Y"/>

			<div class="<?php echo (is_array($this->message) && array_key_exists("status", $this->message)) ? $this->message["status"] : ""; ?>"><p><strong><?php echo (is_array($this->message) && array_key_exists("message", $this->message)) ? $this->message["message"] : ""; ?></strong>
				</p></div>			

			<h2>Newsman Connection</h2>
			<table class="form-table newsmanTable newsmanTblFixed">
				<tr>
					<th scope="row">
						<label for="newsman_apikey">API KEY</label>
					</th>
					<td>
						<input type="text" name="newsman_apikey" value="<?php echo $apikey; ?>"/>
						<p class="description">Your Newsman API KEY</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="newsman_userid">User ID</label>
					</th>
					<td>
						<input type="text" name="newsman_userid" value="<?php echo $userid; ?>"/>
						<p class="description">Your Newsman User ID</p>
					</td>
				</tr>			

				</table>				

				<h2>Settings</h2>
				<table class="form-table newsmanTable newsmanTblFixed">

					<tr>
						<th scope="row">
							<label for="newsman_api">Allow API access</label>
						</th>
						<td>

						<input name="newsman_api" type="checkbox" id="newsman_api" <?php echo (!empty($allowAPI) && $allowAPI == "on") ? "checked" : ""; ?>/>								
						
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsman_checkoutnewsletter">Checkout newsletter subscribe checkbox</label>
						</th>
						<td>

						<input name="newsman_checkoutnewsletter" type="checkbox" id="newsman_checkoutnewsletter" <?php echo (!empty($checkoutNewsletter) && $checkoutNewsletter == "on") ? "checked" : ""; ?>/>																
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsman_checkoutsms">Checkout SMS, sync phone numbers to your SMS list</label>
						</th>
						<td>

						<input name="newsman_checkoutsms" type="checkbox" id="newsman_checkoutsms" <?php echo (!empty($checkoutSMS) && $checkoutSMS == "on") ? "checked" : ""; ?>/>																
						</td>
					</tr>
					<tr class="newsman_checkoutnewslettertypePanel" style="display: <?php echo (!empty($checkoutNewsletter) && $checkoutNewsletter == 'on') ? 'table-row' : 'none'; ?>;">
						<th scope="row">
							<label for="newsman_checkoutnewslettertype">Checkout newsletter subscribe checkbox event type</label>
						</th>
						<td>

						<select name="newsman_checkoutnewslettertype" id="">					
								<option value="save" <?php echo $checkoutNewsletterType == "save" ? "selected = ''" : ""; ?>>Subscribes a subscriber to the list</option>
								<option value="init" <?php echo $checkoutNewsletterType == "init" ? "selected = ''" : ""; ?>>Inits a confirmed opt in subscribe to the list</option>
							</select>														
						</td>
					</tr>
					<tr class="newsman_checkoutnewslettertypePanel" style="display: <?php echo (!empty($checkoutNewsletter) && $checkoutNewsletter == 'on') ? 'table-row' : 'none'; ?>;">
						<th scope="row">
							<label for="newsman_checkoutnewslettermessage">Checkout newsletter subscribe checkbox message</label>
						</th>
						<td>

						<input type="text" id="newsman_checkoutnewslettermessage" name="newsman_checkoutnewslettermessage" value="<?php echo (!empty($newsman_checkoutnewslettermessage)) ? $newsman_checkoutnewslettermessage : "Subscribe to our newsletter"; ?>"/>													
						</td>
					</tr>
					<tr class="newsman_checkoutnewslettertypePanel" style="display: <?php echo (!empty($checkoutNewsletter) && $checkoutNewsletter == 'on') ? 'table-row' : 'none'; ?>;">
						<th scope="row">
							<label for="newsman_checkoutnewsletterdefault">Checkout newsletter subscribe checkbox checked by default</label>
						</th>
						<td>

						<input name="newsman_checkoutnewsletterdefault" type="checkbox" id="newsman_checkoutnewsletterdefault" <?php echo (!empty($checkoutNewsletterDefault) && $checkoutNewsletterDefault == "on") ? "checked" : ""; ?>/>																
						</td>
					</tr>
					<tr class="newsman_checkoutnewslettertypePanel" style="display: <?php echo (!empty($checkoutNewsletter) && $checkoutNewsletter == 'on') ? 'table-row' : 'none'; ?>;">
						<th scope="row">
							<label for="newsman_form_id">Form_id: the form of the form used for the confirmation email / form settings. Forms can be created admin.</label>
						</th>
						<td>

						<input type="text" id="newsman_form_id" name="newsman_form_id" value="<?php echo (!empty($form_id)) ? $form_id : ""; ?>" placeholder="form id"/>													
						</td>
					</tr>

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
	