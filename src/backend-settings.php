	<?php

	if (!empty($_POST['newsman_submit']) && $_POST['newsman_submit'] == 'Y')
	{		
		$userid = (isset($_POST['newsman_userid']) && !empty($_POST['newsman_userid'])) ? strip_tags(trim($_POST['newsman_userid'])) : "";
		$apikey = (isset($_POST['newsman_apikey']) && !empty($_POST['newsman_apikey'])) ? strip_tags(trim($_POST['newsman_apikey'])) : "";	
		$allowAPI = (isset($_POST['newsman_api']) && !empty($_POST['newsman_api'])) ? strip_tags(trim($_POST['newsman_api'])) : "";
		$checkoutNewsletter = (isset($_POST['newsman_checkoutnewsletter']) && !empty($_POST['newsman_checkoutnewsletter'])) ? strip_tags(trim($_POST['newsman_checkoutnewsletter'])) : "";
		$checkoutNewsletterType = (isset($_POST['newsman_checkoutnewslettertype']) && !empty($_POST['newsman_checkoutnewslettertype'])) ? strip_tags(trim($_POST['newsman_checkoutnewslettertype'])) : "";

		$this->constructClient($userid, $apikey);

		update_option("newsman_userid", $this->userid);
		update_option("newsman_apikey", $this->apikey);
		update_option("newsman_api", $allowAPI);
		update_option("newsman_checkoutnewsletter", $checkoutNewsletter);
		update_option("newsman_checkoutnewslettertype", $checkoutNewsletterType);				

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
		$checkoutNewsletter = get_option('newsman_checkoutnewsletter');
		$checkoutNewsletterType = get_option('newsman_checkoutnewslettertype');

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

	<style>	
	.newsmanTable{
		border: 1px solid #c7c7c7;		
	}

	.newsmanTable th{
		padding: 20px 20px 20px 20px;
	}

	.nVariable{
		background: rgba(0,0,0,0.8);
		color: #fff;
		padding: 5px;
		margin: 5px;
	}
	</style>

	<div class="wrap wrap-settings-admin-page">
		<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="newsman_submit" value="Y"/>

			<div class="<?php echo $this->message['status'] ?>"><p><strong><?php _e($this->message['message']); ?></strong>
				</p></div>			
			</table>

			<h2>Newsman Connection</h2>
			<table class="form-table newsmanTable">
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
				<table class="form-table newsmanTable">

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
							<label for="newsman_checkoutnewslettertype">Checkout newsletter subscribe checkbox event type</label>
						</th>
						<td>

						<select name="newsman_checkoutnewslettertype" id="">					
								<option value="save" <?php echo $checkoutNewsletterType == "save" ? "selected = ''" : ""; ?>>Subscribes a subscriber to the list</option>
								<option value="init" <?php echo $checkoutNewsletterType == "init" ? "selected = ''" : ""; ?>>Inits a confirmed opt in subscribe to the list</option>
							</select>														
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
