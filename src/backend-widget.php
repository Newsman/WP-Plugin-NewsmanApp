<?php
/*OBSOLETE
if ($_POST['newsman_submit'] == 'Y')
{
	// process form submission
	$confirm = (isset($_POST['newsman_widget_confirm']) && !empty($_POST['newsman_widget_confirm'])) ? strip_tags(trim($_POST['newsman_widget_confirm'])) : get_option($confirm);
	$infirm = (isset($_POST['newsman_widget_infirm']) && !empty($_POST['newsman_widget_infirm'])) ? strip_tags(trim($_POST['newsman_widget_infirm'])) : get_option($infirm);
	$title = (isset($_POST['newsman_widget_title']) && !empty($_POST['newsman_widget_title'])) ? strip_tags(trim($_POST['newsman_widget_title'])) : get_option($title);

	$name = (isset($_POST['newsman_name']) && !empty($_POST['newsman_name'])) ? strip_tags(trim($_POST['newsman_name'])) : get_option($name);

	$compliant1 = (isset($_POST['newsman_widget_compliant1']) && !empty($_POST['newsman_widget_compliant1'])) ? strip_tags(trim($_POST['newsman_widget_compliant1'])) : get_option($compliant1);
	$compliant2 = (isset($_POST['newsman_widget_compliant2']) && !empty($_POST['newsman_widget_compliant2'])) ? strip_tags(trim($_POST['newsman_widget_compliant2'])) : get_option($compliant2);

	$compliant1Url = (isset($_POST['newsman_widget_compliant1_url']) && !empty($_POST['newsman_widget_compliant1_url'])) ? strip_tags(trim($_POST['newsman_widget_compliant1_url'])) : get_option($compliant1Url);
	$compliant2Url = (isset($_POST['newsman_widget_compliant2_url']) && !empty($_POST['newsman_widget_compliant2_url'])) ? strip_tags(trim($_POST['newsman_widget_compliant2_url'])) : get_option($compliant2Url);


	update_option("newsman_widget_confirm", $confirm);
	update_option('newsman_name', $name);
	update_option("newsman_widget_infirm", $infirm);
	update_option("newsman_widget_compliant1", $compliant1);
	update_option("newsman_widget_compliant2", $compliant2);
	update_option("newsman_widget_compliant1_url", $compliant1Url);
	update_option("newsman_widget_compliant2_url", $compliant2Url);	

	$message = array(
		'status' => 'updated',
		'message' => 'Options saved.'
	);
} else
{
	$confirm = get_option('newsman_widget_confirm');
	$name = get_option('newsman_name');
	$infirm = get_option('newsman_widget_infirm');
	$compliant1 = get_option('newsman_widget_compliant1');
	$compliant2 = get_option('newsman_widget_compliant2');
	$compliant1Url = get_option('newsman_widget_compliant1_url');
	$compliant2Url = get_option('newsman_widget_compliant2_url');
}
*/
?>

<div class="wrap">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="newsman_submit" value="Y"/>
		<h2>Newsman Widget setup</h2>

		<div class="<?php echo $this->message['status'] ?>"><p><strong><?php _e($this->message['message']); ?></strong>
			</p></div>

			<h2>Widget from Newsman Shortcode</h2>

			<table class="form-table">
			<tr>
				<th scope="row"> Info</th>
				<td>
					<p class="description">
						Log in to your <a target="_blank" href="https://newsman.app">Newsman account</a>: Select List -> Settings -> Subscription forms -> Create/Edit form -> Modal window -> Activate modal window for newsletter subscription -> Select embedded form.
						Copy paste Shortcode `newsman_subscribe_widget` into wordpress pages/posts
					</p>
				</td>
			</tr>
			</table>

			<!--OBSOLETE
			<h2>OR</h2>

			<h2>Widget from Wordpress Newsman plugin</h2>

			<table class="form-table">
			<tr>
				<th scope="row"> Info</th>
				<td>
					<p class="description">
						In the widget page you can set the messages to display when a visitor subscribes with a valid
						email address as well as an invalid email address.
						<br>To use the widget go to Appearance > Widgets. Look for the widget called Newsman Form and
						drag it to any of the page containers available. You can also edit the tile here.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label>Display Name & Lastname fields ?</label>
				</th>
				<td>
					<input name="newsman_name" type="checkbox" id="newsman_name" <?php echo (!empty($name) && $name == "on") ? "checked" : ""; ?>/>				
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_widget_confirm">Confirmation Message</label>
				</th>
				<td>
					<textarea type="text" name="newsman_widget_confirm"
					          value="<?php echo $confirm ?>"><?php echo $confirm ?></textarea>
					<p class="description">The message to display when a valid email is entered.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_widget_infirm">Invalid Email Message</label>
				</th>
				<td>
					<textarea type="text" name="newsman_widget_infirm"
					          value="<?php echo $infirm ?>"><?php echo $infirm ?></textarea>
					<p class="description">The message to display when an invalid email address is entered.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_widget_compliant1">Policy Subscription 1 Checkbox</label>
				</th>
				<td>
					<textarea id="newsman_widget_compliant1" type="text" name="newsman_widget_compliant1"
					          value="<?php echo $compliant1 ?>"><?php echo $compliant1 ?></textarea>
					<p class="description">User must check before submit (Leave empty if not mandatory)</p>
					<input style="display: block;" id="newsman_widget_compliant1_url" type="text"
					       name="newsman_widget_compliant1_url"
					       value="<?php echo $compliant1Url; ?>"
					       placeholder="www.example.com"></input>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_widget_compliant2">Policy Subscription 2 Checkbox</label>
				</th>
				<td>
					<textarea id="newsman_widget_compliant2" type="text" name="newsman_widget_compliant2"
					          value="<?php echo $compliant2 ?>"><?php echo $compliant2 ?></textarea>
					<p class="description">User must check before submit (Leave empty if not mandatory)</p>
					<input style="display:block;" id="newsman_widget_compliant2_url" type="text"
					       name="newsman_widget_compliant2_url"
					       value="<?php echo $compliant2Url ?>"
					       placeholder="www.example.com"></input>
				</td>
			</tr>
		</table>

		<input type="submit" value="Save Changes" class="button button-primary"/>
		-->
	</form>
</div>