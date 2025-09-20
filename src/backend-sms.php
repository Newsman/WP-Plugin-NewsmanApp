<?php
/**
 * Title: Newsman admin SMS options
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var Newsman_Admin_Settings_Sms $this
 */

$this->is_oauth();

$nonce_action = 'newsman-settings-sms';
$test_nonce   = '';
if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
	$test_nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
}

if ( ! empty( $test_nonce ) || isset( $_POST['newsman_sms'] ) ) {
	if ( ! wp_verify_nonce( $test_nonce, $nonce_action ) ) {
		wp_nonce_ays( $nonce_action );
		return;
	}
}

$local_nonce = wp_create_nonce( $nonce_action );
wp_nonce_field( $nonce_action, '_wpnonce', false );

$local_newsman_sms = '';
if ( isset( $_POST['newsman_sms'] ) && ! empty( $_POST['newsman_sms'] ) ) {
	$local_newsman_sms = sanitize_text_field( wp_unslash( $_POST['newsman_sms'] ) );
}

$local_newsman_action = '';
if ( isset( $_POST['newsman_action'] ) && ! empty( $_POST['newsman_action'] ) ) {
	$local_newsman_action = sanitize_text_field( wp_unslash( $_POST['newsman_action'] ) );
}

$local_options = array();

if ( 'newsman_smsdevbtn' === $local_newsman_action ) {
	$newsman_smsdevtest = '';
	if ( isset( $_POST['newsman_smsdevtest'] ) && ! empty( $_POST['newsman_smsdevtest'] ) ) {
		$newsman_smsdevtest = '4' . sanitize_text_field( wp_unslash( $_POST['newsman_smsdevtest'] ) );
	}
	$newsman_smsdevtestmsg = '';
	if ( isset( $_POST['newsman_smsdevtestmsg'] ) && ! empty( $_POST['newsman_smsdevtestmsg'] ) ) {
		$newsman_smsdevtestmsg = sanitize_text_field( wp_unslash( $_POST['newsman_smsdevtestmsg'] ) );
	}

	$local_options['newsman_smslist'] = get_option( 'newsman_smslist' );

	try {
		if ( ! empty( $newsman_smsdevtest ) && ! empty( $newsman_smsdevtestmsg ) && ! empty( $local_options['newsman_smslist'] ) ) {
			$this->sms_send_one( $newsman_smsdevtestmsg, $newsman_smsdevtest, $local_options['newsman_smslist'] );
		}

		$this->set_message_backend( 'updated', 'Test SMS was sent' );
	} catch ( Exception $e ) {
		$this->set_message_backend( 'error', 'SMS did not send. ' . $e->getMessage() );
	}
}

$local_options_names = array(
	'newsman_usesms',
	'newsman_smstest',
	'newsman_smstestnr',
	'newsman_smslist',
	'newsman_smspendingactivate',
	'newsman_smspendingtext',
	'newsman_smsfailedactivate',
	'newsman_smsfailedtext',
	'newsman_smsonholdactivate',
	'newsman_smsonholdtext',
	'newsman_smsprocessingactivate',
	'newsman_smsprocessingtext',
	'newsman_smscompletedactivate',
	'newsman_smscompletedtext',
	'newsman_smsrefundedactivate',
	'newsman_smsrefundedtext',
	'newsman_smscancelledactivate',
	'newsman_smscancelledtext',
);

