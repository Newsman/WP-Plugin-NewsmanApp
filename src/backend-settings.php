<?php
/**
 * Title: Newsman admin options
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var Newsman_Admin_Settings_Settings $this
 */

$this->is_oauth();

$nonce_action = 'newsman-settings-general';
$test_nonce   = '';
if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
	$test_nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
}

if ( ! empty( $test_nonce ) || isset( $_POST['newsman_submit'] ) ) {
	if ( ! wp_verify_nonce( $test_nonce, $nonce_action ) ) {
		wp_nonce_ays( $nonce_action );

		return;
	}
}

$local_nonce = wp_create_nonce( $nonce_action );
wp_nonce_field( $nonce_action, '_wpnonce', false );

$local_newsman_submit = '';
if ( isset( $_POST['newsman_submit'] ) && ! empty( $_POST['newsman_submit'] ) ) {
	$local_newsman_submit = sanitize_text_field( wp_unslash( $_POST['newsman_submit'] ) );
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html( __( 'Unauthorized user', 'newsman' ) ) );
}

$local_options       = array();
$local_options_names = array(
	'newsman_userid',
	'newsman_apikey',
	'newsman_api',
	'newsman_checkoutsms',
	'newsman_checkoutnewsletter',
	'newsman_checkoutnewslettertype',
	'newsman_checkoutnewslettermessage',
	'newsman_checkoutnewsletterdefault',
	'newsman_form_id',
	'newsman_developerlogseverity',
	'newsman_developerapitimeout',
);

if ( ! empty( $local_newsman_submit ) && 'Y' === $local_newsman_submit ) {
	foreach ( $local_options_names as $local_option_name ) {
		$local_options[ $local_option_name ] = '';
		if ( isset( $_POST[ $local_option_name ] ) ) {
			$local_options[ $local_option_name ] = sanitize_text_field( wp_unslash( $_POST[ $local_option_name ] ) );
		}
	}

	foreach ( $local_options as $local_option_name => $local_option_value ) {
		if ( 'newsman_userid' === $local_option_name ) {
			update_option( 'newsman_userid', $local_options_names['newsman_userid'] );
		} elseif ( 'newsman_apikey' === $local_option_name ) {
			update_option( 'newsman_apikey', $local_options_names['newsman_apikey'] );
		} else {
			update_option( $local_option_name, $local_option_value );
		}
	}

	$this->is_oauth();

	try {
		$available_lists = $this->retrieve_api_all_lists( $local_options_names['newsman_userid'], $local_options_names['newsman_apikey'] );

		$available_segments = array();
		if ( ! empty( $list ) ) {
			$available_segments = $this->retrieve_api_all_segments( $list );
		}

		$this->set_message_backend( 'updated', 'Options saved.' );
	} catch ( Exception $e ) {
		$this->set_message_backend( 'error', 'Invalid Credentials' );
	}
} else {
	foreach ( $local_options_names as $local_option_name ) {
		$local_options[ $local_option_name ] = get_option( $local_option_name );
	}

	try {
		$available_lists = $this->retrieve_api_all_lists();

		$available_segments = array();
		if ( ! empty( $list ) ) {
			$available_segments = $this->retrieve_api_all_segments( $list );
		}
	} catch ( Exception $e ) {
		$this->set_message_backend( 'error', 'Invalid Credentials' );
	}
}

