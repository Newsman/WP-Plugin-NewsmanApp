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
 * Admin configuration synchronize
 *
 * @class Newsman_Admin_Settings_Sync
 */
class Newsman_Admin_Settings_Sync extends Newsman_Admin_Settings {
	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings-sync';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = 'newsman_sync';

	/**
	 * Form fields
	 *
	 * @var array
	 */
	public $form_fields = array(
		'newsman_list',
		'newsman_segments',
		'newsman_smslist',
	);

	/**
	 * Available lists
	 *
	 * @var array
	 */
	public $available_lists = array();

	/**
	 * Available segments by current list ID
	 *
	 * @var array
	 */
	public $available_segments = array();

	/**
	 * Available SMS lists
	 *
	 * @var array
	 */
	public $available_sms_lists = array();

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend-sync.php';
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
			$this->install_products_feed();

			try {
				$this->available_lists    = $this->retrieve_api_all_lists();
				$this->available_segments = array();
				if ( false !== $this->available_lists ) {
					if ( ! empty( $this->form_values['newsman_list'] ) ) {
						$this->available_segments = $this->retrieve_api_all_segments( $this->form_values['newsman_list'] );
					}

					// If the current list doesn't have the configured segment (ID) than save empty in configured segment ID.
					if ( ! empty( $this->form_values['newsman_segments'] ) ) {
						$found_segment = false;
						foreach ( $this->available_segments as $item ) {
							if ( $this->form_values['newsman_segments'] === (string) $item['segment_id'] ) {
								$found_segment = true;
								break;
							}
						}
						if ( ! $found_segment ) {
							update_option( 'newsman_segments', '' );
							$this->form_values['newsman_segments'] = '';
						}
					}
				} else {
					$this->valid_credentials = false;
					$this->set_message_backend( 'error', esc_html__( 'Could not get the lists or the segments.', 'newsman' ) );
				}

				$this->available_sms_lists = $this->retrieve_api_sms_all_lists();
				if ( empty( $this->available_sms_lists ) ) {
					$this->available_sms_lists = array();
				}

				$this->set_message_backend( 'updated', esc_html__( 'Options saved.', 'newsman' ) );
			} catch ( Exception $e ) {
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Invalid Credentials', 'newsman' ) );
			}
		} else {
			$this->init_form_values_from_option();

			try {
				$this->available_lists = $this->retrieve_api_all_lists();
				if ( false !== $this->available_lists ) {
					if ( ! empty( $this->form_values['newsman_list'] ) ) {
						$this->available_segments = $this->retrieve_api_all_segments( $this->form_values['newsman_list'] );
					}
				} else {
					$this->valid_credentials = false;
					$this->set_message_backend( 'error', esc_html__( 'Could not get the lists or the segments.', 'newsman' ) );
				}

				$this->available_sms_lists = $this->retrieve_api_sms_all_lists();
				if ( empty( $this->available_sms_lists ) ) {
					$this->available_sms_lists = array();
				}
			} catch ( Exception $e ) {
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Could not get the lists or the segments.', 'newsman' ) );
			}
		}
	}

	/**
	 * Install or update products feed entry in Newsman
	 *
	 * @return void
	 */
	public function install_products_feed() {
		if ( empty( $this->form_values['newsman_list'] ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$args           = array(
			'limit'        => -1,
			'return'       => 'ids',
			'status'       => 'publish',
			'stock_status' => 'instock',
		);
		$count_products = wc_get_products( $args );
		if ( empty( $count_products ) ) {
			return;
		}

		$url    = get_site_url() . '/?newsman=products.json&nzmhash=' . $this->get_config()->get_api_key();
		$result = $this->set_feed_on_list(
			$this->form_values['newsman_list'],
			$url,
			get_site_url(),
			'NewsMAN'
		);

		if ( ( false === $result ) || ( 'false' === $result ) ) {
			$this->set_message_backend( 'error', esc_html__( 'Could not update feed list', 'newsman' ) );
		}
	}
}
