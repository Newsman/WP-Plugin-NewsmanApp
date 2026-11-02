<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman plugin health checks.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Health;

use Newsman\Admin\Settings;
use Newsman\Config;
use Newsman\Export\LastRun;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Util\ActionScheduler as NewsmanActionScheduler;
use Newsman\Util\WooCommerceExist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health checks shown on the NewsMAN admin page and in Tools > Site Health.
 *
 * Every check returns an array with the keys:
 * - status:       good, recommended, critical or info.
 * - value:        short text for the status (one or two words).
 * - description:  one or two sentences explaining the status.
 * - action_url:   admin page that fixes the problem, or empty.
 * - action_label: link text for action_url, or empty.
 *
 * @class \Newsman\Health\Checks
 */
class Checks {
	/**
	 * Status of a check that works.
	 */
	public const STATUS_GOOD = 'good';

	/**
	 * Status of a check that needs attention.
	 */
	public const STATUS_RECOMMENDED = 'recommended';

	/**
	 * Status of a check that stops the plugin from working.
	 */
	public const STATUS_CRITICAL = 'critical';

	/**
	 * Status of a check that only informs.
	 */
	public const STATUS_INFO = 'info';

	/**
	 * Pending background tasks older than this many seconds are past due.
	 */
	public const PAST_DUE_SECONDS = 3600;

	/**
	 * Failed background tasks are counted for this many seconds back.
	 */
	public const FAILED_PERIOD_SECONDS = 604800;

	/**
	 * Newsman config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Remarketing config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Admin settings, used for the NewsMAN API call that validates the credentials.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Last export per type
	 *
	 * @var LastRun
	 */
	protected $last_run;

	/**
	 * Lists returned by the NewsMAN API, false when the call failed, null before the call.
	 *
	 * @var array|false|null
	 */
	protected $api_lists;

	/**
	 * Action Scheduler counts, cached for the request.
	 *
	 * @var array|null
	 */
	protected $action_scheduler_counts;

	/**
	 * Class construct
	 *
	 * @param null|Settings $settings Admin settings used to call the NewsMAN API.
	 */
	public function __construct( $settings = null ) {
		$this->config             = Config::init();
		$this->remarketing_config = RemarketingConfig::init();
		$this->settings           = ( null === $settings ) ? new Settings() : $settings;
		$this->last_run           = new LastRun();
	}

	/**
	 * Whether the API key and the User ID are saved.
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return $this->config->has_api_access();
	}

	/**
	 * Lists of the NewsMAN account. Calls the NewsMAN API once per request.
	 *
	 * @return array|false False when the credentials are missing or rejected, or the API is unreachable.
	 */
	public function get_api_lists() {
		if ( null !== $this->api_lists ) {
			return $this->api_lists;
		}

		$this->api_lists = false;
		if ( $this->has_credentials() ) {
			$lists           = $this->settings->retrieve_api_all_lists();
			$this->api_lists = is_array( $lists ) ? $lists : false;
		}
		return $this->api_lists;
	}

	/**
	 * Whether NewsMAN accepts the saved API key and User ID.
	 *
	 * @return bool
	 */
	public function is_valid_credentials() {
		return false !== $this->get_api_lists();
	}

	/**
	 * Check the API key and User ID against the NewsMAN API.
	 *
	 * @return array
	 */
	public function check_credentials() {
		if ( ! $this->has_credentials() ) {
			return $this->result(
				self::STATUS_CRITICAL,
				__( 'Not connected', 'newsman' ),
				__( 'The API key and User ID are not saved. Connect the plugin to your NewsMAN account.', 'newsman' ),
				admin_url( 'admin.php?page=NewsmanOauth' ),
				__( 'Connect with NewsMAN', 'newsman' )
			);
		}

		if ( ! $this->is_valid_credentials() ) {
			return $this->result(
				self::STATUS_CRITICAL,
				__( 'Invalid', 'newsman' ),
				__( 'NewsMAN rejected the saved API key and User ID, or the NewsMAN API could not be reached. Check the credentials in Settings or reconfigure with NewsMAN OAuth.', 'newsman' ),
				admin_url( 'admin.php?page=NewsmanSettings' ),
				__( 'Open Settings', 'newsman' )
			);
		}

		return $this->result(
			self::STATUS_GOOD,
			__( 'Valid', 'newsman' ),
			__( 'NewsMAN accepted the saved API key and User ID.', 'newsman' )
		);
	}

