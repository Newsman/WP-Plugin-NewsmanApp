<?php 

/*
 Plugin Name: NewsmanApp for Wordpress
 Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 Description: NewsmanApp for Wordpress (sign up widget, subscribers sync, create and send newsletters from blog posts)
 Version: 1.0
 Author: newsmanapp
 Author URI: https://www.newsmanapp.com
 */

?><div id="newsman_subscribtion_message"></div>
<form method="post" class="newsman-subscription-form">
	<input type="hidden" name="newsman_subscription_submited" value="Y" />
	<dl>
		<dt>
			<label for="newsman_subscription_email">Email</label>
		</dt>
		<dd>
			<input type="text" name="newsman_subscription_email" />
		</dd>
		<dd>
			<input type="submit" value="Subscribe" />
		</dd>
	</dl>
</form>