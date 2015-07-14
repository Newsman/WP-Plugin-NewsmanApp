<?php 
/*
 Plugin Name: NewsmanApp for Wordpress
 Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 Description: NewsmanApp for Wordpress (sign up widget, subscribers sync, create and send newsletters from blog posts)
 Version: 1.0
 Author: newsmanapp
 Author URI: https://www.newsmanapp.com
 */

//Check if credentials are valid
	try{
		$available_lists = $this->client->list->all();
		$credentials_status = "credentials-valid";
	}catch( Exception $e ){
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
					<div class="credentials-status <?php echo $credentials_status?>"> </div> <span><?php echo ($credentials_status == "credentials-valid") ? "valid" : "invalid"?></span>
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
					<p>After entering and saving valid credentials you will be able to select a list to which subscribers will be added.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_apikey">Sync</label>
				</th>
				<td>
					<p>In the Sync page you cand save your current wordpress subscriber to Newsman.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_apikey">Widget</label>
				</th>
				<td>
					<p>In the widget page you can set the messages to display when a visitor subscribes with a valid email address as well as an invalid email address.</p>
					<p>To use the widget go to Appearance > Widgets. Look for the widget called Newsman Form and drag it to any of the page containers available. You can also edit the tile here.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_apikey">Newsletter</label>
				</th>
				<td>
					<p>In the Newsletter page you can send an email newsletter to all subscribers in the current email list. Select a template, set the subject and select the posts you want to include in the newsletter.</p>
				</td>
			</tr>
		</table>		
</div>