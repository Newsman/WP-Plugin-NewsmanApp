<?php

$this->isOauth();

try
{
	$available_lists = $this->client->list->all();
	$credentials_status = "credentials-valid";
} catch (Exception $e)
{
	$this->valid_credential = false;
	$credentials_status = "credentials-invalid";
}
?>

<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" />
	</a>
</div>
<div class="tabset">

<input type="radio" name="tabset" id="tabNewsman" aria-controls="" checked>
	<label for="tabNewsman" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>
   
  <div class="tab-panels">
    <section id="tabNewsman" class="tab-panel">
      
		<div class="wrap">
			<h2>Newsman Info</h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="">Credentials Status</label>
					</th>
					<td>
						<div class="credentials-status <?php echo $credentials_status ?>"></div>
						<span><?php echo ($credentials_status == "credentials-valid") ? "valid" : "invalid" ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="">Newsman Menus</label>
					</th>
				</tr>
				<tr>
					<th scope="row">
						<label for="">Sync</label>
					</th>
					<td>
						<p>You will be able to sync your shop customers and newsletter subscribers with Newsman list / segments</p>
					</td>
				</tr>	
				<tr>
					<th scope="row">
						<label for="">Remarketing</label>
					</th>
					<td>
						<p>Provide a valuable experience to your customer by automating communication with them. Create automatic email flows based on their actions on the site, such as product viewed, added to cart, completed order etc.</p>
					</td>
				</tr>	
				<tr>
					<th scope="row">
						<label for="">SMS</label>
					</th>
					<td>
						<p>SMS (short messages) is one of the most effective ways to get users’ attention. Create and manage campaigns or send transactional SMS directly from NewsMAN platform.</p>
					</td>
				</tr>	
				<tr>
					<th scope="row">
						<label for="">Settings</label>
					</th>
					<td>
						<p>In the settings page enter your API Key and User id provided by Newsman.</p>
						<p>After entering and saving valid credentials you will be able to select a list to which subscribers
							will be added.</p>
					</td>
				</tr>		
				<tr>
					<th scope="row">
						<label for="">Widget</label>
					</th>
					<td>
						<p>Transform your website visitors into subscribers and customers. Generate easy to integrate pop-ups, embedded signup forms and web layers to convert more.</p>
						<p>
						Log in to your Newsman account: Select List -> Settings -> Subscription forms -> Create/Edit form -> Modal window -> Activate modal window for newsletter subscription -> Select embedded form. Copy paste Shortcode newsman_subscribe_widget
						</p>
					</td>
				</tr>
			</table>
		</div>

  	</section>  
  </div>  
</div>