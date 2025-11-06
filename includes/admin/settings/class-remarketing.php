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

namespace Newsman\Admin\Settings;

use Newsman\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin configuration remarketing
 *
 * @class \Newsman\Admin\Settings\Remarketing
 */
class Remarketing extends Settings {
	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings-remarketing';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = 'newsman_remarketing';

	/**
	 * Form fields
	 *
	 * @var array
	 */
	public $form_fields = array(
		'newsman_useremarketing',
		'newsman_remarketingid',
		'newsman_remarketinganonymizeip',
		'newsman_remarketingsendtelephone',
		'newsman_remarketingproductattributes',
		'newsman_remarketingcustomerattributes',
		'newsman_remarketingordersave',
		'newsman_remarketingexportwordpresssubscribers',
		'newsman_remarketingexportwoocommercesubscribers',
		'newsman_remarketingexportorders',
		'newsman_remarketingorderdate',
	);

	/**
	 * Customer attributes fetched from order
	 *
	 * @var array
	 */
	protected $customer_attributes = array(
		'billing_company'  => 'Billing Company',
		'billing_city'     => 'Billing City',
		'billing_state'    => 'Billing State/County',
		'billing_country'  => 'Billing Country',
		'shipping_company' => 'Shipping Company',
		'shipping_city'    => 'Shipping City',
		'shipping_state'   => 'Shipping State/County',
		'shipping_country' => 'Shipping Country',
	);

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend-remarketing.php';
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

			try {
				$available_lists = $this->retrieve_api_all_lists();
				if ( false === $available_lists ) {
					$this->valid_credentials = false;
				}
				$this->set_message_backend( 'updated', esc_html__( 'Options saved.', 'newsman' ) );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Invalid Credentials. Please configure them in Settings tab.', 'newsman' ) );
			}
		} else {
			$this->init_form_values_from_option();

			try {
				$available_lists = $this->retrieve_api_all_lists();
				if ( false === $available_lists ) {
					$this->valid_credentials = false;
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Invalid Credentials. Please configure them in Settings tab.', 'newsman' ) );
			}
		}
	}

	/**
	 * Get customer attributes
	 *
	 * @return array
	 */
	public function get_customer_attributes() {
		return apply_filters(
			'newsman_admin_settings_remarketing_customer_attributes',
			$this->customer_attributes
		);
	}

	/**
	 * Save admin configuration from form fields.
	 * Unschedule recurring hooks in Action Scheduler.
	 *
	 * @return array
	 */
	public function save_form_values() {
		$form_values = parent::save_form_values();

		if ( isset( $form_values['newsman_remarketingexportwordpresssubscribers'] ) &&
			'on' !== $form_values['newsman_remarketingexportwordpresssubscribers']
		) {
			$scheduler = new \Newsman\Scheduler\Export\Recurring\SubscribersWordpress();
			$scheduler->unschedule_all_actions();
		}

		if ( isset( $form_values['newsman_remarketingexportwoocommercesubscribers'] ) &&
			'on' !== $form_values['newsman_remarketingexportwoocommercesubscribers']
		) {
			$scheduler = new \Newsman\Scheduler\Export\Recurring\SubscribersWoocommerce();
			$scheduler->unschedule_all_actions();
		}

		if ( isset( $form_values['newsman_remarketingexportorders'] ) &&
			'on' !== $form_values['newsman_remarketingexportorders']
		) {
			$scheduler = new \Newsman\Scheduler\Export\Recurring\Orders();
			$scheduler->unschedule_all_actions();
		}

		return $form_values;
	}
}
