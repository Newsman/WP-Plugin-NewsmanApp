<?php
/**
 * Title: Newsman OAuth wizard
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Admin\Settings\Oauth $this
 */

$this->is_oauth( true );

if ( ! $this->validate_nonce( array( $this->form_id, $this->form_id_step_two, 'error', 'code' ) ) ) {
	wp_nonce_ays( $this->nonce_action );
	return;
}
$this->create_nonce();

$this->process_forms();
?>
<div class="tabset-img">
	<a href="https://newsman.com" target="_blank">
		<img src="<?php echo esc_url( NEWSMAN_PLUGIN_URL ); ?>src/img/logo.png" alt="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" title="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" />
	</a>
</div>
<div class="tabset">
	<div class="tab-panels">
		<section id="tab-oauth" class="tab-panel">
			<?php
			if ( ! empty( $this->form_error_message ) ) {
				?>
				<div class="error"><p><strong><?php echo esc_html( $this->form_error_message ); ?></strong></p></div>
			<?php } ?>
			<div class="wrap wrap-settings-admin-page">
				<h2><?php echo esc_html__( 'NewsMAN plugin for Wordpress-Woocommerce', 'newsman' ); ?></h2>
				<?php // OAuth step 1. ?>
				<?php if ( 1 === $this->step ) { ?>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?page=NewsmanOauth' ) ); ?>">
						<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $this->form_id ); ?>" value="Y" />
						<table class="form-table newsman-table newsman-tbl-fixed newsman-oauth">
							<tr>
								<td>
									<p class="description"><b><?php echo esc_html__( 'Connect your site with NewsMAN for:', 'newsman' ); ?></b></p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- <?php echo esc_html__( 'Subscribers Sync', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- <?php echo esc_html__( 'Ecommerce Remarketing', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- <?php echo esc_html__( 'Create and manage forms', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- <?php echo esc_html__( 'Create and manage popups', 'newsman' ); ?></p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- <?php echo esc_html__( 'Connect your forms to automation', 'newsman' ); ?></p>
								</td>
							</tr>
						</table>
						<div style="padding-top: 5px;">
							<a style="background: #ad0100" href="<?php echo esc_url( $this->get_oauth_url() ); ?>"
								class="button button-primary"><?php echo esc_html__( 'Login with NewsMAN', 'newsman' ); ?></a>
						</div>
					</form>
					<?php // List step 2. ?>
				<?php } elseif ( 2 === $this->step ) { ?>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?page=NewsmanOauth' ) ); ?>">
						<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $this->form_id_step_two ); ?>" value="Y" />
						<input type="hidden" name="creds" value='<?php echo wp_json_encode( $this->view_state['creds'] ); ?>'/>
						<table class="form-table newsman-table newsman-tbl-fixed newsman-oauth">
							<tr>
								<td>
									<label for="newsman_list" style="display: none;"><?php echo esc_html__( 'Select list:', 'newsman' ); ?></label>
									<select name="newsman_list" id="newsman_list">
										<option value="0"><?php echo esc_html__( '-- select list --', 'newsman' ); ?></option>
										<?php
										foreach ( $this->response_lists as $item ) {
											if ( 'sms' === $item['type'] ) {
												continue;
											}
											?>
											<option 
												value="<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['name'] ); ?></option>
										<?php } ?>
									</select>
								</td>
							</tr>
						</table>
						<div style="padding-top: 5px;">
							<button type="submit" style="background: #ad0100" class="button button-primary"><?php echo esc_html__( 'Save', 'newsman' ); ?></button>
						</div>
					</form>
				<?php } ?>
			</div>
		</section>
	</div>
</div>
