<?php
/**
 * Title: Newsman ReMarketing admin options
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var Newsman_Admin_Settings_Remarketing $this
 */

$this->is_oauth();

$nonce_action = 'newsman-settings-remarketing';
$test_nonce   = '';
if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
	$test_nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
}

if ( ! empty( $test_nonce ) || isset( $_POST['newsman_remarketing'] ) ) {
	if ( ! wp_verify_nonce( $test_nonce, $nonce_action ) ) {
		wp_nonce_ays( $nonce_action );
		return;
	}
}

$local_nonce = wp_create_nonce( $nonce_action );
wp_nonce_field( $nonce_action, '_wpnonce', false );

$local_newsman_remarketing = '';
$valid_credential          = true;
if ( isset( $_POST['newsman_remarketing'] ) && ! empty( $_POST['newsman_remarketing'] ) ) {
	$local_newsman_remarketing = sanitize_text_field( wp_unslash( $_POST['newsman_remarketing'] ) );
}
if ( 'Y' === $local_newsman_remarketing ) {
	$remarketingid = '';
	if ( isset( $_POST['newsman_remarketingid'] ) && ! empty( $_POST['newsman_remarketingid'] ) ) {
		$remarketingid = sanitize_text_field( wp_unslash( $_POST['newsman_remarketingid'] ) );
	}

	update_option( 'newsman_remarketingid', $remarketingid );

	try {
		$available_lists = $this->retrieve_api_all_lists();

		$this->set_message_backend( 'updated', 'Options saved.' );
	} catch ( Exception $e ) {
		$valid_credential = false;
		$this->set_message_backend( 'error', 'Invalid Credentials' );
	}
} else {
	$remarketingid = get_option( 'newsman_remarketingid' );

	try {
		$available_lists = $this->retrieve_api_all_lists();

	} catch ( Exception $e ) {
		$valid_credential = false;
		$this->set_message_backend( 'error', 'Invalid Credentials' );
	}
}
?>
<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" />
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
			<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $local_nonce ); ?>" />
			<input type="hidden" name="newsman_remarketing" value="Y"/>
			<h2>Remarketing</h2>
			<div class="<?php echo ( is_array( $this->message ) && isset( $this->message['status'] ) ) ? esc_attr( $this->message['status'] ) : ''; ?>"><p><strong><?php echo ( is_array( $this->message ) && isset( $this->message['message'] ) ) ? esc_html( $this->message['message'] ) : ''; ?></strong></p></div>
			<?php
			if ( ! $valid_credential ) {
				?>
				<div class="error"><p><strong><?php esc_html_e( 'Invalid credentials!' ); ?></strong></p></div>
			<?php } ?>
			<table class="form-table newsmanTable newsmanTblFixed">
				<tr>
					<th scope="row">
						<label for="newsman_remarketingid">REMARKETING ID</label>
					</th>
					<td>
						<input type="text" name="newsman_remarketingid" value="<?php echo esc_html( $remarketingid ); ?>"/>
						<p class="description">Your Newsman Remarketing ID</p>
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