	/**
	 * Check the selected email list.
	 *
	 * @return array
	 */
	public function check_list() {
		$list_id = (string) $this->config->get_list_id();
		if ( '' === $list_id ) {
			return $this->result(
				self::STATUS_RECOMMENDED,
				__( 'Not selected', 'newsman' ),
				__( 'Select the NewsMAN list that receives the subscribers of this site.', 'newsman' ),
				admin_url( 'admin.php?page=NewsmanSync' ),
				__( 'Open Sync', 'newsman' )
			);
		}

		$lists = $this->get_api_lists();
		if ( false === $lists ) {
			return $this->result(
				self::STATUS_INFO,
				/* translators: %s: NewsMAN list ID */
				sprintf( __( 'ID: %s', 'newsman' ), $list_id ),
				__( 'The list cannot be checked until NewsMAN accepts the API key and User ID.', 'newsman' )
			);
		}

		foreach ( $lists as $list ) {
			if ( is_array( $list ) && isset( $list['list_id'] ) && (string) $list['list_id'] === $list_id ) {
				$name = isset( $list['list_name'] ) ? (string) $list['list_name'] : '';
				return $this->result(
					self::STATUS_GOOD,
					/* translators: 1: NewsMAN list name, 2: NewsMAN list ID */
					sprintf( __( '%1$s (ID: %2$s)', 'newsman' ), $name, $list_id ),
					__( 'New subscribers are added to this list.', 'newsman' )
				);
			}
		}

		return $this->result(
			self::STATUS_CRITICAL,
			/* translators: %s: NewsMAN list ID */
			sprintf( __( 'ID: %s', 'newsman' ), $list_id ),
			__( 'The selected list is not in your NewsMAN account anymore. Select another list.', 'newsman' ),
			admin_url( 'admin.php?page=NewsmanSync' ),
			__( 'Open Sync', 'newsman' )
		);
	}

	/**
	 * Check that NewsMAN may read data from the store (the "Allow API access" setting).
	 *
	 * @return array
	 */
	public function check_api_access() {
		if ( ! $this->config->use_api() ) {
			return $this->result(
				self::STATUS_RECOMMENDED,
				__( 'Off', 'newsman' ),
				__( 'Allow API access is turned off in Settings, so NewsMAN cannot read subscribers, customers, products and orders from this site.', 'newsman' ),
				admin_url( 'admin.php?page=NewsmanSettings' ),
				__( 'Open Settings', 'newsman' )
			);
		}

		return $this->result(
			self::STATUS_GOOD,
			__( 'On', 'newsman' ),
			__( 'NewsMAN can read subscribers, customers, products and orders from this site.', 'newsman' )
		);
	}

