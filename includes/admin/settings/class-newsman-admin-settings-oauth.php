<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman remarketing class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin configuration Oauth
 *
 * @class Newsman_Admin_Settings_Oauth
 */
class Newsman_Admin_Settings_Oauth extends Newsman_Admin_Settings {
	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings-oauth';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = 'newsman_oauth';

	/**
	 * Form two ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id_step_two = 'oauthstep2';

	/**
	 * Form number or step
	 *
	 * @var int
	 */
	public $step = 1;

	/**
	 * Error message
	 *
	 * @var string
	 */
	public $form_error_message = '';

	/**
	 * Lists in API response
	 *
	 * @var array
	 */
	public $response_lists = array();

	/**
	 * View state
	 *
	 * @var array
	 */
	public $view_state = array();

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend-oauth.php';
	}

	/**
	 * Process forms
	 *
	 * @return void
	 */
	public function process_forms() {
		$this->form_error_message = '';
		$this->response_lists     = array();
		$this->step               = 1;
		$this->view_state         = array();

		$this->process_form_oauth_step_one();
		$this->process_form_settings_step_two();
	}

	/**
	 * Process form OAuth, step 1
	 *
	 * @return void
	 */
	public function process_form_oauth_step_one() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error'] ) && ! empty( $_GET['error'] ) ) {
			switch ( $this->form_error_message ) {
				case 'access_denied':
					$this->form_error_message = esc_html__( 'Access is denied', 'newsman' );
					break;
				case 'missing_lists':
					$this->form_error_message = esc_html__( 'There are no lists in your NewsMAN account', 'newsman' );
					break;
			}
			return;
		} elseif ( empty( $_GET['code'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$auth_url = 'https://newsman.app/admin/oauth/token';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$redirect = 'https://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) .
			sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) . // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			( ! empty( $this->new_nonce ) ? '&_wpnonce=' . $this->new_nonce : '' );

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
			$this->form_error_message .= 'cURL error: ' . curl_error( $ch );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
		curl_close( $ch );

		if ( false !== $response ) {

			$response = json_decode( $response );

			$this->view_state['creds'] = wp_json_encode(
				array(
					'newsman_userid' => $response->user_id,
					'newsman_apikey' => $response->access_token,
				)
			);

			foreach ( $response->lists_data as $list => $l ) {
				$this->response_lists[] = array(
					'id'   => $l->list_id,
					'name' => $l->name,
				);
			}

			$this->step = 2;
		} else {
			$this->form_error_message .= 'Error sending cURL request.';
		}
	}

	/**
	 * Process form settings, step 2
	 *
	 * @return void
	 */
	public function process_form_settings_step_two() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! ( ! empty( $_POST['oauthstep2'] ) && 'Y' === $_POST['oauthstep2'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['newsman_list'] ) ) {
			$this->step = 1;
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$list_id = sanitize_text_field( wp_unslash( $_POST['newsman_list'] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$creds = isset( $_POST['creds'] ) ? sanitize_text_field( wp_unslash( $_POST['creds'] ) ) : '';
		$creds = json_decode( $creds );
		$creds = json_decode( $creds ); // No mistake calling twice.

		update_option( 'newsman_userid', $creds->newsman_userid );
		update_option( 'newsman_apikey', $creds->newsman_apikey );
		update_option( 'newsman_list', sanitize_text_field( wp_unslash( $list_id ) ) );

		$settings = $this->get_remarketing_settings( $list_id, $creds->newsman_userid, $creds->newsman_apikey );
		if ( ! empty( $settings ) && is_array( $settings ) ) {
			$remarketing_id = $settings['site_id'] . '-' . $settings['list_id'] . '-' . $settings['form_id'] .
				'-' . $settings['control_list_hash'];
			update_option( 'newsman_remarketingid', $remarketing_id );
		}

		// Set feed.
		$url = get_site_url() . '/?newsman=products.json&nzmhash=' . $creds->newsman_apikey;

		try {
			if ( class_exists( 'WooCommerce' ) ) {
				$result = $this->set_feed_on_list(
					$list_id,
					$url,
					get_site_url(),
					'NewsMAN'
				);
			}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( Exception $e ) {
			// The feed already exists.
		}

		$this->is_oauth( true );
	}

	/**
	 * Get OAuth URL
	 *
	 * @return string
	 */
	public function get_oauth_url() {
		$oauth_url = 'https://newsman.app/admin/oauth/authorize?response_type=code&client_id=nzmplugin&nzmplugin=Wordpress&scope=api&redirect_uri=' .
			rawurlencode(
				'https://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) . // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				( ! empty( $this->new_nonce ) ? '&_wpnonce=' . $this->new_nonce : '' )
			);
		return $oauth_url;
	}

	/**
	 * API get remarketing settings
	 *
	 * @param string      $list_id List ID.
	 * @param null|string $user_id User ID.
	 * @param null|string $api_key API key.
	 * @return array|false
	 */
	public function get_remarketing_settings( $list_id, $user_id = null, $api_key = null ) {
		try {
			if ( null === $user_id ) {
				$user_id = $this->config->get_user_id();
			}
			if ( null === $api_key ) {
				$api_key = $this->config->get_api_key();
			}

			$context = new Newsman_Service_Context_Configuration_List();
			$context->set_user_id( $user_id )
				->set_api_key( $api_key )
				->set_list_id( $list_id );
			$get_sms_list_all = new Newsman_Service_Configuration_RemarketingGetSettings();
			return $get_sms_list_all->execute( $context );
		} catch ( Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
	}
}
