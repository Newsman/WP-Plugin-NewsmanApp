<?php
//Check if credentials are valid
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

<div class="wrap">
	<h2>Newsman Info</h2>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="newsman_apikey">Credentials Status</label>
			</th>
			<td>
				<div class="credentials-status <?php echo $credentials_status ?>"></div>
				<span><?php echo ($credentials_status == "credentials-valid") ? "valid" : "invalid" ?></span>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="newsman_apikey">Newsman Menus</label>
			</th>
		</tr>
		<tr>
			<th scope="row">
				<label for="newsman_apikey">Settings</label>
			</th>
			<td>
				<p>In the settings page enter your API Key and User id provided by Newsman.</p>
				<p>After entering and saving valid credentials you will be able to select a list to which subscribers
					will be added.</p>
			</td>
		</tr>		
		<tr>
			<th scope="row">
				<label for="newsman_apikey">Widget</label>
			</th>
			<td>
				<p>
				Log in to your Newsman account: Select List -> Settings -> Subscription forms -> Create/Edit form -> Modal window -> Activate modal window for newsletter subscription -> Select embedded form. Copy paste Shortcode newsman_subscribe_widget
				</p>
			</td>
		</tr>
	</table>
</div>