if ( ! empty( $_POST['newsman_sms'] ) ) {
	foreach ( $local_options_names as $local_option_name ) {
		$local_options[ $local_option_name ] = '';
		if ( isset( $_POST[ $local_option_name ] ) ) {
			$local_options[ $local_option_name ] = sanitize_text_field( wp_unslash( $_POST[ $local_option_name ] ) );
		}
	}

	foreach ( $local_options as $local_option_name => $local_option_value ) {
		update_option( $local_option_name, $local_option_value );
	}

	try {
		$available_smslists = $this->retrieve_api_sms_all_lists();

		$this->set_message_backend( 'updated', 'Options saved.' );
	} catch ( Exception $e ) {
		$this->set_message_backend( 'error', 'Invalid Credentials or no SMS list present' );
	}
} else {
	foreach ( $local_options_names as $local_option_name ) {
		$local_options[ $local_option_name ] = get_option( $local_option_name );
	}

	try {
		$available_smslists = $this->retrieve_api_sms_all_lists();

	} catch ( Exception $e ) {
		$this->set_message_backend( 'error', 'Invalid Credentials or no SMS list present' );
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
	<!--<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>-->
	<div class="tab-panels">
		<section id="tabSms" class="tab-panel">
			<div class="wrap wrap-settings-admin-page">
				<form method="post" enctype="multipart/form-data" id="mainForm">
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $local_nonce ); ?>"/>
					<input type="hidden" name="newsman_action" value=""/>
					<h2>SMS</h2>
					<div class="<?php echo ( is_array( $this->message ) && isset( $this->message['status'] ) ) ? esc_attr( $this->message['status'] ) : ''; ?>"><p><strong><?php echo ( is_array( $this->message ) && isset( $this->message['message'] ) ) ? esc_html( $this->message['message'] ) : ''; ?></strong>
						</p></div>
					<table class="form-table newsmanTable">
						<tr>
							<th scope="row">
								<label for="newsman_usesms">Use SMS</label>
							</th>
							<td>
								<input name="newsman_usesms" type="checkbox" id="newsman_usesms" <?php echo ( 'on' === $local_options['newsman_usesms'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="newsman_smslist">Select SMS List</label>
							</th>
							<td>
								<?php if ( 'on' === $local_options['newsman_usesms'] && ! empty( $available_smslists ) ) { ?>
									<select name="newsman_smslist" id="">
										<option value="0">-- select list --</option>
											<?php
											foreach ( $available_smslists as $l ) {
												?>
												<option
													value="<?php echo esc_attr( $l['list_id'] ); ?>" <?php echo ( strval( $l['list_id'] ) === strval( $local_options['newsman_smslist'] ) ) ? "selected = ''" : ''; ?>><?php echo esc_html( $l['list_name'] ); ?></option>
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
								<input name="newsman_smspendingactivate" type="checkbox" id="newsman_smspendingactivate" <?php echo ( 'on' === $local_options['newsman_smspendingactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smspendingtextPanel" <?php echo ( 'off' === $local_options['newsman_smspendingactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<textarea id="newsman_smspendingtext" name="newsman_smspendingtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $local_options['newsman_smspendingtext'] ) ) ? esc_html( $local_options['newsman_smspendingtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
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
								<input name="newsman_smsfailedactivate" type="checkbox" id="newsman_smsfailedactivate" <?php echo ( 'on' === $local_options['newsman_smsfailedactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smsfailedtextPanel" <?php echo ( 'off' === $local_options['newsman_smsfailedactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<textarea id="newsman_smsfailedtext" name="newsman_smsfailedtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $local_options['newsman_smsfailedtext'] ) ) ? esc_html( $local_options['newsman_smsfailedtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
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
								<input name="newsman_smsonholdactivate" type="checkbox" id="newsman_smsonholdactivate" <?php echo ( 'on' === $local_options['newsman_smsonholdactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smsonholdtextPanel" <?php echo ( 'off' === $local_options['newsman_smsonholdactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<textarea id="newsman_smsonholdtext" name="newsman_smsonholdtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $local_options['newsman_smsonholdtext'] ) ) ? esc_html( $local_options['newsman_smsonholdtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
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
								<input name="newsman_smsprocessingactivate" type="checkbox" id="newsman_smsprocessingactivate" <?php echo ( 'on' === $local_options['newsman_smsprocessingactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smsprocessingtextPanel" <?php echo ( 'off' === $local_options['newsman_smsprocessingactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<textarea id="newsman_smsprocessingtext" name="newsman_smsprocessingtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $local_options['newsman_smsprocessingtext'] ) ) ? esc_html( $local_options['newsman_smsprocessingtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
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
								<input name="newsman_smscompletedactivate" type="checkbox" id="newsman_smscompletedactivate" <?php echo ( 'on' === $local_options['newsman_smscompletedactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smscompletedtextPanel" <?php echo ( 'off' === $local_options['newsman_smscompletedactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<textarea id="newsman_smscompletedtext" name="newsman_smscompletedtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $local_options['newsman_smscompletedtext'] ) ) ? esc_html( $local_options['newsman_smscompletedtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
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
								<input name="newsman_smsrefundedactivate" type="checkbox" id="newsman_smsrefundedactivate" <?php echo ( 'on' === $local_options['newsman_smsrefundedactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smsrefundedtextPanel" <?php echo ( 'off' === $local_options['newsman_smsrefundedactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<textarea id="newsman_smsrefundedtext" name="newsman_smsrefundedtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $local_options['newsman_smsrefundedtext'] ) ) ? esc_html( $local_options['newsman_smsrefundedtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
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
								<input name="newsman_smscancelledactivate" type="checkbox" id="newsman_smscancelledactivate" <?php echo ( 'on' === $local_options['newsman_smscancelledactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smscancelledtextPanel" <?php echo ( 'off' === $local_options['newsman_smscancelledactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<textarea id="newsman_smscancelledtext" name="newsman_smscancelledtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $local_options['newsman_smscancelledtext'] ) ) ? esc_html( $local_options['newsman_smscancelledtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
								<p class="newsman_smscancelleddescription" style="padding: 5px;">Variables: <span class="nVariable">{{billing_first_name}}</span><span class="nVariable">{{billing_last_name}}</span><span class="nVariable">{{shipping_first_name}}</span><span class="nVariable">{{shipping_last_name}}</span><span class="nVariable">{{order_number}}</span><span class="nVariable">{{order_date}}</span><span class="nVariable">{{order_total}}</span><span class="nVariable">{{email}}</span></p>
							</td>
						</tr>
					</table>
					<h2>SMS production debug</h2>
					<table class="form-table newsmanTable newsmanTblFixed">
					<tr>
						<th scope="">
							<label for="newsman_smstest">Activate test mode</label>
							<p class="newsmanP">if checked, when an order status changes, the message will be sent on your specified phone, not client phone</p>
						</th>
						<td>
							<input name="newsman_smstest" type="checkbox" id="newsman_smstest" <?php echo ( 'on' === $local_options['newsman_smstest'] ) ? 'checked' : ''; ?>/>
						</td>
					</tr>
					<tr>
						<th scope="">
							<label for="newsman_smstestnr">Phone for tests</label>
						</th>
						<td>
							<input id="newsman_smstestnr" name="newsman_smstestnr" value="<?php echo esc_attr( $local_options['newsman_smstestnr'] ); ?>" /> Ex: 0720998111
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
