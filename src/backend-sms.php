<?php
/**
 * Title: Newsman admin SMS options
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Admin\Settings\Sms $this
 */

$this->is_oauth();

if ( ! $this->validate_nonce( array( $this->form_id ) ) ) {
	wp_nonce_ays( $this->nonce_action );
	return;
}
$this->create_nonce();

$this->process_form();
$form_values = $this->get_form_values();
?>
<script>
	jQuery(document).ready(function()
	{
		jQuery('.newsman_smspendingdescription .nzm-variable').on('click', function(){
			jQuery('#newsman_smspendingtext').append(jQuery(this).html());
		});	
		jQuery('.newsman_smsfaileddescription .nzm-variable').on('click', function(){
			jQuery('#newsman_smsfailedtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smsonholddescription .nzm-variable').on('click', function(){
			jQuery('#newsman_smsonholdtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smsprocessingdescription .nzm-variable').on('click', function(){
			jQuery('#newsman_smsprocessingtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smscompleteddescription .nzm-variable').on('click', function(){
			jQuery('#newsman_smscompletedtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smsrefundeddescription .nzm-variable').on('click', function(){
			jQuery('#newsman_smsrefundedtext').append(jQuery(this).html());
		});
		jQuery('.newsman_smscancelleddescription .nzm-variable').on('click', function(){
			jQuery('#newsman_smscancelledtext').append(jQuery(this).html());
		});
	})	
</script>
<div class="tabset-img">
	<a href="https://newsman.com" target="_blank">
		<img src="<?php echo esc_url( NEWSMAN_PLUGIN_URL ); ?>src/img/logo.png" alt="NewsMAN" />
	</a>
</div>
<div class="tabset">
	<input type="radio" name="tabset" id="tabNewsman" aria-controls="">
	<label for="tabNewsman" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="tabSync" aria-controls="">
	<label for="tabSync" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="tabRemarketing" aria-controls="">
	<label for="tabRemarketing" id="remarketingBtn">Remarketing</label>
	<?php if ( $this->is_woo_commerce_exists() ) : ?>
		<input type="radio" name="tabset" id="tabSmsBtn" aria-controls="" checked>
		<label for="tabSmsBtn" id="smsBtn">SMS</label>
	<?php endif; ?>
	<input type="radio" name="tabset" id="tabSettings" aria-controls="">
	<label for="tabSettings" id="settingsBtn">Settings</label>
	<!--<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>-->
	<div class="tab-panels">
		<section id="tabSms" class="tab-panel">
			<div class="wrap wrap-settings-admin-page">
				<form method="post" enctype="multipart/form-data" id="mainForm" action="<?php echo esc_url( admin_url( 'admin.php?page=NewsmanSMS' ) ); ?>">
					<input type="hidden" name="newsman_action" value="Y" />
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>"/>
					<input type="hidden" name="<?php echo esc_attr( $this->form_id ); ?>" value="Y" />
					<h2>SMS</h2>
					<div class="<?php echo ( is_array( $this->message ) && isset( $this->message['status'] ) ) ? esc_attr( $this->message['status'] ) : ''; ?>"><p><strong><?php echo ( is_array( $this->message ) && isset( $this->message['message'] ) ) ? esc_html( $this->message['message'] ) : ''; ?></strong>
						</p></div>
					<table class="form-table newsman-table">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="">Credentials Status</label>
							</th>
							<td colspan="2">
								<div class="credentials-status <?php echo esc_html( $this->valid_credentials ? 'credentials-valid' : 'credentials-invalid' ); ?>">
									<span><?php echo $this->valid_credentials ? esc_html__( 'Valid', 'newsman' ) : esc_html__( 'Invalid', 'newsman' ); ?></span>
								</div>
								<?php if ( ! $this->valid_credentials ) { ?>
									<p class="description"><?php echo esc_html__( 'Please configure the credentials in Settings tab.', 'newsman' ); ?></p>
								<?php } ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_usesms">Use SMS</label>
							</th>
							<td>
								<input name="newsman_usesms" type="checkbox" id="newsman_usesms" <?php echo ( 'on' === $form_values['newsman_usesms'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_smslist">Select SMS List</label>
							</th>
							<td colspan="2">
								<?php if ( 'on' === $form_values['newsman_usesms'] && ! empty( $this->available_smslists ) ) { ?>
									<select name="newsman_smslist" id="">
										<option value="0">-- select list --</option>
											<?php
											foreach ( $this->available_smslists as $item ) {
												?>
												<option
													value="<?php echo esc_attr( $item['list_id'] ); ?>" <?php echo ( strval( $item['list_id'] ) === strval( $form_values['newsman_smslist'] ) ) ? "selected = ''" : ''; ?>><?php echo esc_html( $item['list_name'] ); ?></option>
											<?php } ?>
									</select>
									<?php if ( empty( $form_values['newsman_smslist'] ) ) : ?>
										<p class="description nzm-description-error"><?php echo esc_html__( 'Please save a SMS list to start sending SMS.', 'newsman' ); ?></p>
									<?php endif; ?>
								<?php } else { ?>
									<p class="description"><?php echo esc_html__( 'The SMS lists dropdown is displayed when Use SMS is enabled.', 'newsman' ); ?></p>
								<?php } ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_smstext">SMS Message</label>
							</th>
							<th scope="row">
								<label class="nzm-label" for="newsman_smstext">Order Status</label>
							</th>
							<th scope="row">
								<label class="nzm-label" for="newsman_smstext">Message / Variables</label>
							</th>
						</tr>
						<tr>
							<td class="newsman-sms-msg">&nbsp;</td>
							<td class="newsman-sms-status">
								<label for="newsman_smspendingactivate">Pending | Active</label>
								<input name="newsman_smspendingactivate" type="checkbox" id="newsman_smspendingactivate" <?php echo ( 'on' === $form_values['newsman_smspendingactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smspendingtextPanel" <?php echo ( empty( $form_values['newsman_smspendingactivate'] ) || 'off' === $form_values['newsman_smspendingactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<label style="display: none;" for="newsman_smspendingtext">Message</label>
								<textarea id="newsman_smspendingtext" name="newsman_smspendingtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $form_values['newsman_smspendingtext'] ) ) ? esc_html( $form_values['newsman_smspendingtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
								<p class="newsman_smspendingdescription" style="padding: 5px;">Variables: <span class="nzm-variable">{{billing_first_name}}</span><span class="nzm-variable">{{billing_last_name}}</span><span class="nzm-variable">{{shipping_first_name}}</span><span class="nzm-variable">{{shipping_last_name}}</span><span class="nzm-variable">{{order_number}}</span><span class="nzm-variable">{{order_date}}</span><span class="nzm-variable">{{order_total}}</span><span class="nzm-variable">{{email}}</span></p>
							</td>
						</tr>
						<tr>
							<td class="newsman-sms-msg">&nbsp;</td>
							<td class="newsman-sms-status">
								<label for="newsman_smsfailedactivate">Failed | Active</label>
								<input name="newsman_smsfailedactivate" type="checkbox" id="newsman_smsfailedactivate" <?php echo ( 'on' === $form_values['newsman_smsfailedactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smsfailedtextPanel" <?php echo ( empty( $form_values['newsman_smsfailedactivate'] ) || 'off' === $form_values['newsman_smsfailedactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<label style="display: none;" for="newsman_smsfailedtext">Message</label>
								<textarea id="newsman_smsfailedtext" name="newsman_smsfailedtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $form_values['newsman_smsfailedtext'] ) ) ? esc_html( $form_values['newsman_smsfailedtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
								<p class="newsman_smsfaileddescription" style="padding: 5px;">Variables: <span class="nzm-variable">{{billing_first_name}}</span><span class="nzm-variable">{{billing_last_name}}</span><span class="nzm-variable">{{shipping_first_name}}</span><span class="nzm-variable">{{shipping_last_name}}</span><span class="nzm-variable">{{order_number}}</span><span class="nzm-variable">{{order_date}}</span><span class="nzm-variable">{{order_total}}</span><span class="nzm-variable">{{email}}</span></p>
							</td>
						</tr>
						<tr>
							<td class="newsman-sms-msg">&nbsp;</td>
							<td class="newsman-sms-status">
								<label for="newsman_smsonholdactivate">On Hold | Active</label>
								<input name="newsman_smsonholdactivate" type="checkbox" id="newsman_smsonholdactivate" <?php echo ( 'on' === $form_values['newsman_smsonholdactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smsonholdtextPanel" <?php echo ( empty( $form_values['newsman_smsonholdactivate'] ) || 'off' === $form_values['newsman_smsonholdactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<label style="display: none;" for="newsman_smsonholdtext">Message</label>
								<textarea id="newsman_smsonholdtext" name="newsman_smsonholdtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $form_values['newsman_smsonholdtext'] ) ) ? esc_html( $form_values['newsman_smsonholdtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
								<p class="newsman_smsonholddescription" style="padding: 5px;">Variables: <span class="nzm-variable">{{billing_first_name}}</span><span class="nzm-variable">{{billing_last_name}}</span><span class="nzm-variable">{{shipping_first_name}}</span><span class="nzm-variable">{{shipping_last_name}}</span><span class="nzm-variable">{{order_number}}</span><span class="nzm-variable">{{order_date}}</span><span class="nzm-variable">{{order_total}}</span><span class="nzm-variable">{{email}}</span></p>
							</td>
						</tr>
						<tr>
							<td class="newsman-sms-msg">&nbsp;</td>
							<td class="newsman-sms-status">
								<label for="newsman_smsprocessingactivate">Processing | Active</label>
								<input name="newsman_smsprocessingactivate" type="checkbox" id="newsman_smsprocessingactivate" <?php echo ( 'on' === $form_values['newsman_smsprocessingactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smsprocessingtextPanel" <?php echo ( empty( $form_values['newsman_smsprocessingactivate'] ) || 'off' === $form_values['newsman_smsprocessingactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<label style="display: none;" for="newsman_smsprocessingtext">Message</label>
								<textarea id="newsman_smsprocessingtext" name="newsman_smsprocessingtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $form_values['newsman_smsprocessingtext'] ) ) ? esc_html( $form_values['newsman_smsprocessingtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
								<p class="newsman_smsprocessingdescription" style="padding: 5px;">Variables: <span class="nzm-variable">{{billing_first_name}}</span><span class="nzm-variable">{{billing_last_name}}</span><span class="nzm-variable">{{shipping_first_name}}</span><span class="nzm-variable">{{shipping_last_name}}</span><span class="nzm-variable">{{order_number}}</span><span class="nzm-variable">{{order_date}}</span><span class="nzm-variable">{{order_total}}</span><span class="nzm-variable">{{email}}</span></p>
							</td>
						</tr>
						<tr>
							<td class="newsman-sms-msg">&nbsp;</td>
							<td class="newsman-sms-status">
								<label for="newsman_smscompletedactivate">Completed | Active</label>
								<input name="newsman_smscompletedactivate" type="checkbox" id="newsman_smscompletedactivate" <?php echo ( 'on' === $form_values['newsman_smscompletedactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smscompletedtextPanel" <?php echo ( empty( $form_values['newsman_smscompletedactivate'] ) || 'off' === $form_values['newsman_smscompletedactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<label style="display: none;" for="newsman_smscompletedtext">Message</label>
								<textarea id="newsman_smscompletedtext" name="newsman_smscompletedtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $form_values['newsman_smscompletedtext'] ) ) ? esc_html( $form_values['newsman_smscompletedtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
								<p class="newsman_smscompleteddescription" style="padding: 5px;">Variables: <span class="nzm-variable">{{billing_first_name}}</span><span class="nzm-variable">{{billing_last_name}}</span><span class="nzm-variable">{{shipping_first_name}}</span><span class="nzm-variable">{{shipping_last_name}}</span><span class="nzm-variable">{{order_number}}</span><span class="nzm-variable">{{order_date}}</span><span class="nzm-variable">{{order_total}}</span><span class="nzm-variable">{{email}}</span></p>
							</td>
						</tr>
						<tr>
							<td class="newsman-sms-msg">&nbsp;</td>
							<td class="newsman-sms-status">
								<label for="newsman_smsrefundedactivate">Refunded | Active</label>
								<input name="newsman_smsrefundedactivate" type="checkbox" id="newsman_smsrefundedactivate" <?php echo ( 'on' === $form_values['newsman_smsrefundedactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smsrefundedtextPanel" <?php echo ( empty( $form_values['newsman_smsrefundedactivate'] ) || 'off' === $form_values['newsman_smsrefundedactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<label style="display: none;" for="newsman_smsrefundedtext">Message</label>
								<textarea id="newsman_smsrefundedtext" name="newsman_smsrefundedtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $form_values['newsman_smsrefundedtext'] ) ) ? esc_html( $form_values['newsman_smsrefundedtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
								<p class="newsman_smsrefundeddescription" style="padding: 5px;">Variables: <span class="nzm-variable">{{billing_first_name}}</span><span class="nzm-variable">{{billing_last_name}}</span><span class="nzm-variable">{{shipping_first_name}}</span><span class="nzm-variable">{{shipping_last_name}}</span><span class="nzm-variable">{{order_number}}</span><span class="nzm-variable">{{order_date}}</span><span class="nzm-variable">{{order_total}}</span><span class="nzm-variable">{{email}}</span></p>
							</td>
						</tr>
						<tr>
							<td class="newsman-sms-msg">&nbsp;</td>
							<td class="newsman-sms-status">
								<label for="newsman_smscancelledactivate">Canceled | Active</label>
								<input name="newsman_smscancelledactivate" type="checkbox" id="newsman_smscancelledactivate" <?php echo ( 'on' === $form_values['newsman_smscancelledactivate'] ) ? 'checked' : ''; ?>/>
							</td>
							<td class="newsman_smscancelledtextPanel" <?php echo ( empty( $form_values['newsman_smscancelledactivate'] ) || 'off' === $form_values['newsman_smscancelledactivate'] ) ? 'style="display: none;"' : ''; ?>>
								<label style="display: none;" for="newsman_smscancelledtext">Message</label>
								<textarea id="newsman_smscancelledtext" name="newsman_smscancelledtext" style="width: 100%; min-height: 100px;"><?php echo ( ! empty( $form_values['newsman_smscancelledtext'] ) ) ? esc_html( $form_values['newsman_smscancelledtext'] ) : 'Order no. {{order_number}}, in total of {{order_total}} EURO, from example.com'; ?></textarea>
								<p class="newsman_smscancelleddescription" style="padding: 5px;">Variables: <span class="nzm-variable">{{billing_first_name}}</span><span class="nzm-variable">{{billing_last_name}}</span><span class="nzm-variable">{{shipping_first_name}}</span><span class="nzm-variable">{{shipping_last_name}}</span><span class="nzm-variable">{{order_number}}</span><span class="nzm-variable">{{order_date}}</span><span class="nzm-variable">{{order_total}}</span><span class="nzm-variable">{{email}}</span></p>
							</td>
						</tr>
					</table>
					<h2>SMS production debug</h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
					<tr>
						<th scope="row">
							<label class="nzm-label" for="newsman_smstest">Activate test mode</label>
						</th>
						<td>
							<input name="newsman_smstest" type="checkbox" id="newsman_smstest" <?php echo ( 'on' === $form_values['newsman_smstest'] ) ? 'checked' : ''; ?>/>
							<p class="description">The message will be sent to your specified phone and not to customer phone. SMS are sent on order status changes.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label class="nzm-label" for="newsman_smstestnr">Phone for tests</label>
						</th>
						<td>
							<input id="newsman_smstestnr" name="newsman_smstestnr" value="<?php echo esc_attr( $form_values['newsman_smstestnr'] ); ?>" /> Ex: 0720998111
						</td>
					</tr>
					</table>
					<h2>SMS send test</h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_smsdevtestnr">Phone</label>
							</th>
							<td>
								<input id="newsman_smsdevtestnr" name="newsman_smsdevtestnr" value="<?php echo ''; ?>" /> Ex: 0720998111
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_smsdevtestmsg">Test message</label>
							</th>
							<td>
								<textarea id="newsman_smsdevtestmsg" name="newsman_smsdevtestmsg" style="width: 100%; min-height: 100px;"><?php echo ''; ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_smsdevtestbtn">Send now</label>
							</th>
							<td class="msg_smsdevbtn">
								<input type="button" value="Send Now" name="newsman_smsdevbtn" class="button button-primary"/>
							</td>
						</tr>
					</table>
					<div style="padding-top: 5px;">
						<input type="submit" name="newsman_sms" value="Save Changes" class="button button-primary"/>
					</div>
				</form>
			</div>
		</section>
	</div>  
</div>
