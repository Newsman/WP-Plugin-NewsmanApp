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
				<p>In the widget page you can set the messages to display when a visitor subscribes with a valid email
					address as well as an invalid email address.</p>
				<p>To use the widget go to Appearance > Widgets. Look for the widget called Newsman Form and drag it to
					any of the page containers available. You can also edit the tile here.</p>
			</td>
		</tr>
	</table>
</div>