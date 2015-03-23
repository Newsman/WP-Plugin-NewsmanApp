<?php 
//process form submission
if( $_POST['newsman_submit'] == 'Y' ){
	
	$userid = ( isset( $_POST['newsman_userid'] ) && !empty( $_POST['newsman_userid'] ) ) 	? strip_tags( trim( $_POST[ 'newsman_userid' ] ) ) 	: get_option( $userid );
	$apikey = ( isset( $_POST['newsman_apikey'] ) && !empty( $_POST['newsman_apikey'] ) ) 	? strip_tags( trim( $_POST[ 'newsman_apikey' ] ) ) 	: get_option( $apikey );
	$list 	= ( isset( $_POST['newsman_list'] ) && !empty( $_POST['newsman_list'] ) ) 		? strip_tags( trim( $_POST[ 'newsman_list' ] ) ) 	: get_option( $list );
	
	$this->constructClient($userid, $apikey);
	
	update_option("newsman_userid",	$this->userid );
	update_option("newsman_apikey",	$this->apikey );
	update_option("newsman_list",	$list );

	try{
		$available_lists = $this->client->list->all();	
		$this->setMessageBackend("updated", "Options saved.");
	}catch( Exception $e ){
		$this->valid_credential = false;
		$this->setMessageBackend('error', 'Invalid Credentials');
	}	
}else{	

	$list = get_option( 'newsman_list' );
	
	try{
		$available_lists = $this->client->list->all();	
	}catch( Exception $e ){
		$this->valid_credential = false;
		$this->setMessageBackend('error', 'Invalid Credentials');
	}
}
?>


<div class="wrap wrap-settings-admin-page">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="newsman_submit" value="Y" />
		<h2>API & List Settings</h2>
		
		<div class="<?php echo $this->message['status']?>"><p><strong><?php _e( $this->message['message'] ); ?></strong></p></div>
		
<?php if( !$this->valid_credentials ){?>
		<div class="error"><p><strong><?php _e( 'Invalid credentials!' ); ?></strong></p></div>
<?php }?>
		
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="newsman_apikey">API KEY</label>
				</th>
				<td>
					<input type="text" name="newsman_apikey" value="<?php echo $this->apikey?>"/>
					<p class="description">Your Newsman API KEY</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_userid">User ID</label>
				</th>
				<td>
					<input type="text" name="newsman_userid" value="<?php echo $this->userid?>"/>
					<p class="description">Your Newsman User ID</p>
				</td>
			</tr>

<?php if( isset( $available_lists ) && !empty( $available_lists ) ){ ?>
			<tr>
				<th scope="row">
					<label for="newsman_list">Select a list</label>
				</th>
				<td>
					<select name="newsman_list" id="">
						<option value="0">-- select list --</option>
<?php foreach( $available_lists as $l){ ?>
						<option value="<?php echo $l['list_id']?>" <?php echo $l['list_id'] == $list ? "selected = ''" : "";?>><?php echo $l['list_name'] ?></option>
<?php }?>		
					</select>
					<p class="description">Select a list of subscribers</p>
				</td>
			</tr>
<?php }?>
		</table>		
		<input type="submit" value="Save Changes" class="button button-primary"/>
	</form>
</div>