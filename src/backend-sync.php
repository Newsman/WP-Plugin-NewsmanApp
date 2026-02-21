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
					</table>
					<div style="padding-top: 5px;">
						<input type="submit" value="<?php echo esc_attr__( 'Save Changes', 'newsman' ); ?>" class="button button-primary"/>
					</div>
				</form>
			</div>
		</section>
	</div>
</div>
