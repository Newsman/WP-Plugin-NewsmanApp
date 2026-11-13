<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman personal data export.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Privacy;

use Newsman\Config;
use Newsman\Config\Sms as SmsConfig;
use Newsman\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds to Tools > Export Personal Data what NewsMAN holds about the person, read live
 * from NewsMAN: the subscriber of the email list and the subscribers of the SMS list
 * (account and order billing phones).
 *
 * @class \Newsman\Privacy\Exporter
 */
class Exporter {
	/**
	 * Key of the exporter in the wp_privacy_personal_data_exporters list.
	 */
	public const EXPORTER_ID = 'newsman';

	/**
	 * Group of the exported items.
	 */
	public const GROUP_ID = 'newsman';

	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Init WordPress hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Add the NewsMAN exporter to Tools > Export Personal Data.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$blog_id = get_current_blog_id();
		if ( ! is_array( $exporters ) || ! ( $this->is_allow( $blog_id ) || $this->is_allow_sms( $blog_id ) ) ) {
			return $exporters;
		}

		$exporters[ self::EXPORTER_ID ] = array(
			'exporter_friendly_name' => __( 'NewsMAN newsletter subscription', 'newsman' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Exporter callback.
	 *
	 * @param string $email_address Email address of the export request.
	 * @param int    $page          Page number, the exporter always finishes on the first one.
	 * @return array|\WP_Error An error when NewsMAN cannot be read, so the export is not shown as complete.
	 */
	public function export( $email_address, $page = 1 ) {
		unset( $page );
		$email   = trim( (string) $email_address );
		$blog_id = get_current_blog_id();
		$data    = array();
		if ( ! is_email( $email ) ) {
			return array(
				'data' => $data,
				'done' => true,
			);
		}

		try {
			if ( $this->is_allow( $blog_id ) ) {
				$subscriber = $this->get_email_subscriber( $email, $blog_id );
				if ( is_array( $subscriber ) ) {
					$data[] = array(
						'group_id'          => self::GROUP_ID,
						'group_label'       => __( 'NewsMAN', 'newsman' ),
						'group_description' => __( 'Newsletter and SMS subscriptions kept by NewsMAN, the service the site uses to send newsletters and SMS messages.', 'newsman' ),
						'item_id'           => 'newsman-email-list',
						'data'              => $this->get_item_data( $subscriber, 'email' ),
					);
				}
			}

			if ( $this->is_allow_sms( $blog_id ) ) {
				$eraser = new Eraser();
				foreach ( $eraser->get_phones( $email, get_user_by( 'email', $email ) ) as $phone ) {
					$subscriber = $this->get_sms_subscriber( $phone, $blog_id );
					if ( is_array( $subscriber ) ) {
						$data[] = array(
							'group_id'          => self::GROUP_ID,
							'group_label'       => __( 'NewsMAN', 'newsman' ),
							'group_description' => __( 'Newsletter and SMS subscriptions kept by NewsMAN, the service the site uses to send newsletters and SMS messages.', 'newsman' ),
							'item_id'           => 'newsman-sms-list-' . $phone,
							'data'              => $this->get_item_data( $subscriber, 'telephone' ),
						);
					}
				}
			}
		} catch ( \Exception $e ) {
			Logger::init()->log_exception( $e );
			return new \WP_Error(
				'newsman_export_failed',
				esc_html(
					sprintf(
						/* translators: %s: Error message */
						__( 'NewsMAN could not be read: %s. Export the personal data again later.', 'newsman' ),
						$e->getMessage()
					)
				)
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Subscriber of the email list, or null when the email is not on the list.
	 *
	 * @param string $email   Email address.
	 * @param int    $blog_id Site ID.
	 * @return array|null
	 * @throws \Exception On NewsMAN errors other than "not found".
	 */
	public function get_email_subscriber( $email, $blog_id ) {
		$context = new \Newsman\Service\Context\GetByEmail();
		$context->set_blog_id( $blog_id )
			->set_list_id( $this->config->get_list_id( $blog_id ) )
			->set_email( $email );
		try {
			$result = ( new \Newsman\Service\GetByEmail() )->execute( $context );
		} catch ( \Exception $e ) {
			if ( Eraser::ERROR_NO_SUCH_SUBSCRIBER === (int) $e->getCode() ) {
				return null;
			}
			throw $e;
		}
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Subscriber of the SMS list, or null when the phone number is not on the list.
	 *
	 * @param string $phone   Phone number, digits only.
	 * @param int    $blog_id Site ID.
	 * @return array|null
	 * @throws \Exception On NewsMAN errors other than "not found".
	 */
	public function get_sms_subscriber( $phone, $blog_id ) {
		$context = new \Newsman\Service\Context\Sms\GetByTelephone();
		$context->set_blog_id( $blog_id )
			->set_list_id( SmsConfig::init()->get_list_id( $blog_id ) )
			->set_telephone( $phone );
		try {
			$result = ( new \Newsman\Service\Sms\GetByTelephone() )->execute( $context );
		} catch ( \Exception $e ) {
			if ( Eraser::ERROR_NO_SUCH_SUBSCRIBER === (int) $e->getCode() ) {
				return null;
			}
			throw $e;
		}
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Name / value pairs of a NewsMAN subscriber for the export.
	 *
	 * @param array  $subscriber Subscriber returned by NewsMAN.
	 * @param string $contact    Contact field: email or telephone.
	 * @return array
	 */
	public function get_item_data( $subscriber, $contact ) {
		$labels = array(
			'list_id'           => 'email' === $contact ? __( 'NewsMAN email list', 'newsman' ) : __( 'NewsMAN SMS list', 'newsman' ),
			$contact            => 'email' === $contact ? __( 'Email', 'newsman' ) : __( 'Phone number', 'newsman' ),
			'status'            => __( 'Status', 'newsman' ),
			'subscription_type' => __( 'Subscription source', 'newsman' ),
			'subscribe_date'    => __( 'Subscribed on', 'newsman' ),
			'unsubscribe_date'  => __( 'Unsubscribed on', 'newsman' ),
			'firstname'         => __( 'First name', 'newsman' ),
			'lastname'          => __( 'Last name', 'newsman' ),
			'ip'                => __( 'IP address', 'newsman' ),
		);

		$data = array();
		foreach ( $labels as $key => $label ) {
			if ( isset( $subscriber[ $key ] ) && '' !== (string) $subscriber[ $key ] ) {
				$data[] = array(
					'name'  => $label,
					'value' => (string) $subscriber[ $key ],
				);
			}
		}

		if ( ! empty( $subscriber['tags'] ) && is_array( $subscriber['tags'] ) ) {
			$data[] = array(
				'name'  => __( 'Tags', 'newsman' ),
				'value' => implode( ', ', array_map( 'strval', $subscriber['tags'] ) ),
			);
		}

		if ( ! empty( $subscriber['props'] ) && is_array( $subscriber['props'] ) ) {
			foreach ( $subscriber['props'] as $name => $value ) {
				$data[] = array(
					'name'  => (string) $name,
					'value' => is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ),
				);
			}
		}

		/**
		 * Name / value pairs of a NewsMAN subscriber in the personal data export.
		 *
		 * @param array  $data       Pairs.
		 * @param array  $subscriber Subscriber returned by NewsMAN.
		 * @param string $contact    email or telephone.
		 */
		return (array) apply_filters( 'newsman_privacy_export_item_data', $data, $subscriber, $contact );
	}

	/**
	 * Whether the plugin is connected to NewsMAN on the site.
	 *
	 * @param int $blog_id Site ID.
	 * @return bool
	 */
	public function is_allow( $blog_id ) {
		return ( new Eraser() )->is_allow( $blog_id );
	}

	/**
	 * Whether SMS is set up on the site.
	 *
	 * @param int $blog_id Site ID.
	 * @return bool
	 */
	public function is_allow_sms( $blog_id ) {
		return ( new Eraser() )->is_allow_sms( $blog_id );
	}

	/**
	 * Suggested text for the privacy policy, shown in Settings > Privacy > Policy Guide.
	 * Called on admin_init.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$paragraphs = array(
			'<p class="privacy-policy-tutorial">' . esc_html__( 'This text describes what the NewsMAN plugin sends to NewsMAN. Keep only the parts that match the NewsMAN features you use.', 'newsman' ) . '</p>',
			'<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'newsman' ) . ' </strong>' . esc_html__( 'We use NewsMAN, a service of Dazoot Software, to send newsletters and SMS messages and for remarketing.', 'newsman' ) . '</p>',
			'<p>' . esc_html__( 'When you subscribe to our newsletter (a subscription form, the checkout or your account page), we send NewsMAN your email address, your name and, when you give it, your phone number, with the date and the IP address of the request.', 'newsman' ) . '</p>',
			'<p>' . esc_html__( 'When you place an order, we may send NewsMAN the order details (products, quantities, prices and the order status) with your email address, name and phone number, to measure our campaigns and to send you SMS messages about your order.', 'newsman' ) . '</p>',
			'<p>' . esc_html__( 'The NewsMAN tracking script on our pages records the pages and products you view, your cart and your purchases. It keeps a tracking identifier in cookies whose names start with _nzm and keeps events that were not sent yet in the storage of your browser.', 'newsman' ) . '</p>',
			'<p>' . esc_html__( 'When you ask us to erase your personal data, or when your account is deleted, NewsMAN replaces your email address in our NewsMAN lists with an anonymous one and unsubscribes your phone numbers from our NewsMAN SMS lists.', 'newsman' ) . '</p>',
			'<p>' . sprintf(
				/* translators: 1: NewsMAN privacy policy link, 2: NewsMAN cookie policy link */
				esc_html__( 'How NewsMAN processes personal data: %1$s and %2$s.', 'newsman' ),
				'<a href="https://www.newsman.com/privacy">' . esc_html__( 'NewsMAN privacy policy', 'newsman' ) . '</a>',
				'<a href="https://www.newsman.com/cookies">' . esc_html__( 'NewsMAN cookie policy', 'newsman' ) . '</a>'
			) . '</p>',
		);

		wp_add_privacy_policy_content( 'NewsMAN', wp_kses_post( implode( "\n", $paragraphs ) ) );
	}
}
