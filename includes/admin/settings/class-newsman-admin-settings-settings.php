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
 * Admin configuration settings
 *
 * @class Newsman_Admin_Settings_Settings
 */
class Newsman_Admin_Settings_Settings extends Newsman_Admin_Settings {
	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings-settings';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = 'newsman_submit';

	/**
	 * Form fields
	 *
	 * @var array
	 */
	public $form_fields = array(
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

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend-settings.php';
	}

	/**
	 * Process form
	 *
	 * @return void
	 */
	public function process_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user', 'newsman' ) );
		}

		$form_id_value           = '';
		$this->valid_credentials = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $this->form_id ] ) && ! empty( $_POST[ $this->form_id ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$form_id_value = sanitize_text_field( wp_unslash( $_POST[ $this->form_id ] ) );
		}

		if ( 'Y' === $form_id_value ) {
			$this->init_form_values_from_post();
			$this->save_form_values();

			$this->is_oauth();

			try {
				$available_lists = $this->retrieve_api_all_lists(
					$this->form_values['newsman_userid'],
					$this->form_values['newsman_apikey']
				);

				if ( false === $available_lists ) {
					$this->valid_credentials = false;
				}

				$this->set_message_backend( 'updated', esc_html__( 'Options saved.', 'newsman' ) );
			} catch ( Exception $e ) {
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Invalid Credentials', 'newsman' ) );
			}
		} else {
			$this->init_form_values_from_option();

			try {
				$available_lists = $this->retrieve_api_all_lists();

				if ( false === $available_lists ) {
					$this->valid_credentials = false;
				}
			} catch ( Exception $e ) {
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Invalid Credentials', 'newsman' ) );
			}
		}
	}
}
