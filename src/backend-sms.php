<?php

$this->isOauth();

if(!empty($_POST["newsman_action"]) && $_POST["newsman_action"] == "newsman_smsdevbtn")
{
	$newsman_smsdevtest = '4' . (isset($_POST['newsman_smsdevtest']) && !empty($_POST['newsman_smsdevtest'])) ? strip_tags(trim($_POST['newsman_smsdevtest'])) : "";
	$newsman_smsdevtestmsg = (isset($_POST['newsman_smsdevtestmsg']) && !empty($_POST['newsman_smsdevtestmsg'])) ? strip_tags(trim($_POST['newsman_smsdevtestmsg'])) : "";

	$newsman_smslist = get_option('newsman_smslist');

	try{
		if(!empty($newsman_smsdevtest) && !empty($newsman_smsdevtestmsg) &&  !empty($newsman_smslist))
		{                                                                                                
			$this->client->sms->sendone($newsman_smslist, $newsman_smsdevtestmsg, $newsman_smsdevtest);                    
		}   

		$this->setMessageBackend('updated', "Test SMS was sent");
	}
	catch(Exception $e)
	{
		$this->setMessageBackend('error', "SMS did not send");
		error_log($e->getMessage());
	}
}

if (!empty($_POST['newsman_sms']))
{	
	$newsman_usesms = (isset($_POST['newsman_usesms']) && !empty($_POST['newsman_usesms'])) ? strip_tags(trim($_POST['newsman_usesms'])) : "";
	$newsman_smstest = (isset($_POST['newsman_smstest']) && !empty($_POST['newsman_smstest'])) ? strip_tags(trim($_POST['newsman_smstest'])) : "";
	$newsman_smstestnr = (isset($_POST['newsman_smstestnr']) && !empty($_POST['newsman_smstestnr'])) ? strip_tags(trim($_POST['newsman_smstestnr'])) : "";
	$newsman_smslist = (isset($_POST['newsman_smslist']) && !empty($_POST['newsman_smslist'])) ? strip_tags(trim($_POST['newsman_smslist'])) : "";

	$newsman_smspendingactivate = (isset($_POST['newsman_smspendingactivate']) && !empty($_POST['newsman_smspendingactivate'])) ? strip_tags(trim($_POST['newsman_smspendingactivate'])) : "";
	$newsman_smspendingtext = (isset($_POST['newsman_smspendingtext']) && !empty($_POST['newsman_smspendingtext'])) ? strip_tags(trim($_POST['newsman_smspendingtext'])) : "";
	$newsman_smsfailedactivate = (isset($_POST['newsman_smsfailedactivate']) && !empty($_POST['newsman_smsfailedactivate'])) ? strip_tags(trim($_POST['newsman_smsfailedactivate'])) : "";
	$newsman_smsfailedtext = (isset($_POST['newsman_smsfailedtext']) && !empty($_POST['newsman_smsfailedtext'])) ? strip_tags(trim($_POST['newsman_smsfailedtext'])) : "";
	$newsman_smsonholdactivate = (isset($_POST['newsman_smsonholdactivate']) && !empty($_POST['newsman_smsonholdactivate'])) ? strip_tags(trim($_POST['newsman_smsonholdactivate'])) : "";
	$newsman_smsonholdtext = (isset($_POST['newsman_smsonholdtext']) && !empty($_POST['newsman_smsonholdtext'])) ? strip_tags(trim($_POST['newsman_smsonholdtext'])) : "";
	$newsman_smsprocessingactivate = (isset($_POST['newsman_smsprocessingactivate']) && !empty($_POST['newsman_smsprocessingactivate'])) ? strip_tags(trim($_POST['newsman_smsprocessingactivate'])) : "";
	$newsman_smsprocessingtext = (isset($_POST['newsman_smsprocessingtext']) && !empty($_POST['newsman_smsprocessingtext'])) ? strip_tags(trim($_POST['newsman_smsprocessingtext'])) : "";
	$newsman_smscompletedactivate = (isset($_POST['newsman_smscompletedactivate']) && !empty($_POST['newsman_smscompletedactivate'])) ? strip_tags(trim($_POST['newsman_smscompletedactivate'])) : "";
	$newsman_smscompletedtext = (isset($_POST['newsman_smscompletedtext']) && !empty($_POST['newsman_smscompletedtext'])) ? strip_tags(trim($_POST['newsman_smscompletedtext'])) : "";
	$newsman_smsrefundedactivate = (isset($_POST['newsman_smsrefundedactivate']) && !empty($_POST['newsman_smsrefundedactivate'])) ? strip_tags(trim($_POST['newsman_smsrefundedactivate'])) : "";
	$newsman_smsrefundedtext = (isset($_POST['newsman_smsrefundedtext']) && !empty($_POST['newsman_smsrefundedtext'])) ? strip_tags(trim($_POST['newsman_smsrefundedtext'])) : "";
	$newsman_smscancelledactivate = (isset($_POST['newsman_smscancelledactivate']) && !empty($_POST['newsman_smscancelledactivate'])) ? strip_tags(trim($_POST['newsman_smscancelledactivate'])) : "";
	$newsman_smscancelledtext = (isset($_POST['newsman_smscancelledtext']) && !empty($_POST['newsman_smscancelledtext'])) ? strip_tags(trim($_POST['newsman_smscancelledtext'])) : "";

	$this->constructClient($this->userid, $this->apikey);
	
	update_option("newsman_usesms", $newsman_usesms);
	update_option("newsman_smstest", $newsman_smstest);
	update_option("newsman_smstestnr", $newsman_smstestnr);
	update_option("newsman_smslist", $newsman_smslist);

	update_option("newsman_smspendingactivate", $newsman_smspendingactivate);
	update_option("newsman_smspendingtext", $newsman_smspendingtext);
	update_option("newsman_smsfailedactivate", $newsman_smsfailedactivate);
	update_option("newsman_smsfailedtext", $newsman_smsfailedtext);
	update_option("newsman_smsonholdactivate", $newsman_smsonholdactivate);
	update_option("newsman_smsonholdtext", $newsman_smsonholdtext);
	update_option("newsman_smsprocessingactivate", $newsman_smsprocessingactivate);
	update_option("newsman_smsprocessingtext", $newsman_smsprocessingtext);
	update_option("newsman_smscompletedactivate", $newsman_smscompletedactivate);
	update_option("newsman_smscompletedtext", $newsman_smscompletedtext);
	update_option("newsman_smsrefundedactivate", $newsman_smsrefundedactivate);
	update_option("newsman_smsrefundedtext", $newsman_smsrefundedtext);
	update_option("newsman_smscancelledactivate", $newsman_smscancelledactivate);
	update_option("newsman_smscancelledtext", $newsman_smscancelledtext);

	try
	{		
		$available_smslists = $this->client->sms->lists();
		
		$this->setMessageBackend("updated", "Options saved.");
	} catch (Exception $e)
	{
		$this->valid_credential = false;
		$this->setMessageBackend('error', 'Invalid Credentials or no SMS list present');
	}
} else
{	
	$newsman_usesms = get_option('newsman_usesms');
	$newsman_smstest = get_option('newsman_smstest');
	$newsman_smstestnr = get_option('newsman_smstestnr');
	$newsman_smslist = get_option('newsman_smslist');

	$newsman_smspendingactivate = get_option('newsman_smspendingactivate');
	$newsman_smspendingtext = get_option('newsman_smspendingtext');
	$newsman_smsfailedactivate = get_option('newsman_smsfailedactivate');
	$newsman_smsfailedtext = get_option('newsman_smsfailedtext');
	$newsman_smsonholdactivate = get_option('newsman_smsonholdactivate');
	$newsman_smsonholdtext = get_option('newsman_smsonholdtext');
	$newsman_smsprocessingactivate = get_option('newsman_smsprocessingactivate');
	$newsman_smsprocessingtext = get_option('newsman_smsprocessingtext');
	$newsman_smscompletedactivate = get_option('newsman_smscompletedactivate');
	$newsman_smscompletedtext = get_option('newsman_smscompletedtext');
	$newsman_smsrefundedactivate = get_option('newsman_smsrefundedactivate');
	$newsman_smsrefundedtext = get_option('newsman_smsrefundedtext');
	$newsman_smscancelledactivate = get_option('newsman_smscancelledactivate');
	$newsman_smscancelledtext = get_option('newsman_smscancelledtext');

	try
	{	
		$available_smslists = $this->client->sms->lists();		

	} catch (Exception $e)
	{
		$this->valid_credential = false;
		$this->setMessageBackend('error', 'Invalid Credentials or no SMS list present');
	}
}

