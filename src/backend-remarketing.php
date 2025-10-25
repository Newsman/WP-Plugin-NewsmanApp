<?php
/**
 * Title: Newsman ReMarketing admin options
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Admin\Settings\Remarketing $this
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
	<input type="radio" name="tabset" id="tabSync" aria-controls="">
	<label for="tabSync" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="tabRemarketing" aria-controls="" checked>
	<label for="tabRemarketing" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<!--<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>-->
	<div class="tab-panels">
	<section id="tabRemarketing" class="tab-panel">
		<div class="wrap wrap-settings-admin-page">
		<form method="post" enctype="multipart/form-data">
			<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $this->form_id ); ?>" value="Y" />
			<h2>Remarketing</h2>
			<div class="<?php echo ( is_array( $this->message ) && isset( $this->message['status'] ) ) ? esc_attr( $this->message['status'] ) : ''; ?>"><p><strong><?php echo ( is_array( $this->message ) && isset( $this->message['message'] ) ) ? esc_html( $this->message['message'] ) : ''; ?></strong></p></div>
			<?php if ( ! $this->valid_credentials ) { ?>
				<div class="error"><p><strong><?php esc_html_e( 'Invalid credentials!' ); ?></strong></p></div>
			<?php } ?>
			<table class="form-table newsman-table newsman-tbl-fixed">
				<tr>
					<th scope="row">
						<label class="nzm-label" for="newsman_useremarketing">Enable</label>
					</th>
					<td>
						<input name="newsman_useremarketing" type="checkbox"
							d="newsman_useremarketing" <?php echo ( ! empty( $this->form_values['newsman_useremarketing'] ) && 'on' === $this->form_values['newsman_useremarketing'] ) ? 'checked' : ''; ?>/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label class="nzm-label" for="newsman_remarketingid">REMARKETING ID</label>
					</th>
					<td>
						<input type="text" name="newsman_remarketingid" id="newsman_remarketingid" value="<?php echo esc_html( $this->form_values['newsman_remarketingid'] ); ?>" />
						<p class="description">Your Newsman Remarketing ID</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label class="nzm-label" for="newsman_remarketinganonymizeip">Anonymize IP</label>
					</th>
					<td>
						<input name="newsman_remarketinganonymizeip" type="checkbox"
							id="newsman_remarketinganonymizeip" <?php echo ( ! empty( $this->form_values['newsman_remarketinganonymizeip'] ) && 'on' === $this->form_values['newsman_remarketinganonymizeip'] ) ? 'checked' : ''; ?>/>
						<p class="description">Anonymize User IP Address</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label class="nzm-label" for="newsman_remarketingsendtelephone">Send telephone numbers</label>
					</th>
					<td>
						<input name="newsman_remarketingsendtelephone" type="checkbox"
							id="newsman_remarketingsendtelephone" <?php echo ( ! empty( $this->form_values['newsman_remarketingsendtelephone'] ) && 'on' === $this->form_values['newsman_remarketingsendtelephone'] ) ? 'checked' : ''; ?>/>
						<p class="description">Send subscribers (e-mail lists) telephone numbers and telephone numbers of customers that made orders.</p>
					</td>
				</tr>
				<?php if ( function_exists( 'wc_get_attribute_taxonomies' ) ) : ?>
					<tr>
						<th scope="row">
							<label class="nzm-label" for="newsman_remarketingproductattributes">Export Additional Product Attributes</label>
						</th>
						<td>
							<select class="nzm-multiple-select" name="newsman_remarketingproductattributes[]" id="newsman_remarketingproductattributes" multiple="multiple">
								<?php
								$attribute_taxonomies = wc_get_attribute_taxonomies();
								$saved_attributes     = isset( $this->form_values['newsman_remarketingproductattributes'] ) ?
									(array) $this->form_values['newsman_remarketingproductattributes'] : array();

								if ( ! empty( $attribute_taxonomies ) ) {
									foreach ( $attribute_taxonomies as $attribute ) {
										$attribute_name = 'pa_' . $attribute->attribute_name;
										$selected       = in_array( $attribute_name, $saved_attributes, true ) ? 'selected' : '';
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo '<option value="' . esc_attr( $attribute_name ) . '" ' . $selected . '>' .
											esc_html( $attribute->attribute_label ) . '</option>';
									}
								}
								?>
							</select>
							<p class="description">Select multiple product attributes to include in your product feed.</p>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row">
						<label class="nzm-label" for="newsman_remarketingcustomerattributes">Customer Attributes</label>
					</th>
					<td>
						<select class="nzm-multiple-select" name="newsman_remarketingcustomerattributes[]" id="newsman_remarketingcustomerattributes" multiple="multiple">
							<?php
							$saved_customer_attributes = isset( $this->form_values['newsman_remarketingcustomerattributes'] ) ?
								(array) $this->form_values['newsman_remarketingcustomerattributes'] : array();

							foreach ( $this->get_customer_attributes() as $key => $label ) {
								$selected = in_array( $key, $saved_customer_attributes, true ) ? 'selected' : '';
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo '<option value="' . esc_attr( $key ) . '" ' . $selected . '>' .
									esc_html( $label ) . '</option>';
							}
							?>
						</select>
						<p class="description">Select which customer attributes (from orders) to include in your remarketing data.</p>
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
