<?php
/**
 * Title: Newsman admin sync list, segment and SMS list
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Admin\Settings\Sync $this
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
	<input type="radio" name="tabset" id="tabSync" aria-controls="" checked>
	<label for="tabSync" id="syncBtn"><?php echo esc_html__( 'Sync', 'newsman' ); ?></label>
	<input type="radio" name="tabset" id="tabRemarketing" aria-controls="">
	<label for="tabRemarketing" id="remarketingBtn"><?php echo esc_html__( 'Remarketing', 'newsman' ); ?></label>
	<?php if ( $this->is_woo_commerce_exists() ) : ?>
		<input type="radio" name="tabset" id="tabSms" aria-controls="">
		<label for="tabSms" id="smsBtn"><?php echo esc_html__( 'SMS', 'newsman' ); ?></label>
	<?php endif; ?>
	<input type="radio" name="tabset" id="tabSettings" aria-controls="">
	<label for="tabSettings" id="settingsBtn"><?php echo esc_html__( 'Settings', 'newsman' ); ?></label>
	<div class="tab-panels">
		<section id="tabSync" class="tab-panel">
			<div class="wrap wrap-settings-admin-page">
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?page=NewsmanSync' ) ); ?>">
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>" />
					<input type="hidden" name="<?php echo esc_attr( $this->form_id ); ?>" value="Y" />
					<h2>Sync</h2>
					<?php foreach ( $this->get_backend_messages() as $message ) : ?>
						<div class="<?php echo ( is_array( $message ) && isset( $message['status'] ) ) ? esc_attr( $message['status'] ) : ''; ?>">
							<p>
								<strong><?php echo ( is_array( $message ) && isset( $message['message'] ) ) ? esc_attr( $message['message'] ) : ''; ?></strong>
							</p>
						</div>
					<?php endforeach; ?>
					<table class="form-table newsman-table newsman-tbl-fixed">
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_list"><?php echo esc_html__( 'Select a list', 'newsman' ); ?></label>
							</th>
							<td>
								<select class="nzm-small-select" name="newsman_list" id="newsman_list">
									<option value="0"><?php echo esc_html__( '-- select list --', 'newsman' ); ?></option>
									<?php
									if ( ! empty( $this->available_lists ) ) {
										foreach ( $this->available_lists as $item ) {
											?>
										<option value="<?php echo esc_attr( $item['list_id'] ); ?>" <?php echo ( strval( $item['list_id'] ) === strval( $form_values['newsman_list'] ) ) ? "selected = ''" : ''; ?>><?php echo esc_html( $item['list_name'] ); ?></option>
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
								<label class="nzm-label" for="newsman_segments"><?php echo esc_html__( 'Select a segment', 'newsman' ); ?></label>
							</th>
							<td>
								<select class="nzm-small-select" name="newsman_segments" id="newsman_segments">
									<option value="0"><?php echo esc_html__( '-- select segment (optional) --', 'newsman' ); ?></option>
									<?php
									if ( ! empty( $this->available_segments ) ) {
										foreach ( $this->available_segments as $item ) {
											?>
										<option value="<?php echo esc_attr( $item['segment_id'] ); ?>" <?php echo ( strval( $item['segment_id'] ) === strval( $form_values['newsman_segments'] ) ) ? "selected = ''" : ''; ?>><?php echo esc_html( $item['segment_name'] ); ?></option>
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
								<label class="nzm-label" for="newsman_smslist"><?php echo esc_html__( 'Select an SMS list', 'newsman' ); ?></label>
							</th>
							<td>
								<select class="nzm-small-select"name="newsman_smslist" id="newsman_smslist">
									<option value="0"><?php echo esc_html__( '-- select list --', 'newsman' ); ?></option>
									<?php
									if ( ! empty( $this->available_sms_lists ) ) {
										foreach ( $this->available_sms_lists as $item ) {
											?>
										<option value="<?php echo esc_attr( $item['list_id'] ); ?>" <?php echo ( strval( $item['list_id'] ) === $form_values['newsman_smslist'] ) ? "selected = ''" : ''; ?>><?php echo esc_html( $item['list_name'] ); ?></option>
											<?php
										}
									}
									?>
								</select>
								<p class="description"><?php echo esc_html__( 'Select a list of SMS to be synced with phone numbers', 'newsman' ); ?></p>
							</td>
						</tr>
						<tr>
							<th rowspan="3">
								<?php if ( $this->is_woo_commerce_exists() && $this->is_single_action_schedule() ) : ?>
									<?php echo wp_kses( __( '<em>Optionally and by case</em> synchronize all data.', 'newsman' ), array( 'em' => array() ) ); ?>
									<br><?php echo wp_kses( __( 'Or <em>optionally</em> synchronize data via CRON jobs (Task scheduler).', 'newsman' ), array( 'em' => array() ) ); ?>
									<p class="newsman-paragraph"><?php echo esc_html__( 'Click the export buttons to push all data once.', 'newsman' ); ?>
									<br><?php echo esc_html__( 'Or click', 'newsman' ); ?>
								<?php else : ?>
									<?php echo wp_kses( __( '<em>Optional</em> synchronize via CRON jobs (Task scheduler).', 'newsman' ), array( 'em' => array() ) ); ?>
									<p class="newsman-paragraph"><?php echo esc_html__( 'Click', 'newsman' ); ?>
								<?php endif; ?>
									<?php echo esc_html__( 'the links to begin synchronizing or setup task scheduler (cron) on your server/hosting.', 'newsman' ); ?><p>
								<br><br>
								<p class="newsman-paragraph">{{limit}} = <?php echo esc_html__( 'Synchronize with NewsMAN from latest number of records (ex: 5000)', 'newsman' ); ?></p>
							</th>
							<td>
								<?php if ( $this->is_single_action_schedule() ) : ?>
									<?php
										$schedule_url = $this->get_action_nonce_url( 'newsman_export_wordpress_subscribers', admin_url( 'admin.php?page=NewsmanSync' ) );
									?>
									<?php echo esc_html__( 'Export once all WordPress users with role subscriber (using Woo Commerce Action Scheduler)', 'newsman' ); ?>:
									<br>
									<a style="margin-top: 5px;" href="<?php echo esc_url( $schedule_url ); ?>" class="button button-primary">
										<?php echo esc_html__( 'Schedule Export Subscribers', 'newsman' ); ?>
									</a>
									<br><br>
								<?php endif; ?>
								<?php
								$wordpress_url = get_site_url() . '/?newsman=cron.json&method=wordpress&nzmhash=' . $this->get_config()->get_api_key() . '&start=0&limit=5000&cronlast=true';
								?>
								<?php echo esc_html__( 'CRON url to export WordPress users with role subscriber', 'newsman' ); ?>:
								<br>
								<a href="<?php echo esc_url( $wordpress_url ); ?>" target="_blank"><?php echo esc_html( $wordpress_url ); ?></a>
							</td>
						</tr>
						<?php if ( $this->is_woo_commerce_exists() ) : ?>
							<tr>
								<td>
									<?php if ( $this->is_single_action_schedule() ) : ?>
										<?php
										$schedule_url = $this->get_action_nonce_url( 'newsman_export_subscribers_orders', admin_url( 'admin.php?page=NewsmanSync' ) );
										?>
										<?php echo esc_html__( 'Export once all buyers from orders with status complete (using Woo Commerce Action Scheduler)', 'newsman' ); ?>:
										<br>
										<a style="margin-top: 5px;" href="<?php echo esc_url( $schedule_url ); ?>" class="button button-primary">
											<?php echo esc_html__( 'Schedule Export Customers from Orders', 'newsman' ); ?>
										</a>
										<br><br>
									<?php endif; ?>
									<?php
									$woocommerce_url = get_site_url() . '/?newsman=cron.json&method=woocommerce&nzmhash=' . $this->get_config()->get_api_key() . '&start=0&limit=5000&cronlast=true';
									?>
									<?php echo esc_html__( 'CRON url to export buyers from orders with status complete', 'newsman' ); ?>:
									<br>
									<a href="<?php echo esc_url( $woocommerce_url ); ?>" target="_blank"><?php echo esc_html( $woocommerce_url ); ?></a>
								</td>
							</tr>
							<tr>
								<td>
									<?php if ( $this->is_single_action_schedule() ) : ?>
										<?php
										$schedule_url = $this->get_action_nonce_url( 'newsman_export_orders', admin_url( 'admin.php?page=NewsmanSync' ) );
										?>
										<?php echo esc_html__( 'Export once all orders to NewsMAN after the date set in Remarketing > Export Orders After Date (using Woo Commerce Action Scheduler)', 'newsman' ); ?>:
										<br>
										<a style="margin-top: 5px;" href="<?php echo esc_url( $schedule_url ); ?>" class="button button-primary">
											<?php echo esc_html__( 'Schedule Export Orders', 'newsman' ); ?>
										</a>
										<br><br>
									<?php endif; ?>
									<?php
									$send_orders_url = get_site_url() . '/?newsman=cron.json&method=send-orders&nzmhash=' . $this->get_config()->get_api_key() . '&start=0&limit=100&cronlast=true';
									?>
									<?php echo esc_html__( 'CRON url to send orders to NewsMAN', 'newsman' ); ?>:
									<br>
									<a href="<?php echo esc_url( $send_orders_url ); ?>" target="_blank"><?php echo esc_html( $send_orders_url ); ?></a>
								</td>
							</tr>
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
