<?php
//process form submission
if ($_POST['newsman_submit'] == 'Y')
{
	$userid = get_option("newsman_userid");
	$apikey = get_option("newsman_apikey");
	$list = get_option("newsman_list");

	$this->constructClient($userid, $apikey);
	if (isset($_POST['mailpoet_subscribeBtn']))
	{
		try
		{
			$available_lists = $this->client->list->all();
			$this->importMailPoetSubscribers($list);
		} catch (Exception $e)
		{
			$this->valid_credential = false;
			$this->setMessageBackend('error', 'Invalid Credentials');
		}
	} else
	{
		try
		{
			$available_lists = $this->client->list->all();
		} catch (Exception $e)
		{
			$this->valid_credential = false;
			$this->setMessageBackend('error', 'Invalid Credentials');
		}

		$this->importWPSubscribers($list);
	}

} else
{
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

<div class="wrap">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="newsman_submit" value="Y"/>
		<h2>Newsman import subscribers</h2>
		<?php if (isset($list))
		{ ?>
		<table class="form-table">
			<tr scope="row">
				<?php if (isset($_POST['newsman_subscribeBtn'])) : ?>
					<?php if ($_POST['newsman_submit'] == 'Y'): ?>
						<?php if ($this->wpSync): ?>
							<th>
								<div id="nws-message" class="updated"><p><strong>Imported Successfully</strong></p>
								</div>
							</th>
						<?php else: ?>
							<th>
								<div id="nws-message" class="error"><p><strong>Import couldn't be done
											<?php
											$arr = $this->getBackendMessage();
											foreach ($arr as $array => $s)
												echo $s;

											?></strong></p></div>

							</th>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</tr>
			<tr scope="row">
				<th>
					<label for="import_subscribers_from_wordpress">Clicking the button below automatically imports your
						wordpress subscribers into Newsman.</label>
				</th>
			</tr>
			<?php } ?>
		</table>
		<input type="submit" name="newsman_subscribeBtn" value="Import Subscribers" class="button button-primary"/>
		<div class="container" style="margin-top: 20px;">
			<input type="hidden" name="mailpoet_submit" value="N"/>
			<ul>
				<li style="overflow: hidden;">
					<input type="checkbox" class="form-control" id="mailpoet" value="Y"
					       style="overflow: hidden; margin-top: 5px;">
					<h4 style="float: left;">MailPoet plugin installed ?&nbsp;</h4></input>
				</li>

				<li id="submitMailPoet" style="display: none;">
					<div>
						<label for="import_subscribers_from_wordpress">Clicking the button below automatically
							imports
							your mailpoet subscribers into Newsman.</label>
					</div>
					<?php if (isset($_POST['mailpoet_subscribeBtn']))
					{ ?>
						<?php if ($this->mailpoetSync): ?>
						<table class="form-table">
						<tr scope="row">
						<th>
							<div id="nws-message" class="updated"><p><strong>Imported Successfully from MailPoet
										plugin</strong></p>
							</div>
						</th>
					<?php else: ?>
						<th>
							<div id="nws-message" class="error"><p><strong>Import couldn't be done from MailPoet
										plugin
										<?php

										$arr = $this->getBackendMessage();
										foreach ($arr as $array => $s)
											echo $s;

										?></strong></p></div>

						</th>
						</tr>
						</table>
					<?php endif; ?>
					<?php } ?>
					<input type="submit" name="mailpoet_subscribeBtn" value="Import MailPoet Subscribers"
					       class="button button-primary"/>
				</li>
			</ul>
		</div>
	</form>
</div>