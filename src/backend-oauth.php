<?php
/**
 * Title: Newsman OAuth wizard
 *
 * @package NewsmanApp for WordPress
 */

$this->is_oauth( true );

$nonce_action = 'newsman-settings-oauth';
$test_nonce   = '';
if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
	$test_nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
}

if ( ! empty( $test_nonce ) || isset( $_POST['oauthstep2'] ) || isset( $_POST['error'] ) || isset( $_POST['code'] ) ) {
	if ( ! wp_verify_nonce( $test_nonce, $nonce_action ) ) {
		wp_nonce_ays( $nonce_action );
		return;
	}
}

if ( ! isset( $_SERVER['HTTP_HOST'] ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
	return;
}

$local_nonce = wp_create_nonce( $nonce_action );
wp_nonce_field( $nonce_action, '_wpnonce', false );
$oauth_url = 'https://newsman.app/admin/oauth/authorize?response_type=code&client_id=nzmplugin&nzmplugin=Wordpress&scope=api&redirect_uri=' .
			rawurlencode(
				'https://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) .
				sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) .
				( ! empty( $local_nonce ) ? '&_wpnonce=' . $local_nonce : '' )
			);

$local_error = '';
$data_lists  = array();
$step        = 1;
$view_state  = array();

if ( isset( $_GET['error'] ) && ! empty( $_GET['error'] ) ) {
	switch ( $local_error ) {
		case 'access_denied':
			$local_error = 'Access is denied';
			break;
		case 'missing_lists':
			$local_error = 'There are no lists in your NewsMAN account';
			break;
	}
} elseif ( ! empty( $_GET['code'] ) ) {

	$auth_url = 'https://newsman.app/admin/oauth/token';

	$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );

	$redirect = 'https://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) . ( ! empty( $local_nonce ) ? '&_wpnonce=' . $local_nonce : '' );

	$data = array(
		'grant_type'   => 'authorization_code',
		'code'         => $code,
		'client_id'    => 'nzmplugin',
		'redirect_uri' => $redirect,
	);

    // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
	$ch = curl_init( $auth_url );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
	curl_setopt( $ch, CURLOPT_POST, 1 );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
	$response = curl_exec( $ch );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_errno
	if ( curl_errno( $ch ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		$local_error .= 'cURL error: ' . curl_error( $ch );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
	curl_close( $ch );

	if ( false !== $response ) {

		$response = json_decode( $response );

		$view_state['creds'] = wp_json_encode(
			array(
				'newsman_userid' => $response->user_id,
				'newsman_apikey' => $response->access_token,
			)
		);

		foreach ( $response->lists_data as $list => $l ) {
			$data_lists[] = array(
				'id'   => $l->list_id,
				'name' => $l->name,
			);
		}

		$step = 2;
	} else {
		$local_error .= 'Error sending cURL request.';
	}
}

if ( ! empty( $_POST['oauthstep2'] ) && 'Y' === $_POST['oauthstep2'] ) {
	if ( empty( $_POST['newsman_list'] ) || 0 === $_POST['newsman_list'] || '0' === $_POST['newsman_list'] ) {
		$step = 1;
	} else {
		$creds = isset( $_POST['creds'] ) ? sanitize_text_field( wp_unslash( $_POST['creds'] ) ) : '';
		$creds = json_decode( $creds );
		$creds = json_decode( $creds );

		$this->construct_client( $creds->newsman_userid, $creds->newsman_apikey );
		$ret = $this->client->remarketing->getSettings( sanitize_text_field( wp_unslash( $_POST['newsman_list'] ) ) );

		$remarketing_id = $ret['site_id'] . '-' . $ret['list_id'] . '-' . $ret['form_id'] . '-' . $ret['control_list_hash'];

		// Set feed.
		$url = get_site_url() . '/?newsman=products.json&nzmhash=' . $creds->newsman_apikey;

		try {
			if ( class_exists( 'WooCommerce' ) ) {
				$ret = $this->client->feeds->setFeedOnList( sanitize_text_field( wp_unslash( $_POST['newsman_list'] ) ), $url, get_site_url(), 'NewsMAN' );
			}
        // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( Exception $ex ) {
			// The feed already exists.
		}

		update_option( 'newsman_userid', $creds->newsman_userid );
		update_option( 'newsman_apikey', $creds->newsman_apikey );
		update_option( 'newsman_list', sanitize_text_field( wp_unslash( $_POST['newsman_list'] ) ) );
		update_option( 'newsman_remarketingid', $remarketing_id );

		$this->is_oauth( true );
	}
}

?>

<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png"/>
	</a>
</div>
<div class="tabset">

	<div class="tab-panels">
		<section id="tabOauth" class="tab-panel">

			<?php
			if ( ! empty( $local_error ) ) {
				?>
				<div class="error"><p><strong><?php echo ( ! empty( $local_error ) ) ? esc_html( $local_error ) : ''; ?></strong>
				</p></div><?php } ?>

			<div class="wrap wrap-settings-admin-page">
				<h2>NewsMAN plugin for Wordpress-Woocommerce</h2>

				<!--oauth step-->
				<?php if ( 1 === $step ) { ?>
					<form method="post" enctype="multipart/form-data">
						<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $local_nonce ); ?>" />
						<input type="hidden" name="newsman_oauth" value="Y"/>
						<table class="form-table newsmanTable newsmanTblFixed newsmanOauth">
							<tr>
								<td>
									<p class="description"><b>Connect your site with NewsMAN for:</b></p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- Subscribers Sync</p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- Ecommerce Remarketing</p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- Create and manage forms</p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- Create and manage popups</p>
								</td>
							</tr>
							<tr>
								<td>
									<p class="description">- Connect your forms to automation</p>
								</td>
							</tr>
						</table>

						<div style="padding-top: 5px;">
							<a style="background: #ad0100" href="<?php echo esc_url( $oauth_url ); ?>"
								class="button button-primary">Login with NewsMAN</a>
						</div>
					</form>

					<!--List step-->
				<?php } elseif ( 2 === $step ) { ?>

					<form method="post" enctype="multipart/form-data">
						<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( $local_nonce ); ?>" />
						<input type="hidden" name="oauthstep2" value="Y"/>
						<input type="hidden" name="creds" value='<?php echo wp_json_encode( $view_state['creds'] ); ?>'/>
						<table class="form-table newsmanTable newsmanTblFixed newsmanOauth">
							<tr>
								<td>
									<select name="newsman_list" id="">
										<option value="0">-- select list --</option>
										<?php
										foreach ( $data_lists as $l ) {
											?>
											<option
													value="<?php echo esc_attr( $l['id'] ); ?>"><?php echo esc_html( $l['name'] ); ?></option>
										<?php } ?>
									</select>
								</td>
							</tr>
						</table>

						<div style="padding-top: 5px;">
							<button type="submit" style="background: #ad0100" class="button button-primary">Save</button>
						</div>
					</form>

				<?php } ?>

			</div>

		</section>
	</div>
</div>
