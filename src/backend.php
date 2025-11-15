<?php
/**
 * Title: Newsman admin settings
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Admin\Settings\Newsman $this
 */

$this->is_oauth();
$is_valid_credentials = $this->is_valid_credentials();
?>
<div class="tabset-img">
	<a href="https://newsman.com" target="_blank">
		<img alt="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" title="<?php echo esc_attr__( 'NewsMAN', 'newsman' ); ?>" src="<?php echo esc_url( NEWSMAN_PLUGIN_URL ); ?>src/img/logo.png"/>
	</a>
</div>
<div class="tabset">
	<input type="radio" name="tabset" id="tabNewsman" aria-controls="" checked>
	<label for="tabNewsman" id="newsmanBtn"><?php echo esc_html__( 'NewsMAN', 'newsman' ); ?></label>
	<input type="radio" name="tabset" id="tabSync" aria-controls="">
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
		<section id="tabNewsman" class="tab-panel">
			<div class="wrap">
				<h2><?php echo esc_html__( 'NewsMAN Info', 'newsman' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Credentials Status', 'newsman' ); ?></strong>
						</th>
						<td>
							<div class="credentials-status <?php echo esc_html( $is_valid_credentials ? 'credentials-valid' : 'credentials-invalid' ); ?>">
								<span><?php echo $is_valid_credentials ? esc_html__( 'Valid', 'newsman' ) : esc_html__( 'Invalid', 'newsman' ); ?></span>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Menus', 'newsman' ); ?>:</strong>
						</th>
					</tr>
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Sync', 'newsman' ); ?></strong>
						</th>
						<td>
							<p><?php echo esc_html__( 'You will be able to sync your shop customers and newsletter subscribers with NewsMAN list / segments', 'newsman' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Remarketing', 'newsman' ); ?></strong>
						</th>
						<td>
							<p><?php echo esc_html__( 'Provide a valuable experience to your customer by automating communication with them. Create automatic email flows based on their actions on the site, such as product viewed, added to cart, completed order etc.', 'newsman' ); ?></p>
						</td>
					</tr>
					<?php if ( $this->is_woo_commerce_exists() ) : ?>
					<tr>
						<th scope="row">
							<strong>SMS</strong>
						</th>
						<td>
							<p><?php echo esc_html__( 'SMS (short messages) is one of the most effective ways to get users’ attention. Create and manage campaigns or send transactional SMS directly from NewsMAN platform.', 'newsman' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Settings', 'newsman' ); ?></strong>
						</th>
						<td>
							<p><?php echo esc_html__( 'In the settings page enter your API Key and User id provided by NewsMAN.', 'newsman' ); ?></p>
							<p><?php echo esc_html__( 'After entering and saving valid credentials you will be able to select a list to which subscribers will be added', 'newsman' ); ?>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong><?php echo esc_html__( 'Widget', 'newsman' ); ?></strong>
						</th>
						<td>
							<p><?php echo esc_html__( 'Transform your website visitors into subscribers and customers. Generate easy to integrate pop-ups, embedded signup forms and web layers to convert more.', 'newsman' ); ?></p>
							<p><?php echo esc_html__( 'Log in to your NewsMAN account: Select List -> Settings -> Subscription forms -> Create/Edit form -> Modal window -> Activate modal window for newsletter subscription -> Select embedded form. Copy paste Shortcode newsman_subscribe_widget', 'newsman' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</section>
	</div>
</div>
