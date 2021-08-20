<?php

if (!empty($_POST['newsman_remarketing']) && $_POST['newsman_remarketing'] == 'Y')
{
	$remarketingid = (isset($_POST['newsman_remarketingid']) && !empty($_POST['newsman_remarketingid'])) ? strip_tags(trim($_POST['newsman_remarketingid'])) : "";

	update_option("newsman_remarketingid", $remarketingid);

	try
	{
		$available_lists = $this->client->list->all();
		
		$this->setMessageBackend("updated", "Options saved.");
	} catch (Exception $e)
	{
		$this->valid_credential = false;
		$this->setMessageBackend('error', 'Invalid Credentials');
	}
} else
{
	$remarketingid = get_option('newsman_remarketingid');

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

<style>	
.newsmanTable{
	border: 1px solid #c7c7c7;		
}

.newsmanTable th{
	padding: 20px 20px 20px 20px;
}
</style>

<div class="wrap wrap-settings-admin-page">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="newsman_remarketing" value="Y"/>
		<h2>Remarketing</h2>

		<div class="<?php echo $this->message['status'] ?>"><p><strong><?php _e($this->message['message']); ?></strong>
				</p></div>			

		<?php if (!$this->valid_credentials)
		{ ?>
			<div class="error"><p><strong><?php _e('Invalid credentials!'); ?></strong></p></div>
		<?php } ?>

		<table class="form-table newsmanTable">
			<tr>
				<th scope="row">
					<label for="newsman_remarketingid">REMARKETING ID</label>
				</th>
				<td>
					<input type="text" name="newsman_remarketingid" value="<?php echo $remarketingid; ?>"/>
					<p class="description">Your Newsman Remarketing ID</p>
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
