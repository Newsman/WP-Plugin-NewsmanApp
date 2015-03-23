<?php 

if( $_POST['newsman_submit'] == 'Y' ){
	// process form submission
	$confirm = ( isset( $_POST['newsman_widget_confirm'] ) && !empty( $_POST['newsman_widget_confirm'] ) ) 	? strip_tags( trim( $_POST[ 'newsman_widget_confirm' ] ) ) 	: get_option( $confirm );
	$infirm  = ( isset( $_POST['newsman_widget_infirm'] ) && !empty( $_POST['newsman_widget_infirm'] ) ) 	? strip_tags( trim( $_POST[ 'newsman_widget_infirm' ] ) ) 	: get_option( $infirm );
	$title 	= ( isset( $_POST['newsman_widget_title'] ) && !empty( $_POST['newsman_widget_title'] ) ) 		? strip_tags( trim( $_POST[ 'newsman_widget_title' ] ) ) 	: get_option( $title );

	update_option("newsman_widget_confirm",	$confirm );
	update_option("newsman_widget_infirm",	$infirm );
	
	$message = array(
		'status' => 'updated',
		'message' => 'Options saved.'
	);

}else{	

	$confirm = get_option( 'newsman_widget_confirm' );
	$infirm = get_option( 'newsman_widget_infirm' );

}
?>

<div class="wrap">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="newsman_submit" value="Y" />
		<h2>Newsman Widget setup</h2>
		
		<div class="<?php echo $this->message['status']?>"><p><strong><?php _e( $this->message['message'] ); ?></strong></p></div>
		
		<table class="form-table">
			<tr>
				<th scope="row"> Info </th>
				<td>
					<p class="description">
					In the widget page you can set the messages to display when a visitor subscribes with a valid email address as well as an invalid email address. 
					<br>To use the widget go to Appearance > Widgets. Look for the widget called Newsman Form and drag it to any of the page containers available. You can also edit the tile here.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_widget_confirm">Confirmation Message</label>
				</th>
				<td>
					<textarea type="text" name="newsman_widget_confirm" value="<?php echo $confirm?>"><?php echo $confirm?></textarea>
					<p class="description">The message to display when a valid email is entered.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="newsman_widget_infirm">Invalid Email Message</label>
				</th>
				<td>
					<textarea type="text" name="newsman_widget_infirm" value="<?php echo $infirm?>"><?php echo $infirm?></textarea>
					<p class="description">The message to display when an invalid email  address is entered.</p>
				</td>
			</tr>
		</table>		
		<input type="submit" value="Save Changes" class="button button-primary"/>
	</form>
</div>



