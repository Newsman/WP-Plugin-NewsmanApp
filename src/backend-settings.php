<?php
/**
 * Title: Newsman admin options
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Admin\Settings\Settings $this
 */

$this->is_oauth();

if ( ! $this->validate_nonce( array( $this->form_id ) ) ) {
	wp_nonce_ays( $this->nonce_action );
	return;
}
$this->create_nonce();

$this->process_form();
?>
<div class="tabset-img">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" alt="Newsman" />
	</a>
</div>
<div class="tabset">
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="tabSettings" aria-controls="" checked>
	<label for="tabSettings" id="settingsBtn">Settings</label>
	<div class="tab-panels">
		<section id="tabSettings" class="tab-panel">
			<div class="wrap wrap-settings-admin-page">
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>"/>
					<input type="hidden" name="<?php echo esc_attr( $this->form_id ); ?>" value="Y" />

					<div class="<?php echo ( is_array( $this->message ) && isset( $this->message['status'] ) ) ? esc_attr( $this->message['status'] ) : ''; ?>">
						<p>
							<strong><?php echo ( is_array( $this->message ) && isset( $this->message['message'] ) ) ? esc_attr( $this->message['message'] ) : ''; ?></strong>
						</p>
					</div>
					<h2>Newsman Connection</h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_apikey">API KEY</label>
							</th>
							<td>
								<input type="text" name="newsman_apikey" id="newsman_apikey"
									value="<?php echo esc_attr( $this->form_values['newsman_apikey'] ); ?>"/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_userid">User ID</label>
							</th>
							<td>
								<input type="text" name="newsman_userid" id="newsman_userid"
									value="<?php echo esc_attr( $this->form_values['newsman_userid'] ); ?>"/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="">Credentials Status</label>
							</th>
							<td>
								<div class="credentials-status <?php echo esc_html( $this->valid_credentials ? 'credentials-valid' : 'credentials-invalid' ); ?>">
									<span><?php echo $this->valid_credentials ? esc_html__( 'Valid', 'newsman' ) : esc_html__( 'Invalid', 'newsman' ); ?></span>
								</div>
							</td>
						</tr>
					</table>
					<h2>Settings</h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_api">Allow API access</label>
							</th>
							<td>
								<input name="newsman_api" type="checkbox"
									id="newsman_api" <?php echo ( ! empty( $this->form_values['newsman_api'] ) && 'on' === $this->form_values['newsman_api'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_senduserip">Send User IP Address</label>
							</th>
							<td>
								<input name="newsman_senduserip" type="checkbox"
									id="newsman_senduserip" <?php echo ( ! empty( $this->form_values['newsman_senduserip'] ) && 'on' === $this->form_values['newsman_senduserip'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! ( ! empty( $this->form_values['newsman_senduserip'] ) && 'on' === $this->form_values['newsman_senduserip'] ) ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_serverip">Server IP Address</label>
							</th>
							<td>
								<input type="text" id="newsman_serverip"
									name="newsman_serverip"
									value="<?php echo ( ! empty( $this->form_values['newsman_serverip'] ) ) ? esc_attr( $this->form_values['newsman_serverip'] ) : ''; ?>"/>
								<p class="description">Please set the public IP address of the server if the user IP address is not sent. Newsman subscribe to newsletter requires an IP address.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_export_authorize_header_name">Import Authorize Header Name</label>
							</th>
							<td>
								<input type="text" name="newsman_export_authorize_header_name" id="newsman_export_authorize_header_name"
									value="<?php echo esc_attr( $this->form_values['newsman_export_authorize_header_name'] ); ?>"/>
								<p class="description">HTTP Header Authorization Name. Please set only alphanumeric characters and minus character.
									Please go to <a target="_blank" href="https://newsman.app/manager">newsman.app</a> in E-Commerce &gt; Feeds and set Header Authorization. Newsman will be able to import product feeds securely.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_export_authorize_header_key">Import Authorize Header Key</label>
							</th>
							<td>
								<input type="text" name="newsman_export_authorize_header_key" id="newsman_export_authorize_header_key"
									value="<?php echo esc_attr( $this->form_values['newsman_export_authorize_header_key'] ); ?>"/>
								<p class="description">HTTP Header Authorization Key. Please set only alphanumeric characters and minus character.
									Please go to <a target="_blank" href="https://newsman.app/manager">newsman.app</a> in E-Commerce &gt; Feeds and set Header Authorization. Newsman will be able to import product feeds securely.</p>
							</td>
						</tr>
					</table>
					<h2>Checkout Subscribe to Newsletter and SMS</h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_checkoutnewsletter">Enable checkbox</label>
							</th>
							<td>
								<input name="newsman_checkoutnewsletter" type="checkbox"
									id="newsman_checkoutnewsletter" <?php echo ( ! empty( $this->form_values['newsman_checkoutnewsletter'] ) && 'on' === $this->form_values['newsman_checkoutnewsletter'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_checkoutsms">Enable SMS, sync phone numbers to your SMS list</label>
							</th>
							<td>
								<input name="newsman_checkoutsms" type="checkbox"
									id="newsman_checkoutsms" <?php echo ( ! empty( $this->form_values['newsman_checkoutsms'] ) && 'on' === $this->form_values['newsman_checkoutsms'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr class="newsman_checkoutnewslettertypePanel"
							style="display: <?php echo ( ! empty( $this->form_values['newsman_checkoutnewsletter'] ) && 'on' === $this->form_values['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_checkoutnewslettertype">Newsletter Opt-in type</label>
							</th>
							<td>
								<select name="newsman_checkoutnewslettertype" id="newsman_checkoutnewslettertype">
									<option value="save" <?php echo ( 'save' === $this->form_values['newsman_checkoutnewslettertype'] ) ? "selected = ''" : ''; ?>>
										Opt-in
									</option>
									<option value="init" <?php echo ( 'init' === $this->form_values['newsman_checkoutnewslettertype'] ) ? "selected = ''" : ''; ?>>
										Double Opt-in
									</option>
								</select>
								<p class="description">Select the type of newsletter opt-in. Double Opt-in is recommended for newsletter subscriptions.</p>
							</td>
						</tr>
						<tr class="newsman_checkoutnewslettertypePanel"
							style="display: <?php echo ( ! empty( $this->form_values['newsman_checkoutnewsletter'] ) && 'on' === $this->form_values['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_checkoutnewslettermessage">Checkbox label</label>
							</th>
							<td>
								<input type="text" id="newsman_checkoutnewslettermessage"
									name="newsman_checkoutnewslettermessage"
									value="<?php echo ( ! empty( $this->form_values['newsman_checkoutnewslettermessage'] ) ) ? esc_attr( $this->form_values['newsman_checkoutnewslettermessage'] ) : 'Subscribe to our newsletter'; ?>"/>
							</td>
						</tr>
						<tr class="newsman_checkoutnewslettertypePanel"
							style="display: <?php echo ( ! empty( $this->form_values['newsman_checkoutnewsletter'] ) && 'on' === $this->form_values['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_checkoutnewsletterdefault">Is checkbox checked by default ?</label>
							</th>
							<td>
								<input name="newsman_checkoutnewsletterdefault" type="checkbox"
									id="newsman_checkoutnewsletterdefault" <?php echo ( ! empty( $this->form_values['newsman_checkoutnewsletterdefault'] ) && 'on' === $this->form_values['newsman_checkoutnewsletterdefault'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr class="newsman_checkoutnewslettertypePanel"
							style="display: <?php echo ( ! empty( $this->form_values['newsman_checkoutnewsletter'] ) && 'on' === $this->form_values['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_form_id">Confirmation email Form ID.</label>
							</th>
							<td>
								<input type="text" id="newsman_form_id" name="newsman_form_id"
									value="<?php echo ( ! empty( $this->form_values['newsman_form_id'] ) ) ? esc_attr( $this->form_values['newsman_form_id'] ) : ''; ?>"
									placeholder="form id"/>
								<p class="description">Form ID used for the confirmation email / form settings. Forms can be created in <a target="_blank" href="https://newsman.app/manager">newsman.app</a> &gt; Forms.</p>
							</td>
						</tr>
					</table>
					<h2>Developer</h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr class="newsman_developerlogseverity">
							<th scope="row">
								<label class="nzm-label" for="newsman_developerlogseverity">Logging level</label>
							</th>
							<td>
								<?php
								// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
								// @see WC_Log_Level::$level_to_severity
								?>
								<select name="newsman_developerlogseverity" id="">
									<option value="2000" <?php echo ( '2000' === $this->form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>>
										No Logging
									</option>
									<option value="800" 
										<?php echo ( '800' === $this->form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Emergency', 'woocommerce' ) ); ?>
									</option>
									<option value="700" 
										<?php echo ( '700' === $this->form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Alert', 'woocommerce' ) ); ?>
									</option>
									<option value="600" 
										<?php echo ( '600' === $this->form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Critical', 'woocommerce' ) ); ?>
									</option>
									<option value="500" 
										<?php echo ( '500' === $this->form_values['newsman_developerlogseverity'] || empty( $this->form_values['newsman_developerlogseverity'] ) ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Error', 'woocommerce' ) ); ?>
									</option>
									<option value="400" 
										<?php echo ( '400' === $this->form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Warning', 'woocommerce' ) ); ?>
									</option>
									<option value="300" 
										<?php echo ( '300' === $this->form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Notice', 'woocommerce' ) ); ?>
									</option>
									<option value="200" 
										<?php echo ( '200' === $this->form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Info', 'woocommerce' ) ); ?>
									</option>
									<option value="100" 
										<?php echo ( '100' === $this->form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Debug', 'woocommerce' ) ); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr class="newsman_apiPanel"
							style="display: <?php echo ( ! empty( $this->form_values['newsman_api'] ) && 'on' === $this->form_values['newsman_api'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_developerapitimeout">API Timeout</label>
							</th>
							<td>
								<input type="number" step="1" id="newsman_developerapitimeout"
									name="newsman_developerapitimeout"
									value="<?php echo ( ! empty( $this->form_values['newsman_developerapitimeout'] ) ) ? esc_attr( $this->form_values['newsman_developerapitimeout'] ) : ''; ?>"
									placeholder="5"/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_developeractiveuserip">Enable Test User IP</label>
							</th>
							<td>
								<input name="newsman_developeractiveuserip" type="checkbox"
									id="newsman_developeractiveuserip" <?php echo ( ! empty( $this->form_values['newsman_developeractiveuserip'] ) && 'on' === $this->form_values['newsman_developeractiveuserip'] ) ? 'checked' : ''; ?>/>
								<p class="description">Warning, do not use this IP address in production because it is used by API endpoint subscriber.saveSubscribe.</p>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $this->form_values['newsman_developeractiveuserip'] ) && 'on' === $this->form_values['newsman_developeractiveuserip'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_developeruserip">Test User IP address</label>
							</th>
							<td>
								<input type="text" id="newsman_developeruserip"
									name="newsman_developeruserip"
									value="<?php echo ( ! empty( $this->form_values['newsman_developeruserip'] ) ) ? esc_attr( $this->form_values['newsman_developeruserip'] ) : ''; ?>"/>
								<p class="description">Valid user IP address.</p>
							</td>
						</tr>
                        <tr>
                            <th scope="row">
                                <label class="nzm-label" for="newsman_developerpluginlazypriority">Plugin Loaded Priority</label>
                            </th>
                            <td>
                                <input type="text" id="newsman_developerpluginlazypriority"
                                       name="newsman_developerpluginlazypriority"
                                       value="<?php echo ( ! empty( $this->form_values['newsman_developerpluginlazypriority'] ) ) ? esc_attr( $this->form_values['newsman_developerpluginlazypriority'] ) : ''; ?>"/>
                                <p class="description">Newsman plugin Woo Commerce hooks are loaded with add_action plugins_loaded and priority set in this configuration. Default is 20.</p>
                            </td>
                        </tr>
					</table>
					<div style="padding-top: 5px;">
						<input type="submit" value="Save Changes" class="button button-primary"/>
					</div>
				</form>
			</div>
		</section>
	</div>
</div>
