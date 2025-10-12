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
		<img alt="Newsman" title="Newsman" src="/wp-content/plugins/newsmanapp/src/img/logo.png"/>
	</a>
</div>
<div class="tabset">
	<input type="radio" name="tabset" id="tabNewsman" aria-controls="" checked>
	<label for="tabNewsman" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<div class="tab-panels">
		<section id="tabNewsman" class="tab-panel">
			<div class="wrap">
				<h2>Newsman Info</h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<strong>Credentials Status</strong>
						</th>
						<td>
							<div class="credentials-status <?php echo esc_html( $is_valid_credentials ? 'credentials-valid' : 'credentials-invalid' ); ?>">
								<span><?php echo $is_valid_credentials ? esc_html__( 'Valid', 'newsman' ) : esc_html__( 'Invalid', 'newsman' ); ?></span>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong>Newsman Menus:</strong>
						</th>
					</tr>
					<tr>
						<th scope="row">
							<strong>Sync</strong>
						</th>
						<td>
							<p>You will be able to sync your shop customers and newsletter subscribers with Newsman list
								/ segments</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong>Remarketing</strong>
						</th>
						<td>
							<p>Provide a valuable experience to your customer by automating communication with them.
								Create automatic email flows based on their actions on the site, such as product viewed,
								added to cart, completed order etc.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong>SMS</strong>
						</th>
						<td>
							<p>SMS (short messages) is one of the most effective ways to get users’ attention. Create
								and manage campaigns or send transactional SMS directly from NewsMAN platform.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong>Settings</strong>
						</th>
						<td>
							<p>In the settings page enter your API Key and User id provided by Newsman.</p>
							<p>After entering and saving valid credentials you will be able to select a list to which
								subscribers
								will be added.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<strong>Widget</strong>
						</th>
						<td>
							<p>Transform your website visitors into subscribers and customers. Generate easy to
								integrate pop-ups, embedded signup forms and web layers to convert more.</p>
							<p>
								Log in to your Newsman account: Select List -> Settings -> Subscription forms ->
								Create/Edit form -> Modal window -> Activate modal window for newsletter subscription ->
								Select embedded form. Copy paste Shortcode newsman_subscribe_widget
							</p>
						</td>
					</tr>
				</table>
			</div>
		</section>
	</div>
</div>
