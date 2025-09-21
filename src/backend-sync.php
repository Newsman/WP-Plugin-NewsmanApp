<?php
/**
 * Title: Newsman admin sync list, segment and SMS list
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var Newsman_Admin_Settings_Sync $this
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
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" alt="NewsMAN" />
	</a>
</div>
<div class="tabset">
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="tabSync" aria-controls="" checked>
	<label for="tabSync" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<div class="tab-panels">
		<section id="tabSync" class="tab-panel">
			<div class="wrap wrap-settings-admin-page">
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>" />
					<input type="hidden" name="<?php echo esc_attr( $this->form_id ); ?>" value="Y" />
					<h2>Sync</h2>
					<div class="<?php echo ( is_array( $this->message ) && isset( $this->message['status'] ) ) ? esc_attr( $this->message['status'] ) : ''; ?>"><p><strong><?php echo ( is_array( $this->message ) && isset( $this->message['message'] ) ) ? esc_html( $this->message['message'] ) : ''; ?></strong>
						</p></div>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_list">Select a list</label>
							</th>
							<td>
								<select name="newsman_list" id="newsman_list">
									<option value="0">-- select list --</option>
									<?php
									if ( ! empty( $this->available_lists ) ) {
										foreach ( $this->available_lists as $item ) {
											?>
										<option value="<?php echo esc_attr( $item['list_id'] ); ?>" <?php echo ( strval( $item['list_id'] ) === strval( $this->form_values['newsman_list'] ) ) ? "selected = ''" : ''; ?>><?php echo esc_html( $item['list_name'] ); ?></option>
											<?php
										}
									}
									?>
								</select>
								<p class="description"><?php echo esc_html__( 'Select a list of subscribers.', 'newsman' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_segments">Select a segment</label>
							</th>
							<td>
								<select name="newsman_segments" id="newsman_segments">
									<option value="0">-- select segment (optional) --</option>
									<?php
									if ( ! empty( $this->available_segments ) ) {
										foreach ( $this->available_segments as $item ) {
											?>
										<option value="<?php echo esc_attr( $item['segment_id'] ); ?>" <?php echo ( strval( $item['segment_id'] ) === strval( $this->form_values['newsman_segments'] ) ) ? "selected = ''" : ''; ?>><?php echo esc_html( $item['segment_name'] ); ?></option>
											<?php
										}
									}
									?>
								</select>
								<p class="description"><?php echo esc_html__( 'Select a segment of subscribers.', 'newsman' ); ?> <?php echo esc_html__( 'The dropdown has the updated segments as options after the new list was saved.', 'newsman' ); ?></p>
								<p class="description"><?php echo esc_html__( 'Please save the segment after the list ID was changed.' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_smslist">Select an SMS list</label>
							</th>
							<td>
								<select name="newsman_smslist" id="newsman_smslist">
									<option value="0">-- select list --</option>
									<?php
									if ( ! empty( $this->available_sms_lists ) ) {
										foreach ( $this->available_sms_lists as $item ) {
											?>
										<option value="<?php echo esc_attr( $item['list_id'] ); ?>" <?php echo ( strval( $item['list_id'] ) === $this->form_values['newsman_smslist'] ) ? "selected = ''" : ''; ?>><?php echo esc_html( $item['list_name'] ); ?></option>
											<?php
										}
									}
									?>
								</select>
								<p class="description">Select a list of SMS to be synced with phone numbers</p>
							</td>
						</tr>
						<tr>
							<th>
								SYNC via CRON Job (Task scheduler)
								<p class="newsman-paragraph">click the links to begin Sync or setup task scheduler (cron) on your server/hosting<p>
								<br><br>
								<p class="newsman-paragraph">{{limit}} = Sync with newsman from latest number of records (ex: 5000)</p>
							</th>
							<td>
								<?php
									$wordpress_url   = get_site_url() . '/?newsman=cron.json&method=wordpress&nzmhash=' . $this->get_config()->get_api_key() . '&start=1&limit=5000&cronlast=true';
									$woocommerce_url = get_site_url() . '/?newsman=cron.json&method=woocommerce&nzmhash=' . $this->get_config()->get_api_key() . '&start=1&limit=5000&cronlast=true';

									echo "CRON url Sync WordPress subscribers: <a href='" . esc_url( $wordpress_url ) . "' target='_blank'>" . esc_html( $wordpress_url ) . '</a>';
									echo '<br><br>';
									echo "CRON url Sync customers with orders completed: <a href='" . esc_url( $woocommerce_url ) . "' target='_blank'>" . esc_html( $woocommerce_url ) . '</a>';
								?>
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
