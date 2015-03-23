<?php 
//process form submission
if( $_POST['newsman_submit'] == 'Y' ){
	
	$userid = get_option( "newsman_userid" );
	$apikey = get_option( "newsman_apikey" );
	$list 	= get_option( "newsman_list" );
	
	$this->constructClient($userid, $apikey);

	try{
		$available_lists = $this->client->list->all();	
	}catch( Exception $e ){
		$this->valid_credential = false;
		$this->setMessageBackend('error', 'Invalid Credentials');
	}
		
	$this->importWPSubscribers($list);
	
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


<div class="wrap">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="newsman_submit" value="Y" />
		<h2>Newsman import subscribers</h2>
<?php if( isset( $list ) ){?>
		<table class="form-table">
			<tr scope="row">
				<th>
					<label for="import_subscribers_from_wordpress">Clicking the button below automatically imports your wordpress subscribers into Newsman.</label>
				</th>
			</tr>
<?php }?>
		</table>		
		<input type="submit" value="Import Subscribers" class="button button-primary"/>
	</form>
</div>