?>
<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png"/>
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
	<!--<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>-->
	<div class="tab-panels">
		<section id="tabSettings" class="tab-panel">
			<div class="wrap wrap-settings-admin-page">
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $local_nonce ); ?>"/>
					<input type="hidden" name="newsman_submit" value="Y"/>

					<div class="<?php echo ( is_array( $this->message ) && isset( $this->message['status'] ) ) ? esc_attr( $this->message['status'] ) : ''; ?>">
						<p>
							<strong><?php echo ( is_array( $this->message ) && isset( $this->message['message'] ) ) ? esc_attr( $this->message['message'] ) : ''; ?></strong>
						</p></div>

					<h2>Newsman Connection</h2>
					<table class="form-table newsmanTable newsmanTblFixed">
						<tr>
							<th scope="row">
								<label for="newsman_apikey">API KEY</label>
							</th>
							<td>
								<input type="text" name="newsman_apikey"
									value="<?php echo esc_attr( $local_options['newsman_apikey'] ); ?>"/>
								<p class="description">Your Newsman API KEY</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="newsman_userid">User ID</label>
							</th>
							<td>
								<input type="text" name="newsman_userid"
									value="<?php echo esc_attr( $local_options['newsman_userid'] ); ?>"/>
								<p class="description">Your Newsman User ID</p>
							</td>
						</tr>
					</table>

					<h2>Settings</h2>
					<table class="form-table newsmanTable newsmanTblFixed">
						<tr>
							<th scope="row">
								<label for="newsman_api">Allow API access</label>
							</th>
							<td>
								<input name="newsman_api" type="checkbox"
									id="newsman_api" <?php echo ( ! empty( $local_options['newsman_api'] ) && 'on' === $local_options['newsman_api'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="newsman_checkoutnewsletter">Checkout newsletter subscribe checkbox</label>
							</th>
							<td>
								<input name="newsman_checkoutnewsletter" type="checkbox"
									id="newsman_checkoutnewsletter" <?php echo ( ! empty( $local_options['newsman_checkoutnewsletter'] ) && 'on' === $local_options['newsman_checkoutnewsletter'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="newsman_checkoutsms">Checkout SMS, sync phone numbers to your SMS
									list</label>
							</th>
							<td>
								<input name="newsman_checkoutsms" type="checkbox"
									id="newsman_checkoutsms" <?php echo ( ! empty( $local_options['newsman_checkoutsms'] ) && 'on' === $local_options['newsman_checkoutsms'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr class="newsman_checkoutnewslettertypePanel"
							style="display: <?php echo ( ! empty( $local_options['newsman_checkoutnewsletter'] ) && 'on' === $local_options['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label for="newsman_checkoutnewslettertype">Checkout newsletter subscribe checkbox event
									type</label>
							</th>
							<td>
								<select name="newsman_checkoutnewslettertype" id="">
									<option value="save" <?php echo ( 'save' === $local_options['newsman_checkoutnewslettertype'] ) ? "selected = ''" : ''; ?>>
										Subscribes a subscriber to the list
									</option>
									<option value="init" <?php echo ( 'init' === $local_options['newsman_checkoutnewslettertype'] ) ? "selected = ''" : ''; ?>>
										Inits a confirmed opt in subscribe to the list
									</option>
								</select>
							</td>
						</tr>
						<tr class="newsman_checkoutnewslettertypePanel"
							style="display: <?php echo ( ! empty( $local_options['newsman_checkoutnewsletter'] ) && 'on' === $local_options['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label for="newsman_checkoutnewslettermessage">Checkout newsletter subscribe checkbox
									message</label>
							</th>
							<td>
								<input type="text" id="newsman_checkoutnewslettermessage"
									name="newsman_checkoutnewslettermessage"
									value="<?php echo ( ! empty( $local_options['newsman_checkoutnewslettermessage'] ) ) ? esc_attr( $local_options['newsman_checkoutnewslettermessage'] ) : 'Subscribe to our newsletter'; ?>"/>
							</td>
						</tr>
						<tr class="newsman_checkoutnewslettertypePanel"
							style="display: <?php echo ( ! empty( $local_options['newsman_checkoutnewsletter'] ) && 'on' === $local_options['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label for="newsman_checkoutnewsletterdefault">Checkout newsletter subscribe checkbox
									checked by default</label>
							</th>
							<td>
								<input name="newsman_checkoutnewsletterdefault" type="checkbox"
									id="newsman_checkoutnewsletterdefault" <?php echo ( ! empty( $local_options['newsman_checkoutnewsletterdefault'] ) && 'on' === $local_options['newsman_checkoutnewsletterdefault'] ) ? 'checked' : ''; ?>/>
							</td>
						</tr>
						<tr class="newsman_checkoutnewslettertypePanel"
							style="display: <?php echo ( ! empty( $local_options['newsman_checkoutnewsletter'] ) && 'on' === $local_options['newsman_checkoutnewsletter'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label for="newsman_form_id">Form_id: the form of the form used for the confirmation
									email / form settings. Forms can be created admin.</label>
							</th>
							<td>
								<input type="text" id="newsman_form_id" name="newsman_form_id"
									value="<?php echo ( ! empty( $local_options['newsman_form_id'] ) ) ? esc_attr( $local_options['newsman_form_id'] ) : ''; ?>"
									placeholder="form id"/>
							</td>
						</tr>
						<th>
						</th>
					</table>

					<h2>Developer</h2>
					<table class="form-table newsmanTable newsmanTblFixed">
						<tr class="newsman_developerlogseverity">
							<th scope="row">
								<label for="newsman_developerlogseverity">Logging level</label>
							</th>
							<td>
								<?php
								// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
								// @see WC_Log_Level::$level_to_severity
								?>
								<select name="newsman_developerlogseverity" id="">
									<option value="2000" <?php echo ( '2000' === $local_options['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>>
										No Logging
									</option>
									<option value="800" 
										<?php echo ( '800' === $local_options['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Emergency', 'woocommerce' ) ); ?>
									</option>
									<option value="700" 
										<?php echo ( '700' === $local_options['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Alert', 'woocommerce' ) ); ?>
									</option>
									<option value="600" 
										<?php echo ( '600' === $local_options['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Critical', 'woocommerce' ) ); ?>
									</option>
									<option value="500" 
										<?php echo ( '500' === $local_options['newsman_developerlogseverity'] || empty( $local_options['newsman_developerlogseverity'] ) ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Error', 'woocommerce' ) ); ?>
									</option>
									<option value="400" 
										<?php echo ( '400' === $local_options['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Warning', 'woocommerce' ) ); ?>
									</option>
									<option value="300" 
										<?php echo ( '300' === $local_options['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Notice', 'woocommerce' ) ); ?>
									</option>
									<option value="200" 
										<?php echo ( '200' === $local_options['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Info', 'woocommerce' ) ); ?>
									</option>
									<option value="100" 
										<?php echo ( '100' === $local_options['newsman_developerlogseverity'] ) ? "selected = ''" : ''; ?>
									>
										<?php echo esc_html( __( 'Debug', 'woocommerce' ) ); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr class="newsman_apiPanel"
							style="display: <?php echo ( ! empty( $local_options['newsman_api'] ) && 'on' === $local_options['newsman_api'] ) ? 'table-row' : 'none'; ?>;">
							<th scope="row">
								<label for="newsman_developerapitimeout">API Timeout</label>
							</th>
							<td>
								<input type="number" step="1" id="newsman_developerapitimeout"
									name="newsman_developerapitimeout"
									value="<?php echo ( ! empty( $local_options['newsman_developerapitimeout'] ) ) ? esc_attr( $local_options['newsman_developerapitimeout'] ) : ''; ?>"
									placeholder="60"/>
							</td>
						</tr>
						<th>
						</th>
					</table>
					<div style="padding-top: 5px;">
						<input type="submit" value="Save Changes" class="button button-primary"/>
					</div>
				</form>
			</div>

		</section>
	</div>
</div>
