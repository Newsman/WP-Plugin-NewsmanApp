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
$form_values = $this->get_form_values();
?>
<div class="tabset-img">
	<a href="https://newsman.com" target="_blank">
		<img src="<?php echo esc_url( NEWSMAN_PLUGIN_URL ); ?>src/img/logo.png" alt="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" title="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" />
	</a>
</div>
<div class="tabset">
	<input type="radio" name="tabset" id="tabNewsman" aria-controls="">
	<label for="tabNewsman" id="newsmanBtn"><?php echo esc_html__( 'NewsMAN', 'newsman' ); ?></label>
	<input type="radio" name="tabset" id="tabSync" aria-controls="">
	<label for="tabSync" id="syncBtn"><?php echo esc_html__( 'Sync', 'newsman' ); ?></label>
	<input type="radio" name="tabset" id="tabRemarketing" aria-controls="" checked>
	<label for="tabRemarketing" id="remarketingBtn"><?php echo esc_html__( 'Remarketing', 'newsman' ); ?></label>
	<?php if ( $this->is_woo_commerce_exists() ) : ?>
		<input type="radio" name="tabset" id="tabSms" aria-controls="">
		<label for="tabSms" id="smsBtn"><?php echo esc_html__( 'SMS', 'newsman' ); ?></label>
	<?php endif; ?>
	<input type="radio" name="tabset" id="tabSettings" aria-controls="">
	<label for="tabSettings" id="settingsBtn"><?php echo esc_html__( 'Settings', 'newsman' ); ?></label>
	<div class="tab-panels">
	<section id="tabRemarketing" class="tab-panel">
		<div class="wrap wrap-settings-admin-page">
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?page=NewsmanRemarketing' ) ); ?>">
			<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $this->new_nonce ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $this->form_id ); ?>" value="Y" />
			<h2>Remarketing</h2>
			<?php foreach ( $this->get_backend_messages() as $message ) : ?>
				<div class="<?php echo ( is_array( $message ) && isset( $message['status'] ) ) ? esc_attr( $message['status'] ) : ''; ?>">
					<p>
						<strong><?php echo ( is_array( $message ) && isset( $message['message'] ) ) ? esc_attr( $message['message'] ) : ''; ?></strong>
					</p>
				</div>
			<?php endforeach; ?>
			<?php if ( ! $this->valid_credentials ) { ?>
				<div class="error"><p><strong><?php esc_html_e( 'Invalid credentials!' ); ?></strong></p></div>
			<?php } ?>
			<table class="form-table newsman-table newsman-tbl-fixed">
				<tr>
					<th scope="row">
						<label class="nzm-label" for="newsman_useremarketing"><?php echo esc_html__( 'Enable', 'newsman' ); ?></label>
					</th>
					<td>
						<input name="newsman_useremarketing" type="checkbox"
							id="newsman_useremarketing" <?php echo ( ! empty( $form_values['newsman_useremarketing'] ) && 'on' === $form_values['newsman_useremarketing'] ) ? 'checked' : ''; ?>/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label class="nzm-label" for="newsman_remarketingid"><?php echo esc_html__( 'REMARKETING ID', 'newsman' ); ?></label>
					</th>
					<td>
						<input type="text" name="newsman_remarketingid" id="newsman_remarketingid" value="<?php echo esc_html( $form_values['newsman_remarketingid'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Your NewsMAN Remarketing ID', 'newsman' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label class="nzm-label" for="newsman_remarketinganonymizeip"><?php echo esc_html__( 'Anonymize IP', 'newsman' ); ?></label>
					</th>
					<td>
						<input name="newsman_remarketinganonymizeip" type="checkbox"
							id="newsman_remarketinganonymizeip" <?php echo ( ! empty( $form_values['newsman_remarketinganonymizeip'] ) && 'on' === $form_values['newsman_remarketinganonymizeip'] ) ? 'checked' : ''; ?>/>
						<p class="description"><?php echo esc_html__( 'Anonymize User IP Address', 'newsman' ); ?></p>
					</td>
				</tr>
				<?php if ( $this->is_woo_commerce_exists() ) : ?>
					<tr>
						<th scope="row">
							<label class="nzm-label" for="newsman_remarketingsendtelephone"><?php echo esc_html__( 'Send telephone numbers', 'newsman' ); ?></label>
						</th>
						<td>
							<input name="newsman_remarketingsendtelephone" type="checkbox"
								id="newsman_remarketingsendtelephone" <?php echo ( ! empty( $form_values['newsman_remarketingsendtelephone'] ) && 'on' === $form_values['newsman_remarketingsendtelephone'] ) ? 'checked' : ''; ?>/>
							<p class="description"><?php echo esc_html__( 'Send subscribers (e-mail lists) telephone numbers and telephone numbers of customers that made orders.', 'newsman' ); ?></p>
						</td>
					</tr>
					<?php if ( $this->is_action_scheduler_exists() ) : ?>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_remarketingexportwordpresssubscribers"><?php echo esc_html__( 'Export WordPress Subscribers', 'newsman' ); ?></label>
							</th>
							<td>
								<input name="newsman_remarketingexportwordpresssubscribers" type="checkbox"
										id="newsman_remarketingexportwordpresssubscribers" <?php echo ( ! empty( $form_values['newsman_remarketingexportwordpresssubscribers'] ) && 'on' === $form_values['newsman_remarketingexportwordpresssubscribers'] ) ? 'checked' : ''; ?>/>
								<p class="description"><?php echo esc_html__( 'Export all WordPress users with role subscriber on regular basis (using Woo Commerce Action Scheduler).', 'newsman' ); ?></p>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $form_values['newsman_remarketingexportwordpresssubscribers'] ) && 'on' === $form_values['newsman_remarketingexportwordpresssubscribers'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row" class="nzm-child-config">
								<label class="nzm-label" for="newsman_remarketingexportwordpresssubscribers_recurring_short_days"><?php echo esc_html__( 'Latest Short Period in Days', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="number" step="1" id="newsman_remarketingexportwordpresssubscribers_recurring_short_days"
										name="newsman_remarketingexportwordpresssubscribers_recurring_short_days"
										value="<?php echo ( ! empty( $form_values['newsman_remarketingexportwordpresssubscribers_recurring_short_days'] ) ) ? esc_attr( $form_values['newsman_remarketingexportwordpresssubscribers_recurring_short_days'] ) : ''; ?>"/>
								<?php
								$scheduler          = new \Newsman\Scheduler\Export\Recurring\SubscribersWordpress();
								$repeating_interval = $scheduler->get_recurring_short_interval();
								?>
								<?php /* translators: 1: Time Interval */ ?>
								<p class="description"><?php printf( esc_html__( 'Export latest WordPress subscribers registered in last X days every %d hours.', 'newsman' ), esc_html( $repeating_interval ) ); ?></p>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $form_values['newsman_remarketingexportwordpresssubscribers'] ) && 'on' === $form_values['newsman_remarketingexportwordpresssubscribers'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row" class="nzm-child-config">
								<label class="nzm-label" for="newsman_remarketingexportwordpresssubscribers_recurring_long_days"><?php echo esc_html__( 'Latest Long Period in Days', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="number" step="1" id="newsman_remarketingexportwordpresssubscribers_recurring_long_days"
										name="newsman_remarketingexportwordpresssubscribers_recurring_long_days"
										value="<?php echo ( ! empty( $form_values['newsman_remarketingexportwordpresssubscribers_recurring_long_days'] ) ) ? esc_attr( $form_values['newsman_remarketingexportwordpresssubscribers_recurring_long_days'] ) : ''; ?>"/>
								<?php
								$scheduler          = new \Newsman\Scheduler\Export\Recurring\SubscribersWordpress();
								$repeating_interval = $scheduler->get_recurring_long_interval();
								?>
								<?php /* translators: 1: Time Interval */ ?>
								<p class="description"><?php printf( esc_html__( 'Export latest WordPress subscribers registered in last X days every %d days.', 'newsman' ), esc_html( $repeating_interval / 24 ) ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_remarketingexportwoocommercesubscribers"><?php echo esc_html__( 'Export WooCommerce Buyers as Subscribers', 'newsman' ); ?></label>
							</th>
							<td>
								<input name="newsman_remarketingexportwoocommercesubscribers" type="checkbox"
										id="newsman_remarketingexportwoocommercesubscribers" <?php echo ( ! empty( $form_values['newsman_remarketingexportwoocommercesubscribers'] ) && 'on' === $form_values['newsman_remarketingexportwoocommercesubscribers'] ) ? 'checked' : ''; ?>/>
								<p class="description"><?php echo esc_html__( 'Export all buyers from orders with status complete on regular basis (using Woo Commerce Action Scheduler).', 'newsman' ); ?></p>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $form_values['newsman_remarketingexportwoocommercesubscribers'] ) && 'on' === $form_values['newsman_remarketingexportwoocommercesubscribers'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row" class="nzm-child-config">
								<label class="nzm-label" for="newsman_remarketingexportwoocommercesubscribers_recurring_short_days"><?php echo esc_html__( 'Latest Short Period in Days', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="number" step="1" id="newsman_remarketingexportwoocommercesubscribers_recurring_short_days"
										name="newsman_remarketingexportwoocommercesubscribers_recurring_short_days"
										value="<?php echo ( ! empty( $form_values['newsman_remarketingexportwoocommercesubscribers_recurring_short_days'] ) ) ? esc_attr( $form_values['newsman_remarketingexportwoocommercesubscribers_recurring_short_days'] ) : ''; ?>"/>
								<?php
								$scheduler          = new \Newsman\Scheduler\Export\Recurring\SubscribersWoocommerce();
								$repeating_interval = $scheduler->get_recurring_short_interval();
								?>
								<?php /* translators: 1: Time Interval */ ?>
								<p class="description"><?php printf( esc_html__( 'Export latest buyers from orders created in the last X days every %d hours.', 'newsman' ), esc_html( $repeating_interval ) ); ?></p>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $form_values['newsman_remarketingexportwoocommercesubscribers'] ) && 'on' === $form_values['newsman_remarketingexportwoocommercesubscribers'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row" class="nzm-child-config">
								<label class="nzm-label" for="newsman_remarketingexportwoocommercesubscribers_recurring_long_days"><?php echo esc_html__( 'Latest Long Period in Days', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="number" step="1" id="newsman_remarketingexportwoocommercesubscribers_recurring_long_days"
										name="newsman_remarketingexportwoocommercesubscribers_recurring_long_days"
										value="<?php echo ( ! empty( $form_values['newsman_remarketingexportwoocommercesubscribers_recurring_long_days'] ) ) ? esc_attr( $form_values['newsman_remarketingexportwoocommercesubscribers_recurring_long_days'] ) : ''; ?>"/>
								<?php
								$scheduler          = new \Newsman\Scheduler\Export\Recurring\SubscribersWoocommerce();
								$repeating_interval = $scheduler->get_recurring_long_interval();
								?>
								<?php /* translators: 1: Time Interval */ ?>
								<p class="description"><?php printf( esc_html__( 'Export latest buyers from orders created in the last X days every %d days.', 'newsman' ), esc_html( $repeating_interval / 24 ) ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_remarketingexportorders"><?php echo esc_html__( 'Export Orders', 'newsman' ); ?></label>
							</th>
							<td>
								<input name="newsman_remarketingexportorders" type="checkbox"
										id="newsman_remarketingexportorders" <?php echo ( ! empty( $form_values['newsman_remarketingexportorders'] ) && 'on' === $form_values['newsman_remarketingexportorders'] ) ? 'checked' : ''; ?>/>
								<p class="description"><?php echo esc_html__( 'Export orders on regular basis (using Woo Commerce Action Scheduler).', 'newsman' ); ?></p>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $form_values['newsman_remarketingexportorders'] ) && 'on' === $form_values['newsman_remarketingexportorders'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row" class="nzm-child-config">
								<label class="nzm-label" for="newsman_remarketingexportorders_recurring_short_days"><?php echo esc_html__( 'Latest Short Period in Days', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="number" step="1" id="newsman_remarketingexportorders_recurring_short_days"
										name="newsman_remarketingexportorders_recurring_short_days"
										value="<?php echo ( ! empty( $form_values['newsman_remarketingexportorders_recurring_short_days'] ) ) ? esc_attr( $form_values['newsman_remarketingexportorders_recurring_short_days'] ) : ''; ?>"/>
								<?php
								$scheduler          = new \Newsman\Scheduler\Export\Recurring\Orders();
								$repeating_interval = $scheduler->get_recurring_short_interval();
								?>
								<?php /* translators: 1: Time Interval */ ?>
								<p class="description"><?php printf( esc_html__( 'Export latest orders created in the last X days every %d hours.', 'newsman' ), esc_html( $repeating_interval ) ); ?></p>
							</td>
						</tr>
						<tr style="display: <?php echo ( ! empty( $form_values['newsman_remarketingexportorders'] ) && 'on' === $form_values['newsman_remarketingexportorders'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row" class="nzm-child-config">
								<label class="nzm-label" for="newsman_remarketingexportorders_recurring_long_days"><?php echo esc_html__( 'Latest Long Period in Days', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="number" step="1" id="newsman_remarketingexportorders_recurring_long_days"
										name="newsman_remarketingexportorders_recurring_long_days"
										value="<?php echo ( ! empty( $form_values['newsman_remarketingexportorders_recurring_long_days'] ) ) ? esc_attr( $form_values['newsman_remarketingexportorders_recurring_long_days'] ) : ''; ?>"/>
								<?php
								$scheduler          = new \Newsman\Scheduler\Export\Recurring\Orders();
								$repeating_interval = $scheduler->get_recurring_long_interval();
								?>
								<?php /* translators: 1: Time Interval */ ?>
								<p class="description"><?php printf( esc_html__( 'Export latest orders created in the last X days every %d days.', 'newsman' ), esc_html( $repeating_interval / 24 ) ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_remarketingorderdate"><?php echo esc_html__( 'Export Orders After Date', 'newsman' ); ?></label>
							</th>
							<td>
								<input class="nzm-small-input" type="text" name="newsman_remarketingorderdate" id="newsman_remarketingorderdate" value="<?php echo esc_html( $form_values['newsman_remarketingorderdate'] ); ?>" />
								<p class="description"><?php echo esc_html__( 'Export orders created after a specific date (including). Format: YYYY-MM-DD', 'newsman' ); ?></p>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row">
							<label class="nzm-label" for="newsman_remarketingordersave"><?php echo esc_html__( 'Export Orders on Status Change', 'newsman' ); ?></label>
						</th>
						<td>
							<select class="nzm-multiple-select" name="newsman_remarketingordersave[]" id="newsman_remarketingordersave" multiple="multiple">
								<?php
								$all_order_statuses   = wc_get_order_statuses();
								$saved_order_statuses = isset( $form_values['newsman_remarketingordersave'] ) ?
									(array) $form_values['newsman_remarketingordersave'] : array();

								if ( ! empty( $all_order_statuses ) ) {
									foreach ( $all_order_statuses as $status_key => $status_label ) {
										$selected = in_array( $status_key, $saved_order_statuses, true ) ? 'selected' : '';
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo '<option value="' . esc_attr( $status_key ) . '" ' . $selected . '>' .
											esc_html( $status_label ) . '</option>';
									}
								}
								?>
							</select>
							<p class="description"><?php echo esc_html__( 'Select order statuses. On each selected order status change, the order details will be sent to NewsMAN.', 'newsman' ); ?></p>
						</td>
					</tr>
					<?php if ( function_exists( 'wc_get_attribute_taxonomies' ) ) : ?>
						<tr>
							<th scope="row">
								<label class="nzm-label" for="newsman_remarketingproductattributes"><?php echo esc_html__( 'Export Additional Product Attributes', 'newsman' ); ?></label>
							</th>
							<td>
								<select class="nzm-multiple-select" name="newsman_remarketingproductattributes[]" id="newsman_remarketingproductattributes" multiple="multiple">
									<?php
									$attribute_taxonomies = wc_get_attribute_taxonomies();
									$saved_attributes     = isset( $form_values['newsman_remarketingproductattributes'] ) ?
										(array) $form_values['newsman_remarketingproductattributes'] : array();

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
								<p class="description"><?php echo esc_html__( 'Select multiple product attributes to include in your product feed.', 'newsman' ); ?></p>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row">
							<label class="nzm-label" for="newsman_remarketingcustomerattributes"><?php echo esc_html__( 'Customer Attributes', 'newsman' ); ?></label>
						</th>
						<td>
							<select class="nzm-multiple-select" name="newsman_remarketingcustomerattributes[]" id="newsman_remarketingcustomerattributes" multiple="multiple">
								<?php
								$saved_customer_attributes = isset( $form_values['newsman_remarketingcustomerattributes'] ) ?
									(array) $form_values['newsman_remarketingcustomerattributes'] : array();

								foreach ( $this->get_customer_attributes() as $key => $label ) {
									$selected = in_array( $key, $saved_customer_attributes, true ) ? 'selected' : '';
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo '<option value="' . esc_attr( $key ) . '" ' . $selected . '>' .
										esc_html( $label ) . '</option>';
								}
								?>
							</select>
							<p class="description"><?php echo esc_html__( 'Select which customer attributes (from orders) to include in your remarketing data.', 'newsman' ); ?></p>
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
