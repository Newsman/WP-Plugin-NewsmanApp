<?php
//process form submission
if ($_POST['newsman_submit'] == 'Y')
{
	$userid = (isset($_POST['newsman_userid']) && !empty($_POST['newsman_userid'])) ? strip_tags(trim($_POST['newsman_userid'])) : get_option($userid);
	$apikey = (isset($_POST['newsman_apikey']) && !empty($_POST['newsman_apikey'])) ? strip_tags(trim($_POST['newsman_apikey'])) : get_option($apikey);
	$list = (isset($_POST['newsman_list']) && !empty($_POST['newsman_list'])) ? strip_tags(trim($_POST['newsman_list'])) : get_option($list);
	$segments = (isset($_POST['newsman_segments']) && !empty($_POST['newsman_segments'])) ? strip_tags(trim($_POST['newsman_segments'])) : get_option($segments);

	$this->constructClient($userid, $apikey);

	update_option("newsman_userid", $this->userid);
	update_option("newsman_apikey", $this->apikey);
	update_option("newsman_list", $list);
	update_option("newsman_segments", $segments);

	try
	{
		$available_lists = $this->client->list->all();
		$available_segments = array();
		if(!empty($list))
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
	$list = get_option('newsman_list');
	$segments = get_option('newsman_segments');
	try
	{
		$available_lists = $this->client->list->all();
		$available_segments = array();
		if(!empty($list))
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

<div class="wrap wrap-settings-admin-page">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="newsman_submit" value="Y"/>
		<h2>API & List Settings</h2>

		<div class="<?php echo $this->message['status'] ?>"><p><strong><?php _e($this->message['message']); ?></strong>
			</p></div>

		<?php if (!$this->valid_credentials)
		{ ?>
			<div class="error"><p><strong><?php _e('Invalid credentials!'); ?></strong></p></div>
		<?php } ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="newsman_apikey">API KEY</label>
				</th>
				<td>
					<input type="text" name="newsman_apikey" value="<?php echo $this->apikey ?>"/>
					<p class="description">Your Newsman API KEY</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_userid">User ID</label>
				</th>
				<td>
					<input type="text" name="newsman_userid" value="<?php echo $this->userid ?>"/>
					<p class="description">Your Newsman User ID</p>
				</td>
			</tr>

			<?php if (isset($available_lists) && !empty($available_lists))
			{ ?>
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
									value="<?php echo $l['list_id'] ?>" <?php echo $l['list_id'] == $list ? "selected = ''" : ""; ?>><?php echo $l['list_name'] ?></option>
							<?php } ?>
						</select>
						<p class="description">Select a list of subscribers</p>
					</td>
				</tr>
			<?php } ?>

			<?php if (isset($available_segments) && !empty($available_segments))
			{ ?>
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
									value="<?php echo $l['segment_id'] ?>" <?php echo $l['segment_id'] == $segments ? "selected = ''" : ""; ?>><?php echo $l['segment_name'] ?></option>
							<?php } ?>
						</select>
						<p class="description">Select a segment</p>
					</td>
				</tr>
			<?php } ?>
		</table>
		<input type="submit" value="Save Changes" class="button button-primary"/>
	</form>
</div>