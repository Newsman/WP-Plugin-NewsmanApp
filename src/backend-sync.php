<?php
/**
 * Title: Newsman admin sync list, segment and SMS list
 *
 * @package NewsmanApp for WordPress
 */

$this->is_oauth();

$nonce_action = 'newsman-settings-sync';
$test_nonce   = '';
if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
	$test_nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
}

if ( ! empty( $test_nonce ) || isset( $_POST['newsman_sync'] ) ) {
	if ( ! wp_verify_nonce( $test_nonce, $nonce_action ) ) {
		wp_nonce_ays( $nonce_action );
		return;
	}
}

$local_nonce = wp_create_nonce( $nonce_action );
wp_nonce_field( $nonce_action, '_wpnonce', false );

$local_newsman_sync = '';
if ( isset( $_POST['newsman_sync'] ) && ! empty( $_POST['newsman_sync'] ) ) {
	$local_newsman_sync = sanitize_text_field( wp_unslash( $_POST['newsman_sync'] ) );
}

$local_options       = array();
$local_options_names = array(
	'newsman_list',
	'newsman_smslist',
	'newsman_segments',
);

if ( 'Y' === $local_newsman_sync ) {
	foreach ( $local_options_names as $local_option_name ) {
		$local_options[ $local_option_name ] = '';
		if ( isset( $_POST[ $local_option_name ] ) ) {
			$local_options[ $local_option_name ] = sanitize_text_field( wp_unslash( $_POST[ $local_option_name ] ) );
		}
	}

	$this->construct_client( $this->userid, $this->apikey );

	foreach ( $local_options as $local_option_name => $local_option_value ) {
		if ( 'newsman_userid' === $local_option_name ) {
			update_option( 'newsman_userid', $this->userid );
		} elseif ( 'newsman_apikey' === $local_option_name ) {
			update_option( 'newsman_apikey', $this->apikey );
		} else {
			update_option( $local_option_name, $local_option_value );
		}
	}

	if ( ! empty( $local_options['newsman_list'] ) && class_exists( 'WooCommerce' ) ) {
		$args     = array(
			'stock_status' => 'instock',
		);
		$products = wc_get_products( $args );

		if ( ! empty( $products ) ) {

			$url = get_site_url() . '/?newsman=products.json&nzmhash=' . $this->apikey;

			try {
				$ret = $this->client->feeds->setFeedOnList( $local_options['newsman_list'], $url, get_site_url(), 'NewsMAN' );
			} catch ( Exception $ex ) {
				$this->set_message_backend( 'error', 'Could not update feed list' );
			}
		}
	}

	try {
		$available_lists = $this->client->list->all();

		$available_segments = array();
		if ( ! empty( $local_options['newsman_list'] ) ) {
			$available_segments = $this->client->segment->all( $local_options['newsman_list'] );
		}

		$available_smslists = $this->client->sms->lists();

		$this->set_message_backend( 'updated', 'Options saved.' );
	} catch ( Exception $e ) {
		$this->valid_credential = false;
		$this->set_message_backend( 'error', 'Invalid Credentials' );
	}
} else {
	foreach ( $local_options_names as $local_option_name ) {
		$local_options[ $local_option_name ] = get_option( $local_option_name );
	}

	try {
		$available_lists = $this->client->list->all();

		$available_segments = array();
		if ( ! empty( $local_options['newsman_list'] ) ) {
			$available_segments = $this->client->segment->all( $local_options['newsman_list'] );
		}

		$available_smslists = $this->client->sms->lists();

	} catch ( Exception $e ) {
		$this->valid_credential = false;
		$this->set_message_backend( 'error', $e->getMessage() );
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
	<input type="radio" name="tabset" id="tabSync" aria-controls="" checked>
	<label for="tabSync" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<!--<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="widgetBtn">Widget</label>-->
   
	<div class="tab-panels">
		<section id="tabSync" class="tab-panel">
	  
			<div class="wrap wrap-settings-admin-page">
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $local_nonce ); ?>" />
					<input type="hidden" name="newsman_sync" value="Y"/>		
		
					<h2>Sync</h2>
		
					<div class="<?php echo ( is_array( $this->message ) && isset( $this->message['status'] ) ) ? esc_attr( $this->message['status'] ) : ''; ?>"><p><strong><?php echo ( is_array( $this->message ) && isset( $this->message['message'] ) ) ? esc_html( $this->message['message'] ) : ''; ?></strong>
						</p></div>				
					
					<table class="form-table newsmanTable newsmanTblFixed">			
		
						<tr>
							<th scope="row">
								<label for="newsman_list">Select a list</label>
							</th>
							<td>
								<select name="newsman_list" id="">
									<option value="0">-- select list --</option>
									<?php
									foreach ( $available_lists as $l ) {
										?>
										<option
											value="<?php echo esc_attr( $l['list_id'] ); ?>" <?php echo ( strval( $l['list_id'] ) === strval( $local_options['newsman_list'] ) ) ? "selected = ''" : ''; ?>><?php echo esc_html( $l['list_name'] ); ?></option>
									<?php } ?>
								</select>
								<p class="description">Select a list of subscribers</p>
							</td>
						</tr>
		
						<tr>
							<th scope="row">
								<label for="newsman_smslist">Select an SMS list</label>
							</th>
							<td>
								<select name="newsman_smslist" id="">
									<option value="0">-- select list --</option>
									<?php
									foreach ( $available_smslists as $l ) {
										?>
										<option
											value="<?php echo esc_attr( $l['list_id'] ); ?>" <?php echo ( strval( $l['list_id'] ) === $local_options['newsman_smslist'] ) ? "selected = ''" : ''; ?>><?php echo esc_html( $l['list_name'] ); ?></option>
									<?php } ?>
								</select>
								<p class="description">Select a list of SMS to be synced with phone numbers</p>
							</td>
						</tr>
		
						<tr>
							<th scope="row">
								<label for="newsman_segments">Select a segment</label>
							</th>
							<td>
								<select name="newsman_segments" id="">
									<option value="0">-- select segment (optional) --</option>
									<?php
									foreach ( $available_segments as $l ) {
										?>
										<option
											value="<?php echo esc_attr( $l['segment_id'] ); ?>" <?php echo ( strval( $l['segment_id'] ) === strval( $local_options['newsman_segments'] ) ) ? "selected = ''" : ''; ?>><?php echo esc_html( $l['segment_name'] ); ?></option>
									<?php } ?>
								</select>
								<p class="description">Select a segment</p>
							</td>
						</tr>
		
						<tr>
							<th>
								SYNC via CRON Job (Task scheduler)
								<p class="newsmanP">click the links to begin Sync or setup task scheduler (cron) on your server/hosting<p>
								<br><br>
								<p class="newsmanP">{{limit}} = Sync with newsman from latest number of records (ex: 5000)</p>
							</th>
							<td>
								<?php
									$wordpress_url   = get_site_url() . '/?newsman=cron.json&method=wordpress&nzmhash=' . $this->apikey . '&start=1&limit=5000&cronlast=true';
									$woocommerce_url = get_site_url() . '/?newsman=cron.json&method=woocommerce&nzmhash=' . $this->apikey . '&start=1&limit=5000&cronlast=true';

									echo "CRON url Sync WordPress subscribers: <a href='" . esc_url( $wordpress_url ) . "' target='_blank'>" . esc_html( $wordpress_url ) . '</a>';
									echo '<br><br>';
									echo "CRON url Sync customers with orders completed: <a href='" . esc_url( $woocommerce_url ) . "' target='_blank'>" . esc_html( $woocommerce_url ) . '</a>';
								?>
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
