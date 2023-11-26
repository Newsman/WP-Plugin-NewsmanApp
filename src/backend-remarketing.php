<?php

$this->isOauth();

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

<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" />
	</a>
</div>
<div class="tabset">

<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="tabSync" aria-controls="">
	<label for="tabSync" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="tabRemarketing" aria-controls="" checked>
	<label for="tabRemarketing" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>
   
  <div class="tab-panels">
    <section id="tabRemarketing" class="tab-panel">
      
		<div class="wrap wrap-settings-admin-page">
		<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="newsman_remarketing" value="Y"/>
			<h2>Remarketing</h2>

			<div class="<?php echo (is_array($this->message) && array_key_exists("status", $this->message)) ? $this->message["status"] : ""; ?>"><p><strong><?php echo (is_array($this->message) && array_key_exists("message", $this->message)) ? $this->message["message"] : ""; ?></strong>
				</p></div>	

			<?php if (!$this->valid_credentials)
			{ ?>
				<div class="error"><p><strong><?php _e('Invalid credentials!'); ?></strong></p></div>
			<?php } ?>

			<table class="form-table newsmanTable newsmanTblFixed">
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

  	</section>  
  </div>  
</div>
