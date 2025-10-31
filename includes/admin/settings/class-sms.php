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
 * Admin configuration SMS
 *
 * @class \Newsman\Admin\Settings\Sms
 */
class Sms extends Settings {
	/**
	 * Config SMS
	 *
	 * @var \Newsman\Config\Sms
	 */
	protected $sms_config;

	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings-sms';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = 'newsman_action';

	/**
	 * Form fields
	 *
	 * @var array
	 */
	public $form_fields = array(
		'newsman_usesms',
		'newsman_smstest',
		'newsman_smstestnr',
		'newsman_smslist',
		'newsman_smspendingactivate',
		'newsman_smspendingtext',
		'newsman_smsfailedactivate',
		'newsman_smsfailedtext',
		'newsman_smsonholdactivate',
		'newsman_smsonholdtext',
		'newsman_smsprocessingactivate',
		'newsman_smsprocessingtext',
		'newsman_smscompletedactivate',
		'newsman_smscompletedtext',
		'newsman_smsrefundedactivate',
		'newsman_smsrefundedtext',
		'newsman_smscancelledactivate',
		'newsman_smscancelledtext',
	);

	/**
	 * SMS lists
	 *
	 * @var array
	 */
	public $available_smslists = array();

	/**
	 * Class construct
	 */
	public function __construct() {
		parent::__construct();
		$this->sms_config = \Newsman\Config\Sms::init();
	}

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend-sms.php';
	}

	/**
	 * Call API SMS send one
	 *
	 * @param string          $text SMS text.
	 * @param string          $to Phone number.
	 * @param null|int|string $list_id API SMS list ID.
	 * @return array|false
	 */
	public function sms_send_one( $text, $to, $list_id = null ) {
		try {
			if ( null === $list_id ) {
				$list_id = $this->get_sms_config()->get_list_id();
			}

			$context = new \Newsman\Service\Context\Sms\SendOne();
			$context->set_list_id( $list_id )
				->set_text( $text )
				->set_to( $to );
			$send_one = new \Newsman\Service\Sms\SendOne();
			$result   = $send_one->execute( $context );
			return $result;
		} catch ( \Exception $e ) {
			$this->logger->log_exception( $e );
			return false;
		}
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

		$this->process_form_send_one();

		if ( 'Y' === $form_id_value ) {
			$this->init_form_values_from_post();
			$this->save_form_values();
			$this->set_message_backend( 'updated', 'Options saved.' );

			try {
				$available_lists = $this->retrieve_api_all_lists();
				if ( false === $available_lists ) {
					$this->valid_credentials = false;
					$this->set_message_backend( 'error', esc_html__( 'Invalid credentials.', 'newsman' ) );
				} else {
					$this->available_smslists = $this->retrieve_api_sms_all_lists();
					if ( false === $this->available_smslists ) {
						$this->set_message_backend( 'error', esc_html__( 'Invalid credentials.', 'newsman' ) );
					} elseif ( empty( $this->available_smslists ) ) {
						$this->set_message_backend( 'error', esc_html__( 'No SMS list found.', 'newsman' ) );
					}
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$this->set_message_backend(
					'error',
					esc_html__( 'An error has occurred.', 'newsman' ) . $e->getMessage()
				);
			}
		} else {
			$this->init_form_values_from_option();

			try {
				$available_lists = $this->retrieve_api_all_lists();
				if ( false === $available_lists ) {
					$this->valid_credentials = false;
					$this->set_message_backend( 'error', esc_html__( 'Invalid credentials.', 'newsman' ) );
				} else {
					$this->available_smslists = $this->retrieve_api_sms_all_lists();
					if ( false === $this->available_smslists ) {
						$this->set_message_backend( 'error', esc_html__( 'Invalid credentials.', 'newsman' ) );
					} elseif ( empty( $this->available_smslists ) ) {
						$this->set_message_backend( 'error', esc_html__( 'No SMS list found.', 'newsman' ) );
					}
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$this->set_message_backend( 'error', 'Invalid Credentials or no SMS list present' );
			}
		}
	}

	/**
	 * Process form, send one test SMS
	 *
	 * @return void
	 */
	public function process_form_send_one() {
		$form_fields = array(
			$this->form_id,
			'newsman_smsdevtestnr',
			'newsman_smsdevtestmsg',
			'newsman_smslist',
		);
		$form_values = array();
		foreach ( $form_fields as $name ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $name ] ) && ! empty( $_POST[ $name ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$form_values[ $name ] = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
			} else {
				$form_values[ $name ] = '';
			}
		}

		if ( 'newsman_smsdevbtn' === $form_values[ $this->form_id ] ) {
			try {
				if ( ! empty( $form_values['newsman_smsdevtestnr'] ) &&
					! empty( $form_values['newsman_smsdevtestmsg'] ) &&
					! empty( $form_values['newsman_smslist'] ) ) {
					$result = $this->sms_send_one(
						$form_values['newsman_smsdevtestmsg'],
						$form_values['newsman_smsdevtestnr'],
						$form_values['newsman_smslist']
					);
					if ( false === $result ) {
						$this->set_message_backend( 'error', esc_html__( 'The SMS was not sent. ', 'newsman' ) );
					} else {
						$this->set_message_backend( 'updated', esc_html__( 'Test SMS was sent.', 'newsman' ) );
					}
				} elseif ( empty( $form_values['newsman_smslist'] ) ) {
					$this->set_message_backend(
						'error',
						esc_html__( 'The SMS was not sent. Please select or keep the configured SMS list.', 'newsman' )
					);
				} else {
					$this->set_message_backend(
						'error',
						esc_html__(
							'The SMS was not sent. Please fill the test phone number and the test message.',
							'newsman'
						)
					);
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$this->set_message_backend( 'error', esc_html__( 'The SMS was not sent. ', 'newsman' ) . $e->getMessage() );
			}
		}
	}

	/**
	 * Get \Newsman\Config\Sms
	 *
	 * @return \Newsman\Config\Sms|null Newsman SMS config.
	 */
	public function get_sms_config() {
		return $this->sms_config;
	}
}