?>

<script>
	jQuery(document).ready(function()
	{
		jQuery('.newsman_smspendingdescription .nVariable').on('click', function(){
			jQuery('#newsman_smspendingtext').append(jQuery(this).html());
		});	
		jQuery('.newsman_smsfaileddescription .nVariable').on('click', function(){
			jQuery('#newsman_smsfailedtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smsonholddescription .nVariable').on('click', function(){
			jQuery('#newsman_smsonholdtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smsprocessingdescription .nVariable').on('click', function(){
			jQuery('#newsman_smsprocessingtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smscompleteddescription .nVariable').on('click', function(){
			jQuery('#newsman_smscompletedtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smsrefundeddescription .nVariable').on('click', function(){
			jQuery('#newsman_smsrefundedtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smscancelleddescription .nVariable').on('click', function(){
			jQuery('#newsman_smscancelledtext').append(jQuery(this).html());
		});
	})	
</script>

<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" />
	</a>
</div>
<div class="tabset">

	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="tabSms" aria-controls="" checked>
	<label for="tabSms" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>
   
  <div class="tab-panels">
    <section id="tabSms" class="tab-panel">

	<div class="wrap wrap-settings-admin-page">
	<form method="post" enctype="multipart/form-data" id="mainForm">
		<input type="hidden" name="newsman_action" value=""/>

			<h2>SMS</h2>

			<div class="<?php echo (is_array($this->message) && array_key_exists("status", $this->message)) ? $this->message["status"] : ""; ?>"><p><strong><?php echo (is_array($this->message) && array_key_exists("message", $this->message)) ? $this->message["message"] : ""; ?></strong>
				</p></div>		
			
			<table class="form-table newsmanTable">
				<tr>
					<th scope="row">
						<label for="newsman_usesms">Use SMS</label>
					</th>
					<td>

					<input name="newsman_usesms" type="checkbox" id="newsman_usesms" <?php echo (!empty($newsman_usesms) && $newsman_usesms == "on") ? "checked" : ""; ?>/>																
					</td>
				</tr>			
				<tr>
					<th scope="row">
						<label for="newsman_smslist">Select SMS List</label>
					</th>
					<td>

					<?php if($newsman_usesms == "on" && !empty($available_smslists)) { ?>
						<select name="newsman_smslist" id="">					
							<option value="0">-- select list --</option>						
								<?php foreach ($available_smslists as $l)
								{ ?>
									<option
										value="<?php echo $l['list_id'] ?>" <?php echo $l['list_id'] == $newsman_smslist ? "selected = ''" : ""; ?>><?php echo $l['list_name']; ?></option>
								<?php } ?>
						</select>														
					<?php } ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="newsman_smstext">SMS Message</label>						
					</th>
					<th scope="row">
						<label for="newsman_smstext">Order Status</label>						
					</th>
					<th scope="row">
						<label for="newsman_smstext">Message / Variables</label>						
					</th>			
				</tr>
				<tr>
					<td>
					</td>	
					<td>		
					<label>Pending</label>
					|
					<label for="newsman_smspendingactivate">Activate</label>				
					<input name="newsman_smspendingactivate" type="checkbox" id="newsman_smspendingactivate" <?php echo (!empty($newsman_smspendingactivate) && $newsman_smspendingactivate == "on") ? "checked" : ""; ?>/>		

					</td>
					<td class="newsman_smspendingtextPanel" <?php echo (empty($newsman_smspendingactivate) || $newsman_smspendingactivate == "off") ? 'style="display: none;"' : ""; ?>>
						<textarea id="newsman_smspendingtext" name="newsman_smspendingtext" style="width: 100%; min-height: 100px;"><?php echo (!empty($newsman_smspendingtext)) ? $newsman_smspendingtext : "Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com"; ?></textarea>
						
						<p class="newsman_smspendingdescription" style="padding: 5px;">Variables: <span class="nVariable">{{billing_first_name}}</span><span class="nVariable">{{billing_last_name}}</span><span class="nVariable">{{shipping_first_name}}</span><span class="nVariable">{{shipping_last_name}}</span><span class="nVariable">{{order_number}}</span><span class="nVariable">{{order_date}}</span><span class="nVariable">{{order_total}}</span><span class="nVariable">{{email}}</span></p>
					</td>		
				</tr>
				<tr>
					<td>
					</td>	
					<td>		
					<label>Failed</label>
					|
					<label for="newsman_smsfailedactivate">Activate</label>				
					<input name="newsman_smsfailedactivate" type="checkbox" id="newsman_smsfailedactivate" <?php echo (!empty($newsman_smsfailedactivate) && $newsman_smsfailedactivate == "on") ? "checked" : ""; ?>/>		

					</td>
					<td class="newsman_smsfailedtextPanel" <?php echo (empty($newsman_smsfailedactivate) || $newsman_smsfailedactivate == "off") ? 'style="display: none;"' : ""; ?>>
						<textarea id="newsman_smsfailedtext" name="newsman_smsfailedtext" style="width: 100%; min-height: 100px;"><?php echo (!empty($newsman_smsfailedtext)) ? $newsman_smsfailedtext : "Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com"; ?></textarea>
						<p class="newsman_smsfaileddescription" style="padding: 5px;">Variables: <span class="nVariable">{{billing_first_name}}</span><span class="nVariable">{{billing_last_name}}</span><span class="nVariable">{{shipping_first_name}}</span><span class="nVariable">{{shipping_last_name}}</span><span class="nVariable">{{order_number}}</span><span class="nVariable">{{order_date}}</span><span class="nVariable">{{order_total}}</span><span class="nVariable">{{email}}</span></p>
					</td>			
				</tr>
				<tr>
					<td>
					</td>	
					<td>		
					<label>On Hold</label>
					|
					<label for="newsman_smsonholdactivate">Activate</label>				
					<input name="newsman_smsonholdactivate" type="checkbox" id="newsman_smsonholdactivate" <?php echo (!empty($newsman_smsonholdactivate) && $newsman_smsonholdactivate == "on") ? "checked" : ""; ?>/>		
					</td>
					<td class="newsman_smsonholdtextPanel" <?php echo (empty($newsman_smsonholdactivate) || $newsman_smsonholdactivate == "off") ? 'style="display: none;"' : ""; ?>>
						<textarea id="newsman_smsonholdtext" name="newsman_smsonholdtext" style="width: 100%; min-height: 100px;"><?php echo (!empty($newsman_smsonholdtext)) ? $newsman_smsonholdtext : "Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com"; ?></textarea>
						<p class="newsman_smsonholddescription" style="padding: 5px;">Variables: <span class="nVariable">{{billing_first_name}}</span><span class="nVariable">{{billing_last_name}}</span><span class="nVariable">{{shipping_first_name}}</span><span class="nVariable">{{shipping_last_name}}</span><span class="nVariable">{{order_number}}</span><span class="nVariable">{{order_date}}</span><span class="nVariable">{{order_total}}</span><span class="nVariable">{{email}}</span></p>
					</td>		
				</tr>
				<tr>
					<td>
					</td>	
					<td>		
					<label>Processing</label>
					|
					<label for="newsman_smsprocessingactivate">Activate</label>				
					<input name="newsman_smsprocessingactivate" type="checkbox" id="newsman_smsprocessingactivate" <?php echo (!empty($newsman_smsprocessingactivate) && $newsman_smsprocessingactivate == "on") ? "checked" : ""; ?>/>		

					</td>
					<td class="newsman_smsprocessingtextPanel" <?php echo (empty($newsman_smsprocessingactivate) || $newsman_smsprocessingactivate == "off") ? 'style="display: none;"' : ""; ?>>
						<textarea id="newsman_smsprocessingtext" name="newsman_smsprocessingtext" style="width: 100%; min-height: 100px;"><?php echo (!empty($newsman_smsprocessingtext)) ? $newsman_smsprocessingtext : "Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com"; ?></textarea>
						<p class="newsman_smsprocessingdescription" style="padding: 5px;">Variables: <span class="nVariable">{{billing_first_name}}</span><span class="nVariable">{{billing_last_name}}</span><span class="nVariable">{{shipping_first_name}}</span><span class="nVariable">{{shipping_last_name}}</span><span class="nVariable">{{order_number}}</span><span class="nVariable">{{order_date}}</span><span class="nVariable">{{order_total}}</span><span class="nVariable">{{email}}</span></p>
					</td>	
				</tr>
				<tr>
					<td>
					</td>	
					<td>		
					<label>Completed</label>
					|
					<label for="newsman_smscompletedactivate">Activate</label>				
					<input name="newsman_smscompletedactivate" type="checkbox" id="newsman_smscompletedactivate" <?php echo (!empty($newsman_smscompletedactivate) && $newsman_smscompletedactivate == "on") ? "checked" : ""; ?>/>		

					</td>
					<td class="newsman_smscompletedtextPanel" <?php echo (empty($newsman_smscompletedactivate) || $newsman_smscompletedactivate == "off") ? 'style="display: none;"' : ""; ?>>
						<textarea id="newsman_smscompletedtext" name="newsman_smscompletedtext" style="width: 100%; min-height: 100px;"><?php echo (!empty($newsman_smscompletedtext)) ? $newsman_smscompletedtext : "Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com"; ?></textarea>
						<p class="newsman_smscompleteddescription" style="padding: 5px;">Variables: <span class="nVariable">{{billing_first_name}}</span><span class="nVariable">{{billing_last_name}}</span><span class="nVariable">{{shipping_first_name}}</span><span class="nVariable">{{shipping_last_name}}</span><span class="nVariable">{{order_number}}</span><span class="nVariable">{{order_date}}</span><span class="nVariable">{{order_total}}</span><span class="nVariable">{{email}}</span></p>
					</td>		
				</tr>
				<tr>
					<td>
					</td>	
					<td>		
					<label>Refunded</label>
					|
					<label for="newsman_smsrefundedactivate">Activate</label>				
					<input name="newsman_smsrefundedactivate" type="checkbox" id="newsman_smsrefundedactivate" <?php echo (!empty($newsman_smsrefundedactivate) && $newsman_smsrefundedactivate == "on") ? "checked" : ""; ?>/>		

					</td>
					<td class="newsman_smsrefundedtextPanel" <?php echo (empty($newsman_smsrefundedactivate) || $newsman_smsrefundedactivate == "off") ? 'style="display: none;"' : ""; ?>>
						<textarea id="newsman_smsrefundedtext" name="newsman_smsrefundedtext" style="width: 100%; min-height: 100px;"><?php echo (!empty($newsman_smsrefundedtext)) ? $newsman_smsrefundedtext : "Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com"; ?></textarea>
						<p class="newsman_smsrefundeddescription" style="padding: 5px;">Variables: <span class="nVariable">{{billing_first_name}}</span><span class="nVariable">{{billing_last_name}}</span><span class="nVariable">{{shipping_first_name}}</span><span class="nVariable">{{shipping_last_name}}</span><span class="nVariable">{{order_number}}</span><span class="nVariable">{{order_date}}</span><span class="nVariable">{{order_total}}</span><span class="nVariable">{{email}}</span></p>
					</td>
				</tr>
				<tr>
					<td>
					</td>	
					<td>		
					<label>Cancelled</label>
					|
					<label for="newsman_smscancelledactivate">Activate</label>				
					<input name="newsman_smscancelledactivate" type="checkbox" id="newsman_smscancelledactivate" <?php echo (!empty($newsman_smscancelledactivate) && $newsman_smscancelledactivate == "on") ? "checked" : ""; ?>/>		

					</td>
					<td class="newsman_smscancelledtextPanel" <?php echo (empty($newsman_smscancelledactivate) || $newsman_smscancelledactivate == "off") ? 'style="display: none;"' : ""; ?>>
						<textarea id="newsman_smscancelledtext" name="newsman_smscancelledtext" style="width: 100%; min-height: 100px;"><?php echo (!empty($newsman_smscancelledtext)) ? $newsman_smscancelledtext : "Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com"; ?></textarea>
						<p class="newsman_smscancelleddescription" style="padding: 5px;">Variables: <span class="nVariable">{{billing_first_name}}</span><span class="nVariable">{{billing_last_name}}</span><span class="nVariable">{{shipping_first_name}}</span><span class="nVariable">{{shipping_last_name}}</span><span class="nVariable">{{order_number}}</span><span class="nVariable">{{order_date}}</span><span class="nVariable">{{order_total}}</span><span class="nVariable">{{email}}</span></p>
					</td>
				</tr>
				<!--
				<tr>
					<td>					
					</td>
					<td>						
					</td>
					<td>
						<h4>Example message: Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com<h4>
					</td>
				</tr>
				-->
			</table>

			<h2>SMS production debug</h2>

			<table class="form-table newsmanTable newsmanTblFixed">		
			<tr>
				<th scope="">
					<label for="newsman_smstest">Activate test mode</label>
					<p class="newsmanP">if checked, when an order status changes, the message will be sent on your specified phone, not client phone</p>
				</th>
				<td>

				<input name="newsman_smstest" type="checkbox" id="newsman_smstest" <?php echo (!empty($newsman_smstest) && $newsman_smstest == "on") ? "checked" : ""; ?>/>																
				</td>
			</tr>
			<tr>
				<th scope="">
					<label for="newsman_smstestnr">Phone for tests</label>						
				</th>
				<td>				
				<input id="newsman_smstestnr" name="newsman_smstestnr" value="<?php echo $newsman_smstestnr; ?>" /> Ex: 0720998111

				</td>
			</tr>
			</table>

			<h2>SMS send test</h2>			

			<table class="form-table newsmanTable newsmanTblFixed">								
				<tr>
					<th scope="row">
						<label for="newsman_smsdevtest">Phone</label>						
					</th>
					<td>
					
					<input id="newsman_smsdevtest" name="newsman_smsdevtest" value="<?php echo ''; ?>" /> Ex: 0720998111

					</td>
				</tr>		
				<tr>
					<th scope="row">
						<label for="newsman_smsdevtestmsg">Test message</label>						
					</th>					
					<td>
						<textarea id="newsman_smsdevtestmsg" name="newsman_smsdevtestmsg" style="width: 100%; min-height: 100px;"><?php echo ''; ?></textarea>
					</td>
					
				</tr>			
				<tr>
					<th scope="row">
						<label for="newsman_smsdevtestbtn">Send now</label>						
					</th>		
					<td class="msg_smsdevbtn">
						<input type="button" value="Send Now" name="newsman_smsdevbtn" class="button button-primary"/>
					</td>
				</tr>

			</table>
		
			<th>
			</th>

		</table>		
		
		<div style="padding-top: 5px;">
			<input type="submit" name="newsman_sms" value="Save Changes" class="button button-primary"/>
		</div>

	</form>
</div>


  	</section>  
  </div>  
</div>