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
$form_values = $this->get_form_values();
?>
<div class="tabset-img">
	<a href="https://newsman.com" target="_blank">
		<img src="<?php echo esc_url( NEWSMAN_PLUGIN_URL ); ?>src/img/logo.png" alt="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" title="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" />
	</a>
</div>
<div class="nzm-tabset">
	<input type="radio" name="tabset" id="tabNewsman" aria-controls="">
	<label for="tabNewsman" id="newsmanBtn"><?php echo esc_html__( 'NewsMAN', 'newsman' ); ?></label>
	<input type="radio" name="tabset" id="tabSync" aria-controls="">
	<label for="tabSync" id="syncBtn"><?php echo esc_html__( 'Sync', 'newsman' ); ?></label>
	<input type="radio" name="tabset" id="tabRemarketing" aria-controls="">
	<label for="tabRemarketing" id="remarketingBtn"><?php echo esc_html__( 'Remarketing', 'newsman' ); ?></label>
	<?php if ( $this->is_woo_commerce_exists() ) : ?>
		<input type="radio" name="tabset" id="tabSms" aria-controls="">
		<label for="tabSms" id="smsBtn"><?php echo esc_html__( 'SMS', 'newsman' ); ?></label>
	<?php endif; ?>
	<input type="radio" name="tabset" id="tabSettings" aria-controls="" checked>
	<label for="tabSettings" id="settingsBtn"><?php echo esc_html__( 'Settings', 'newsman' ); ?></label>
	<div class="tab-panels">
		<section id="tabSettings" class="tab-panel">
			<div class="wrap wrap-settings-admin-page">
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?page=NewsmanSettings' ) ); ?>">
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>"/>
					<input type="hidden" name="<?php echo esc_attr( $this->form_id ); ?>" value="Y" />
					<?php foreach ( $this->get_backend_messages() as $message ) : ?>
					<div class="<?php echo ( is_array( $message ) && isset( $message['status'] ) ) ? esc_attr( $message['status'] ) : ''; ?>">
						<p><strong><?php echo ( is_array( $message ) && isset( $message['message'] ) ) ? esc_attr( $message['message'] ) : ''; ?></strong></p>
					</div>
					<?php endforeach; ?>
					<h2><?php echo esc_html__( 'NewsMAN Connection', 'newsman' ); ?></h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_apikey"><?php echo esc_html__( 'API KEY', 'newsman' ); ?></label>
							</th>
							<td>
								<input type="text" name="newsman_apikey" id="newsman_apikey"
									value="<?php echo esc_attr( $form_values['newsman_apikey'] ); ?>"/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_userid"><?php echo esc_html__( 'User ID', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="text" name="newsman_userid" id="newsman_userid"
									value="<?php echo esc_attr( $form_values['newsman_userid'] ); ?>"/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for=""><?php echo esc_html__( 'Credentials Status', 'newsman' ); ?></label>
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
								<label class="nzm-label" for="newsman_api"><?php echo esc_html__( 'Allow API access', 'newsman' ); ?></label>
							</th>
							<td>
								<input name="newsman_api" type="checkbox"
									id="newsman_api" <?php echo ( ! empty( $form_values['newsman_api'] ) && 'on' === $form_values['newsman_api'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_senduserip"><?php echo esc_html__( 'Send User IP Address', 'newsman' ); ?></label>
							</th>
							<td>
								<input name="newsman_senduserip" type="checkbox"
									id="newsman_senduserip" <?php echo ( ! empty( $form_values['newsman_senduserip'] ) && 'on' === $form_values['newsman_senduserip'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! ( ! empty( $form_values['newsman_senduserip'] ) && 'on' === $form_values['newsman_senduserip'] ) ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_serverip"><?php echo esc_html__( 'Server IP Address', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="text" id="newsman_serverip"
									name="newsman_serverip"
									value="<?php echo ( ! empty( $form_values['newsman_serverip'] ) ) ? esc_attr( $form_values['newsman_serverip'] ) : ''; ?>"/>
								<p class="description"><?php echo esc_html__( 'Please set the public IP address of the server if the user IP address is not sent. NewsMAN subscribe to newsletter requires an IP address.', 'newsman' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_export_authorize_header_name"><?php echo esc_html__( 'Import Authorize Header Name', 'newsman' ); ?></label>
							</th>
							<td>
								<input type="text" name="newsman_export_authorize_header_name" id="newsman_export_authorize_header_name"
									value="<?php echo esc_attr( $form_values['newsman_export_authorize_header_name'] ); ?>"/>
								<p class="description"><?php echo esc_html__( 'HTTP Header Authorization Name. Please set only alphanumeric characters and minus character.', 'newsman' ); ?>
									<?php
									printf(
										wp_kses(
											/* translators: 1: Link to NewsMAN */
											__( 'Please go to <a target="_blank" href="%s">newsman.app</a> in E-Commerce &gt; Feeds and set Header Authorization.', 'newsman' ),
											array(
												'a' => array(
													'href' => array(),
													'target' => array(),
												),
											)
										),
										'https://newsman.app/manager'
									);
									?>
									<?php echo esc_html__( 'NewsMAN will be able to import product feeds securely.', 'newsman' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_export_authorize_header_key"><?php echo esc_html__( 'Import Authorize Header Key', 'newsman' ); ?></label>
							</th>
							<td>
								<input type="text" name="newsman_export_authorize_header_key" id="newsman_export_authorize_header_key"
									value="<?php echo esc_attr( $form_values['newsman_export_authorize_header_key'] ); ?>"/>
								<p class="description"><?php echo esc_html__( 'HTTP Header Authorization Key. Please set only alphanumeric characters and minus character.', 'newsman' ); ?>
									<?php
									printf(
										wp_kses(
											/* translators: 1: Link to NewsMAN */
											__( 'Please go to <a target="_blank" href="%s">newsman.app</a> in E-Commerce &gt; Feeds and set Header Authorization.', 'newsman' ),
											array(
												'a' => array(
													'href' => array(),
													'target' => array(),
												),
											)
										),
										'https://newsman.app/manager'
									);
									?>
									<?php echo esc_html__( 'NewsMAN will be able to import product feeds securely.', 'newsman' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<h2>Subscribe to Newsletter</h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_newslettertype"><?php echo esc_html__( 'Newsletter Opt-in type', 'newsman' ); ?></label>
							</th>
							<td>
								<select class="nzm-small-select" name="newsman_newslettertype" id="newsman_newslettertype">
									<option value="save" <?php echo ( 'save' === $form_values['newsman_newslettertype'] ) ? "selected = ''" : ''; ?>>
										<?php echo esc_html__( 'Opt-in', 'newsman' ); ?>
									</option>
									<option value="init" <?php echo ( 'init' === $form_values['newsman_newslettertype'] ) ? "selected = ''" : ''; ?>>
										<?php echo esc_html__( 'Double Opt-in', 'newsman' ); ?>
									</option>
								</select>
								<p class="description"><?php echo esc_html__( 'Select the type of newsletter opt-in. Double Opt-in is recommended for newsletter subscriptions.', 'newsman' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_form_id"><?php echo esc_html__( 'Confirmation email Form ID.', 'newsman' ); ?></label>
							</th>
							<td>
								<input type="text" id="newsman_form_id" name="newsman_form_id"
										value="<?php echo ( ! empty( $form_values['newsman_form_id'] ) ) ? esc_attr( $form_values['newsman_form_id'] ) : ''; ?>"
										placeholder="form id"/>
								<p class="description"><?php echo esc_html__( 'Form ID used for the confirmation email / form settings.', 'newsman' ); ?>
									<?php
									printf(
										wp_kses(
											__( 'Forms can be created in <a target="_blank" href="https://newsman.app/manager">newsman.app</a> &gt; Forms.', 'newsman' ),
											array(
												'a' => array(
													'href' => array(),
													'target' => array(),
												),
											)
										),
										'https://newsman.app/manager'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
					<?php if ( $this->is_woo_commerce_exists() ) : ?>
						<h2><?php echo esc_html__( 'Checkout Subscribe to Newsletter and SMS', 'newsman' ); ?></h2>
						<table class="form-table newsman-table newsman-tbl-fixed">
							<tr>
								<th scope="row">
									<label class="nzm-label" for="newsman_checkoutnewsletter"><?php echo esc_html__( 'Enable', 'newsman' ); ?></label>
								</th>
								<td>
									<input name="newsman_checkoutnewsletter" type="checkbox"
										id="newsman_checkoutnewsletter" <?php echo ( ! empty( $form_values['newsman_checkoutnewsletter'] ) && 'on' === $form_values['newsman_checkoutnewsletter'] ) ? 'checked' : ''; ?>/>
									<p class="description"><?php echo esc_html__( 'Enable checkbox to subscribe to newsletter in checkout.', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label class="nzm-label" for="newsman_checkoutsms"><?php echo esc_html__( 'Enable SMS, sync phone numbers to your SMS list', 'newsman' ); ?></label>
								</th>
								<td>
									<input name="newsman_checkoutsms" type="checkbox"
										id="newsman_checkoutsms" <?php echo ( ! empty( $form_values['newsman_checkoutsms'] ) && 'on' === $form_values['newsman_checkoutsms'] ) ? 'checked' : ''; ?>/>
									<p class="description"><?php echo esc_html__( 'Enable subscribe after checkout to SMS list with order billing telephone number.', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr style="display: <?php echo ( ! empty( $form_values['newsman_checkoutnewsletter'] ) && 'on' === $form_values['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
								<th scope="row">
									<label class="nzm-label" for="newsman_checkoutnewslettermessage"><?php echo esc_html__( 'Checkbox label', 'newsman' ); ?></label>
								</th>
								<td>
									<input type="text" id="newsman_checkoutnewslettermessage"
										name="newsman_checkoutnewslettermessage"
										value="<?php echo ( ! empty( $form_values['newsman_checkoutnewslettermessage'] ) ) ? esc_attr( $form_values['newsman_checkoutnewslettermessage'] ) : 'Subscribe to our newsletter'; ?>"/>
								</td>
							</tr>
							<tr style="display: <?php echo ( ! empty( $form_values['newsman_checkoutnewsletter'] ) && 'on' === $form_values['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
								<th scope="row">
									<label class="nzm-label" for="newsman_checkoutnewsletterdefault"><?php echo esc_html__( 'Is checkbox checked by default ?', 'newsman' ); ?></label>
								</th>
								<td>
									<input name="newsman_checkoutnewsletterdefault" type="checkbox"
										id="newsman_checkoutnewsletterdefault" <?php echo ( ! empty( $form_values['newsman_checkoutnewsletterdefault'] ) && 'on' === $form_values['newsman_checkoutnewsletterdefault'] ) ? 'checked' : ''; ?>/>
								</td>
							</tr>
						</table>
						<h2><?php echo esc_html__( 'My Account Subscribe to Newsletter', 'newsman' ); ?></h2>
						<table class="form-table newsman-table newsman-tbl-fixed">
							<tr>
								<th scope="row">
									<label class="nzm-label" for="newsman_myaccountnewsletter"><?php echo esc_html__( 'Enable', 'newsman' ); ?></label>
								</th>
								<td>
									<input name="newsman_myaccountnewsletter" type="checkbox"
										id="newsman_myaccountnewsletter" <?php echo ( ! empty( $form_values['newsman_myaccountnewsletter'] ) && 'on' === $form_values['newsman_myaccountnewsletter'] ) ? 'checked' : ''; ?>/>
									<p class="description"><?php echo esc_html__( 'Enable page and checkbox to subscribe to newsletter in customers account.', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr style="display: <?php echo ( ! empty( $form_values['newsman_myaccountnewsletter'] ) && 'on' === $form_values['newsman_myaccountnewsletter'] ) ? 'table-row' : 'none'; ?>;">
								<th scope="row">
									<label class="nzm-label" for="newsman_myaccountnewsletter_menu_label"><?php echo esc_html__( 'Page Menu Label', 'newsman' ); ?></label>
								</th>
								<td>
									<input class="nzm-small-input" type="text" id="newsman_myaccountnewsletter_menu_label"
											name="newsman_myaccountnewsletter_menu_label"
											value="<?php echo ( ! empty( $form_values['newsman_myaccountnewsletter_menu_label'] ) ) ? esc_attr( $form_values['newsman_myaccountnewsletter_menu_label'] ) : ''; ?>"/>
									<p class="description"><?php echo esc_html__( 'The label on the link on the left side menu in customer account.', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr style="display: <?php echo ( ! empty( $form_values['newsman_myaccountnewsletter'] ) && 'on' === $form_values['newsman_myaccountnewsletter'] ) ? 'table-row' : 'none'; ?>;">
								<th scope="row">
									<label class="nzm-label" for="newsman_myaccountnewsletter_page_title"><?php echo esc_html__( 'Page Title', 'newsman' ); ?></label>
								</th>
								<td>
									<input class="nzm-small-input" type="text" id="newsman_myaccountnewsletter_page_title"
											name="newsman_myaccountnewsletter_page_title"
											value="<?php echo ( ! empty( $form_values['newsman_myaccountnewsletter_page_title'] ) ) ? esc_attr( $form_values['newsman_myaccountnewsletter_page_title'] ) : ''; ?>"/>
									<p class="description"><?php echo esc_html__( 'The title of subscribe to newsletter page in customer account.', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr style="display: <?php echo ( ! empty( $form_values['newsman_myaccountnewsletter'] ) && 'on' === $form_values['newsman_myaccountnewsletter'] ) ? 'table-row' : 'none'; ?>;">
								<th scope="row">
									<label class="nzm-label" for="newsman_myaccountnewsletter_checkbox_label"><?php echo esc_html__( 'Checkbox Label', 'newsman' ); ?></label>
								</th>
								<td>
									<input class="nzm-small-input" type="text" id="newsman_myaccountnewsletter_checkbox_label"
											name="newsman_myaccountnewsletter_checkbox_label"
											value="<?php echo ( ! empty( $form_values['newsman_myaccountnewsletter_checkbox_label'] ) ) ? esc_attr( $form_values['newsman_myaccountnewsletter_checkbox_label'] ) : ''; ?>"/>
									<p class="description"><?php echo esc_html__( 'The label on subscribe to newsletter checkbox in customer account.', 'newsman' ); ?></p>
								</td>
							</tr>
						</table>
					<?php endif; ?>
					<h2>Developer</h2>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr class="newsman_developerlogseverity">
							<th scope="row">
								<label class="nzm-label" for="newsman_developerlogseverity"><?php echo esc_html__( 'Logging level', 'newsman' ); ?></label>
							</th>
							<td>
								<?php
								// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
								// @see WC_Log_Level::$level_to_severity
								?>
								<select class="nzm-small-select" name="newsman_developerlogseverity" id="">
									<option value="2000" <?php echo ( '2000' === $form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>>
										<?php echo esc_html__( 'No Logging', 'newsman' ); ?>
									</option>
									<option value="800" 
										<?php echo ( '800' === $form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Emergency', 'woocommerce' ) ); ?>
									</option>
									<option value="700" 
										<?php echo ( '700' === $form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Alert', 'woocommerce' ) ); ?>
									</option>
									<option value="600" 
										<?php echo ( '600' === $form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Critical', 'woocommerce' ) ); ?>
									</option>
									<option value="500" 
										<?php echo ( '500' === $form_values['newsman_developerlogseverity'] || empty( $form_values['newsman_developerlogseverity'] ) ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Error', 'woocommerce' ) ); ?>
									</option>
									<option value="400" 
										<?php echo ( '400' === $form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Warning', 'woocommerce' ) ); ?>
									</option>
									<option value="300" 
										<?php echo ( '300' === $form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Notice', 'woocommerce' ) ); ?>
									</option>
									<option value="200" 
										<?php echo ( '200' === $form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Info', 'woocommerce' ) ); ?>
									</option>
									<option value="100" 
										<?php echo ( '100' === $form_values['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Debug', 'woocommerce' ) ); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $form_values['newsman_api'] ) && 'on' === $form_values['newsman_api'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_developerapitimeout"><?php echo esc_html__( 'API Timeout', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="number" step="1" id="newsman_developerapitimeout"
									name="newsman_developerapitimeout"
									value="<?php echo ( ! empty( $form_values['newsman_developerapitimeout'] ) ) ? esc_attr( $form_values['newsman_developerapitimeout'] ) : ''; ?>"
									placeholder="<?php echo esc_attr( $this->config->get_api_timeout() ); ?>"/>
							</td>
						</tr>
						<?php if ( $this->is_woo_commerce_exists() ) : ?>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_developeractiveuserip"><?php echo esc_html__( 'Enable Test User IP', 'newsman' ); ?></label>
							</th>
							<td>
								<input name="newsman_developeractiveuserip" type="checkbox"
									id="newsman_developeractiveuserip" <?php echo ( ! empty( $form_values['newsman_developeractiveuserip'] ) && 'on' === $form_values['newsman_developeractiveuserip'] ) ? 'checked' : ''; ?>/>
								<p class="description<?php echo ( ! empty( $form_values['newsman_developeractiveuserip'] ) ? ' nzm-description-error' : '' ); ?>"><?php echo esc_html__( 'Warning! Please do not use this IP address in production because it is used by API endpoint subscriber.saveSubscribe.', 'newsman' ); ?></p>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $form_values['newsman_developeractiveuserip'] ) && 'on' === $form_values['newsman_developeractiveuserip'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label class="nzm-label" for="newsman_developeruserip"><?php echo esc_html__( 'Test User IP address', 'newsman' ); ?></label>
							</th>
							<td>
								<input type="text" id="newsman_developeruserip"
									name="newsman_developeruserip"
									value="<?php echo ( ! empty( $form_values['newsman_developeruserip'] ) ) ? esc_attr( $form_values['newsman_developeruserip'] ) : ''; ?>"/>
								<p class="description">Valid user IP address.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_developerpluginlazypriority"><?php echo esc_html__( 'Plugin Loaded Priority', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="text" id="newsman_developerpluginlazypriority"
									name="newsman_developerpluginlazypriority"
									value="<?php echo ( ! empty( $form_values['newsman_developerpluginlazypriority'] ) ) ? esc_attr( $form_values['newsman_developerpluginlazypriority'] ) : ''; ?>"/>
								<p class="description"><?php echo esc_html__( 'NewsMAN plugin Woo Commerce hooks are loaded with add_action plugins_loaded and priority set in this configuration. Default is 20.', 'newsman' ); ?></p>
							</td>
						</tr>
							<?php if ( $this->is_action_scheduler_exists() ) : ?>
							<tr>
								<th scope="row">
									<label class="nzm-label" for="newsman_developer_use_action_scheduler"><?php echo esc_html__( 'Use Action Scheduler', 'newsman' ); ?></label>
								</th>
								<td>
									<input name="newsman_developer_use_action_scheduler" type="checkbox"
										id="newsman_developer_use_action_scheduler" <?php echo ( ! empty( $form_values['newsman_developer_use_action_scheduler'] ) && 'on' === $form_values['newsman_developer_use_action_scheduler'] ) ? 'checked' : ''; ?>/>
									<p class="description"><?php echo esc_html__( 'Use action scheduler plugin for some of NewsMAN API actions.', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr style="display: <?php echo ( ! empty( $form_values['newsman_developer_use_action_scheduler'] ) && 'on' === $form_values['newsman_developer_use_action_scheduler'] ) ? 'table-row' : 'none'; ?>;">
								<th scope="row">
									<label class="nzm-label" for="newsman_developer_use_as_subscribe"><?php echo esc_html__( 'Use Action Scheduler for Subscribe', 'newsman' ); ?></label>
								</th>
								<td>
									<input name="newsman_developer_use_as_subscribe" type="checkbox"
										id="newsman_developer_use_as_subscribe" <?php echo ( ! empty( $form_values['newsman_developer_use_as_subscribe'] ) && 'on' === $form_values['newsman_developer_use_as_subscribe'] ) ? 'checked' : ''; ?>/>
									<p class="description"><?php echo esc_html__( 'On storefront use action scheduler for subscribe to email and SMS lists actions.', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr style="display: <?php echo ( ! empty( $form_values['newsman_developer_use_action_scheduler'] ) && 'on' === $form_values['newsman_developer_use_action_scheduler'] ) ? 'table-row' : 'none'; ?>;">
								<th scope="row">
									<label class="nzm-label" for="newsman_developer_use_as_unsubscribe"><?php echo esc_html__( 'Use Action Scheduler for Unsubscribe', 'newsman' ); ?></label>
								</th>
								<td>
									<input name="newsman_developer_use_as_unsubscribe" type="checkbox"
										id="newsman_developer_use_as_unsubscribe" <?php echo ( ! empty( $form_values['newsman_developer_use_as_unsubscribe'] ) && 'on' === $form_values['newsman_developer_use_as_unsubscribe'] ) ? 'checked' : ''; ?>/>
									<p class="description"><?php echo esc_html__( 'On storefront use action scheduler for unsubscribe from email lists action.', 'newsman' ); ?></p>
								</td>
							</tr>
							<?php endif; ?>
						<?php endif; ?>
					</table>
					<div style="padding-top: 5px;">
						<input type="submit" value="<?php echo esc_attr__( 'Save Changes', 'newsman' ); ?>" class="button button-primary"/>
					</div>
				</form>
			</div>
		</section>
	</div>
</div>