	/**
	 * Check the remarketing tracker.
	 *
	 * @return array
	 */
	public function check_remarketing() {
		$action_url   = admin_url( 'admin.php?page=NewsmanRemarketing' );
		$action_label = __( 'Open Remarketing', 'newsman' );

		if ( ! $this->remarketing_config->use_remarketing() ) {
			return $this->result(
				self::STATUS_INFO,
				__( 'Off', 'newsman' ),
				__( 'Remarketing is turned off, so NewsMAN does not receive product views, cart changes and purchases from this site.', 'newsman' ),
				$action_url,
				$action_label
			);
		}

		$tracking_id = (string) $this->remarketing_config->get_id();
		if ( '' === $tracking_id ) {
			return $this->result(
				self::STATUS_RECOMMENDED,
				__( 'Remarketing ID missing', 'newsman' ),
				__( 'Remarketing is turned on, but the Remarketing ID is empty, so the tracking script is not added to the site.', 'newsman' ),
				$action_url,
				$action_label
			);
		}

		return $this->result(
			self::STATUS_GOOD,
			__( 'On', 'newsman' ),
			__( 'The tracking script sends product views, cart changes and purchases to NewsMAN. Administrators are not tracked.', 'newsman' )
		);
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return array
	 */
	public function check_woocommerce() {
		if ( ! $this->has_woocommerce() ) {
			return $this->result(
				self::STATUS_INFO,
				__( 'Not active', 'newsman' ),
				__( 'The checkout and My Account newsletter options, SMS, order tracking and the customer, product and order exports need WooCommerce.', 'newsman' )
			);
		}

		return $this->result(
			self::STATUS_GOOD,
			/* translators: %s: WooCommerce version */
			sprintf( __( 'Active, version %s', 'newsman' ), $this->get_woocommerce_version() ),
			__( 'All store features of the plugin are available.', 'newsman' )
		);
	}

	/**
	 * Check the NewsMAN background tasks in Action Scheduler.
	 *
	 * @return array
	 */
	public function check_action_scheduler() {
		if ( ! $this->has_action_scheduler() ) {
			return $this->result(
				self::STATUS_INFO,
				__( 'Not available', 'newsman' ),
				__( 'Action Scheduler is not installed. It comes with WooCommerce and runs NewsMAN tasks in the background.', 'newsman' )
			);
		}

		$counts = $this->get_action_scheduler_counts();
		if ( null === $counts ) {
			return $this->result(
				self::STATUS_INFO,
				__( 'Unknown', 'newsman' ),
				__( 'The NewsMAN background tasks could not be counted.', 'newsman' )
			);
		}

		$summary = sprintf(
			/* translators: 1: number of pending tasks, 2: number of past due tasks, 3: number of failed tasks */
			__( '%1$d pending, %2$d past due, %3$d failed in the last 7 days', 'newsman' ),
			$counts['pending'],
			$counts['past_due'],
			$counts['failed']
		);

		if ( $counts['past_due'] > 0 ) {
			return $this->result(
				self::STATUS_RECOMMENDED,
				$summary,
				__( 'Some NewsMAN background tasks waited more than one hour. WP-Cron may not be running on this site.', 'newsman' ),
				$this->get_action_scheduler_url( 'past-due' ),
				__( 'View past due tasks', 'newsman' )
			);
		}

		if ( $counts['failed'] > 0 ) {
			return $this->result(
				self::STATUS_RECOMMENDED,
				$summary,
				__( 'Some NewsMAN background tasks failed in the last 7 days. The task log shows the error.', 'newsman' ),
				$this->get_action_scheduler_url( 'failed' ),
				__( 'View failed tasks', 'newsman' )
			);
		}

		$description = $this->config->use_action_scheduler()
			? __( 'NewsMAN background tasks run on time.', 'newsman' )
			: __( 'Use Action Scheduler is turned off in Settings, so subscriptions are sent to NewsMAN right away.', 'newsman' );

		return $this->result( self::STATUS_GOOD, $summary, $description );
	}

	/**
	 * Last export per type, for the types available on this site.
	 *
	 * @return array List of [ 'type' => string, 'label' => string, 'time' => int|null, 'value' => string ].
	 */
	public function get_last_exports() {
		$labels  = $this->get_export_type_labels();
		$exports = array();
		foreach ( $this->last_run->get_types( $this->has_woocommerce() ) as $type ) {
			$time      = $this->last_run->get_time( $type );
			$exports[] = array(
				'type'  => $type,
				'label' => isset( $labels[ $type ] ) ? $labels[ $type ] : $type,
				'time'  => $time,
				'value' => ( null === $time ) ? __( 'No export recorded yet', 'newsman' ) : $this->format_time( $time ),
			);
		}
		return $exports;
	}

	/**
	 * Labels of the export types.
	 *
	 * @return array
	 */
	public function get_export_type_labels() {
		return apply_filters(
			'newsman_health_export_type_labels',
			array(
				'subscribers' => __( 'Subscribers', 'newsman' ),
				'customers'   => __( 'Customers', 'newsman' ),
				'products'    => __( 'Products', 'newsman' ),
				'orders'      => __( 'Orders', 'newsman' ),
			)
		);
	}

	/**
	 * Count the NewsMAN background tasks: pending, past due and failed in the last 7 days.
	 *
	 * @return array|null Null when Action Scheduler is missing or cannot count.
	 */
	public function get_action_scheduler_counts() {
		if ( null !== $this->action_scheduler_counts ) {
			return $this->action_scheduler_counts;
		}
		if ( ! $this->has_action_scheduler() || ! is_callable( array( '\ActionScheduler', 'store' ) ) ) {
			return null;
		}

		$now             = time();
		$past_due_before = new \DateTime( '@' . ( $now - (int) apply_filters( 'newsman_health_action_scheduler_past_due_seconds', self::PAST_DUE_SECONDS ) ) );
		$failed_after    = new \DateTime( '@' . ( $now - self::FAILED_PERIOD_SECONDS ) );
		$counts          = array(
			'pending'  => 0,
			'past_due' => 0,
			'failed'   => 0,
		);

		try {
			$store = \ActionScheduler::store();
			foreach ( $this->get_action_scheduler_groups() as $group ) {
				$counts['pending']  += (int) $store->query_actions(
					array(
						'group'  => $group,
						'status' => 'pending',
					),
					'count'
				);
				$counts['past_due'] += (int) $store->query_actions(
					array(
						'group'        => $group,
						'status'       => 'pending',
						'date'         => $past_due_before,
						'date_compare' => '<=',
					),
					'count'
				);
				$counts['failed']   += (int) $store->query_actions(
					array(
						'group'            => $group,
						'status'           => 'failed',
						'modified'         => $failed_after,
						'modified_compare' => '>=',
					),
					'count'
				);
			}
		} catch ( \Throwable $e ) {
			return null;
		}

		$this->action_scheduler_counts = $counts;
		return $counts;
	}

	/**
	 * Action Scheduler groups used by the plugin.
	 *
	 * @return array
	 */
	public function get_action_scheduler_groups() {
		return apply_filters(
			'newsman_health_action_scheduler_groups',
			array(
				NewsmanActionScheduler::GROUP_SUBSCRIBE,
				NewsmanActionScheduler::GROUP_ORDER_CHANGE,
				NewsmanActionScheduler::GROUP_ORDER_SAVE,
				NewsmanActionScheduler::GROUP_ORDER_SMS_AWB,
				NewsmanActionScheduler::GROUP_MASS_EXPORT_SUBSCRIBERS_WORDPRESS,
				NewsmanActionScheduler::GROUP_MASS_EXPORT_SUBSCRIBERS_WOOCOMMERCE,
				NewsmanActionScheduler::GROUP_MASS_EXPORT_ORDERS,
			)
		);
	}

	/**
	 * Scheduled Actions admin page filtered on the NewsMAN tasks.
	 *
	 * @param string $status Action status filter.
	 * @return string
	 */
	public function get_action_scheduler_url( $status = 'pending' ) {
		return add_query_arg(
			array(
				'page'   => 'action-scheduler',
				'status' => $status,
				's'      => 'newsman',
			),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function has_woocommerce() {
		return ( new WooCommerceExist() )->exist();
	}

	/**
	 * WooCommerce version.
	 *
	 * @return string
	 */
	public function get_woocommerce_version() {
		if ( defined( 'WC_VERSION' ) ) {
			return (string) constant( 'WC_VERSION' );
		}
		if ( function_exists( 'WC' ) && isset( WC()->version ) ) {
			return (string) WC()->version;
		}
		return '';
	}

	/**
	 * Whether Action Scheduler is loaded.
	 *
	 * @return bool
	 */
	public function has_action_scheduler() {
		return ( new NewsmanActionScheduler() )->exist();
	}

	/**
	 * Format a Unix time in the site time zone, with the elapsed time.
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	public function format_time( $time ) {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		if ( function_exists( 'wp_date' ) ) {
			$date = wp_date( $format, $time );
		} else {
			$date = date_i18n( $format, $time + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		}

		return sprintf(
			/* translators: 1: date and time, 2: elapsed time, for example "3 hours" */
			__( '%1$s (%2$s ago)', 'newsman' ),
			$date,
			human_time_diff( $time, max( $time, time() ) )
		);
	}

	/**
	 * Build a check result.
	 *
	 * @param string $status       Status.
	 * @param string $value        Short text.
	 * @param string $description  Explanation.
	 * @param string $action_url   Admin URL that fixes the problem.
	 * @param string $action_label Link text.
	 * @return array
	 */
	protected function result( $status, $value, $description, $action_url = '', $action_label = '' ) {
		return array(
			'status'       => $status,
			'value'        => $value,
			'description'  => $description,
			'action_url'   => $action_url,
			'action_label' => $action_label,
		);
	}
}
