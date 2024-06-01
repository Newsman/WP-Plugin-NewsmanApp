<?php

$this->isOauth(true);

$oauthUrl = "https://newsman.app/admin/oauth/authorize?response_type=code&client_id=nzmplugin&nzmplugin=Wordpress&scope=api&redirect_uri=https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

$error = "";
$dataLists = array();
$step = 1;
$viewState = array();

if(!empty($_GET["error"])){
    switch($error){
        case "access_denied":
            $error = "Access is denied";
            break;
        case "missing_lists":
            $error = "There are no lists in your NewsMAN account";
            break;
    }
}else if(!empty($_GET["code"])){

    $authUrl = "https://newsman.app/admin/oauth/token";

    $code = $_GET["code"];

    $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    $data = array(
        "grant_type" => "authorization_code",
        "code" => $code,
        "client_id" => "nzmplugin",
        "redirect_uri" => $redirect
    );
    
    $ch = curl_init($authUrl);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error .= 'cURL error: ' . curl_error($ch);
    }
    
    curl_close($ch);
    
    if ($response !== false) {

		$response = json_decode($response);

		$viewState["creds"] = json_encode(array(
			"newsman_userid" => $response->user_id,
			"newsman_apikey" => $response->access_token
			)
		);

		foreach($response->lists_data as $list => $l){
			$dataLists[] = array(
				"id" => $l->list_id,
				"name" => $l->name
			);
		}	

		$step = 2;
    } else {
        $error .= "Error sending cURL request.";
    }  
}

if(!empty($_POST["oauthstep2"]) && $_POST['oauthstep2'] == 'Y')
{
	if(empty($_POST["newsman_list"]) || $_POST["newsman_list"] == 0)
	{
		$step = 1;
	}
	else
	{
		$creds = stripslashes($_POST["creds"]);
		$creds = json_decode($creds);
		$creds = json_decode($creds);

		$this->constructClient($creds->newsman_userid, $creds->newsman_apikey);
		$ret = $this->client->remarketing->getSettings($_POST["newsman_list"]);

		$remarketingId = $ret["site_id"] . "-" . $ret["list_id"] . "-" . $ret["form_id"] . "-" . $ret["control_list_hash"];

		//set feed
		$url = get_site_url() . "/?newsman=products.json&nzmhash=" . $creds->newsman_apikey;					

		try{
			if (class_exists('WooCommerce')) {
				$ret = $this->client->feeds->setFeedOnList($_POST["newsman_list"], $url, get_site_url(), "NewsMAN");
			}	
		}
		catch(Exception $ex)
		{			
			//the feed already exists
		}

		update_option("newsman_userid", $creds->newsman_userid);
		update_option("newsman_apikey", $creds->newsman_apikey);
		update_option("newsman_list", $_POST["newsman_list"]);
		update_option("newsman_remarketingid", $remarketingId);

		$this->isOauth(true);
	}
}

?>

<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" />
	</a>
</div>
<div class="tabset">
   
  <div class="tab-panels">
    <section id="tabOauth" class="tab-panel">

    <?php if(!empty($error)) { ?><div class="error"><p><strong><?php echo (!empty($error)) ? $error : "" ?></strong>
				</p></div><?php } ?>	
      
		<div class="wrap wrap-settings-admin-page">
			<h2>NewsMAN plugin for Wordpress-Woocommerce</h2>

			<!--oauth step-->
			<?php if($step == 1) { ?>
			<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="newsman_oauth" value="Y"/>
			<table class="form-table newsmanTable newsmanTblFixed newsmanOauth">
				<tr>
					<td>
						<p class="description"><b>Connect your site with NewsMAN for:</b></p>
					</td>
				</tr>
                <tr>
					<td>
						<p class="description">- Subscribers Sync</p>
					</td>
				</tr>
                <tr>
					<td>
						<p class="description">- Ecommerce Remarketing</p>
					</td>
				</tr>
                <tr>
					<td>
						<p class="description">- Create and manage forms</p>
					</td>
				</tr>
                <tr>
					<td>
						<p class="description">- Create and manage popups</p>
					</td>
				</tr>
                <tr>
					<td>
						<p class="description">- Connect your forms to automation</p>
					</td>
				</tr>
			</table>	
			
			<div style="padding-top: 5px;">
				<a style="background: #ad0100" href="<?php echo $oauthUrl; ?>" class="button button-primary">Login with NewsMAN</a>
			</div>
			</form>

			<!--List step-->
			<?php } else if($step == 2) { ?>

			<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="oauthstep2" value="Y"/>
			<input type="hidden" name="creds" value='<?php echo json_encode($viewState["creds"]); ?>' />
			<table class="form-table newsmanTable newsmanTblFixed newsmanOauth">
			<tr>
				<td>
					<select name="newsman_list" id="">
						<option value="0">-- select list --</option>
						<?php foreach ($dataLists as $l)
						{ ?>
							<option
								value="<?php echo $l['id'] ?>"><?php echo $l['name']; ?></option>
						<?php } ?>
					</select>
				</td>
			</tr>
			</table>	
			
			<div style="padding-top: 5px;">
				<button type="submit" style="background: #ad0100" class="button button-primary">Save</a>
			</div>
			</form>

			<?php } ?>

		</div>

  	</section>  
  </div>  
</div>
