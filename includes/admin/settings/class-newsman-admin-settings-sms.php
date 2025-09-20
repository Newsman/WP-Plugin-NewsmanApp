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
 * Admin configuration SMS
 *
 * @class Newsman_Admin_Settings_Sms
 */
class Newsman_Admin_Settings_Sms extends Newsman_Admin_Settings {
	/**
	 * Config SMS
	 *
	 * @var Newsman_Config_Sms
	 */
	protected $sms_config;

	/**
	 * Class construct
	 */
	public function __construct() {
		parent::__construct();
		$this->sms_config = Newsman_Config_Sms::init();
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
	 * @param null|int|string $list_id API SMS list ID..
	 * @return array|false
	 */
	public function sms_send_one( $text, $to, $list_id = null ) {
		try {
			if ( null === $list_id ) {
				$list_id = $this->sms_config->get_list_id();
			}

			$context = new Newsman_Service_Context_Sms_SendOne();
			$context->set_list_id( $list_id )
				->set_text( $text )
				->set_to( $to );
			$get_sms_list_all = new Newsman_Service_Sms_SendOne();
			$lists_data       = $get_sms_list_all->execute( $context );
			return $lists_data;
		} catch ( Exception $e ) {
			$this->logger->error( $e->getCode() . ' ' . $e->getMessage() );
			return false;
		}
	}
}
