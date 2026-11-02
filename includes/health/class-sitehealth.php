<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman Site Health integration.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Health;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the NewsMAN tests to Tools > Site Health > Status and the NewsMAN
 * section to Tools > Site Health > Info.
 *
 * @class \Newsman\Health\SiteHealth
 */
class SiteHealth {
	/**
	 * REST namespace of the asynchronous connection test.
	 */
	public const REST_NAMESPACE = 'newsman/v1';

	/**
	 * REST route of the asynchronous connection test.
	 */
	public const REST_ROUTE = '/site-health/connection';

	/**
	 * Health checks, created on first use.
	 *
	 * @var Checks|null
	 */
	protected $checks;

	/**
	 * Class construct
	 *
	 * @param null|Checks $checks Health checks.
	 */
	public function __construct( $checks = null ) {
		$this->checks = $checks;
	}

	/**
	 * Register the Site Health hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add the NewsMAN tests.
	 *
	 * @param array $tests Site Health tests.
	 * @return array
	 */
	public function add_tests( $tests ) {
		if ( ! is_array( $tests ) ) {
			$tests = array();
		}

		$connection = array(
			'label' => __( 'NewsMAN connection', 'newsman' ),
			'test'  => array( $this, 'test_connection' ),
		);
		if ( $this->is_rest_async_supported() ) {
			// The connection test calls the NewsMAN API, so it runs after the page loads.
			$tests['async']['newsman_connection'] = array(
				'label'             => $connection['label'],
				'test'              => rest_url( self::REST_NAMESPACE . self::REST_ROUTE ),
				'has_rest'          => true,
				'async_direct_test' => $connection['test'],
			);
		} else {
			$tests['direct']['newsman_connection'] = $connection;
		}

		$tests['direct']['newsman_api_access'] = array(
			'label' => __( 'NewsMAN API access', 'newsman' ),
			'test'  => array( $this, 'test_api_access' ),
		);

		if ( $this->get_checks()->has_action_scheduler() ) {
			$tests['direct']['newsman_action_scheduler'] = array(
				'label' => __( 'NewsMAN background tasks', 'newsman' ),
				'test'  => array( $this, 'test_action_scheduler' ),
			);
		}

		if ( \Newsman\Remarketing\Config::init()->use_remarketing() ) {
			$tests['direct']['newsman_remarketing'] = array(
				'label' => __( 'NewsMAN remarketing', 'newsman' ),
				'test'  => array( $this, 'test_remarketing' ),
			);
		}

		return $tests;
	}

	/**
	 * Register the REST route of the asynchronous connection test.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_test_connection' ),
				'permission_callback' => array( $this, 'can_view_site_health' ),
			)
		);
	}

	/**
	 * Whether the current user may run the Site Health tests.
	 *
	 * @return bool
	 */
	public function can_view_site_health() {
		return current_user_can( 'view_site_health_checks' );
	}

	/**
	 * REST callback of the connection test.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function rest_test_connection() {
		$result = $this->test_connection();
		return function_exists( 'rest_ensure_response' ) ? rest_ensure_response( $result ) : $result;
	}

	/**
	 * Test the API credentials and the selected list.
	 *
	 * @return array
	 */
	public function test_connection() {
		$checks      = $this->get_checks();
		$credentials = $checks->check_credentials();

		if ( Checks::STATUS_GOOD !== $credentials['status'] ) {
			$label = $checks->has_credentials()
				? __( 'NewsMAN rejects the saved API key and User ID', 'newsman' )
				: __( 'The NewsMAN plugin is not connected to a NewsMAN account', 'newsman' );
			return $this->build_result( 'newsman_connection', $label, $credentials );
		}

		$list = $checks->check_list();
		if ( Checks::STATUS_GOOD !== $list['status'] ) {
			$label = ( Checks::STATUS_CRITICAL === $list['status'] )
				? __( 'The NewsMAN list selected in the plugin was not found', 'newsman' )
				: __( 'No NewsMAN list is selected', 'newsman' );
			return $this->build_result( 'newsman_connection', $label, $list );
		}

		$list['description'] = $credentials['description'] . ' ' . sprintf(
			/* translators: %s: NewsMAN list name and ID */
			__( 'Subscribers are added to the list %s.', 'newsman' ),
			$list['value']
		);
		return $this->build_result( 'newsman_connection', __( 'The NewsMAN plugin is connected', 'newsman' ), $list );
	}

	/**
	 * Test that NewsMAN may read data from the store.
	 *
	 * @return array
	 */
	public function test_api_access() {
		$check = $this->get_checks()->check_api_access();
		$label = ( Checks::STATUS_GOOD === $check['status'] )
			? __( 'NewsMAN can read data from this site', 'newsman' )
			: __( 'NewsMAN cannot read data from this site', 'newsman' );
		return $this->build_result( 'newsman_api_access', $label, $check );
	}

	/**
	 * Test the NewsMAN background tasks.
	 *
	 * @return array
	 */
	public function test_action_scheduler() {
		$check = $this->get_checks()->check_action_scheduler();
		$label = ( Checks::STATUS_RECOMMENDED === $check['status'] )
			? __( 'NewsMAN background tasks need attention', 'newsman' )
			: __( 'NewsMAN background tasks run on time', 'newsman' );

		$check['description'] = $check['value'] . '. ' . $check['description'];
		return $this->build_result( 'newsman_action_scheduler', $label, $check );
	}

	/**
	 * Test the remarketing tracker. Added only when remarketing is turned on.
	 *
	 * @return array
	 */
	public function test_remarketing() {
		$check = $this->get_checks()->check_remarketing();
		$label = ( Checks::STATUS_GOOD === $check['status'] )
			? __( 'NewsMAN remarketing is active', 'newsman' )
			: __( 'NewsMAN remarketing is turned on without a Remarketing ID', 'newsman' );
		return $this->build_result( 'newsman_remarketing', $label, $check );
	}

	/**
	 * Add the NewsMAN section to Tools > Site Health > Info.
	 * No NewsMAN API call is made here; the Status tab tests the connection.
	 *
	 * @param array $info Debug information sections.
	 * @return array
	 */
	public function add_debug_information( $info ) {
		if ( ! is_array( $info ) ) {
			$info = array();
		}

		$checks   = $this->get_checks();
		$config   = \Newsman\Config::init();
		$yes_no   = function ( $value ) {
			return array(
				'value' => $value ? __( 'Yes', 'newsman' ) : __( 'No', 'newsman' ),
				'debug' => $value ? 'yes' : 'no',
			);
		};
		$on_off   = function ( $value ) {
			return array(
				'value' => $value ? __( 'On', 'newsman' ) : __( 'Off', 'newsman' ),
				'debug' => $value ? 'on' : 'off',
			);
		};
		$list_id  = (string) $config->get_list_id();
		$rm       = \Newsman\Remarketing\Config::init();
		$has_woo  = $checks->has_woocommerce();
		$has_as   = $checks->has_action_scheduler();
		$fields   = array();
		$fields[] = array(
			'key'   => 'version',
			'label' => __( 'Plugin version', 'newsman' ),
			'value' => NEWSMAN_VERSION,
		);
		$fields[] = array_merge(
			array(
				'key'   => 'credentials',
				'label' => __( 'API key and User ID saved', 'newsman' ),
			),
			$yes_no( $checks->has_credentials() )
		);
		$fields[] = array(
			'key'   => 'list_id',
			'label' => __( 'List ID', 'newsman' ),
			'value' => ( '' === $list_id ) ? __( 'Not selected', 'newsman' ) : $list_id,
			'debug' => ( '' === $list_id ) ? 'none' : $list_id,
		);
		$fields[] = array_merge(
			array(
				'key'   => 'api_access',
				'label' => __( 'Allow API access', 'newsman' ),
			),
			$on_off( $config->use_api() )
		);
		$fields[] = array_merge(
			array(
				'key'   => 'remarketing',
				'label' => __( 'Remarketing', 'newsman' ),
			),
			$on_off( $rm->use_remarketing() )
		);
		$fields[] = array(
			'key'   => 'remarketing_id',
			'label' => __( 'Remarketing ID', 'newsman' ),
			'value' => ( '' === (string) $rm->get_id() ) ? __( 'Not set', 'newsman' ) : (string) $rm->get_id(),
			'debug' => ( '' === (string) $rm->get_id() ) ? 'none' : (string) $rm->get_id(),
		);
		$fields[] = array(
			'key'   => 'woocommerce',
			'label' => __( 'WooCommerce', 'newsman' ),
			'value' => $has_woo ? $checks->get_woocommerce_version() : __( 'Not active', 'newsman' ),
			'debug' => $has_woo ? $checks->get_woocommerce_version() : 'not active',
		);
		$fields[] = array_merge(
			array(
				'key'   => 'action_scheduler',
				'label' => __( 'Action Scheduler available', 'newsman' ),
			),
			$yes_no( $has_as )
		);
		$fields[] = array_merge(
			array(
				'key'   => 'use_action_scheduler',
				'label' => __( 'Use Action Scheduler', 'newsman' ),
			),
			$on_off( $config->use_action_scheduler() )
		);

		$counts = $has_as ? $checks->get_action_scheduler_counts() : null;
		if ( is_array( $counts ) ) {
			$fields[] = array(
				'key'   => 'tasks_pending',
				'label' => __( 'Pending background tasks', 'newsman' ),
				'value' => $counts['pending'],
			);
			$fields[] = array(
				'key'   => 'tasks_past_due',
				'label' => __( 'Past due background tasks', 'newsman' ),
				'value' => $counts['past_due'],
			);
			$fields[] = array(
				'key'   => 'tasks_failed',
				'label' => __( 'Failed background tasks in the last 7 days', 'newsman' ),
				'value' => $counts['failed'],
			);
		}

		foreach ( $checks->get_last_exports() as $export ) {
			$fields[] = array(
				'key'   => 'last_export_' . $export['type'],
				/* translators: %s: export type, for example "Orders" */
				'label' => sprintf( __( 'Last export: %s', 'newsman' ), $export['label'] ),
				'value' => $export['value'],
				'debug' => ( null === $export['time'] ) ? 'never' : gmdate( 'Y-m-d H:i:s', $export['time'] ) . ' UTC',
			);
		}

		$section_fields = array();
		foreach ( $fields as $field ) {
			$key = $field['key'];
			unset( $field['key'] );
			$section_fields[ $key ] = $field;
		}

		$info['newsman'] = array(
			'label'  => __( 'NewsMAN', 'newsman' ),
			'fields' => apply_filters( 'newsman_site_health_debug_fields', $section_fields ),
		);

		return $info;
	}

	/**
	 * Build a Site Health test result from a check.
	 *
	 * @param string $test  Test name.
	 * @param string $label Result title.
	 * @param array  $check Check result from Checks.
	 * @return array
	 */
	protected function build_result( $test, $label, $check ) {
		$status = $check['status'];
		if ( Checks::STATUS_INFO === $status ) {
			$status = Checks::STATUS_RECOMMENDED;
		}

		$actions = '';
		if ( ! empty( $check['action_url'] ) ) {
			$actions = sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( $check['action_url'] ),
				esc_html( $check['action_label'] )
			);
		}

		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'NewsMAN', 'newsman' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $check['description'] ) . '</p>',
			'actions'     => $actions,
			'test'        => $test,
		);
	}

	/**
	 * Whether Site Health runs asynchronous tests through REST (WordPress 5.6 and later).
	 *
	 * @return bool
	 */
	protected function is_rest_async_supported() {
		return function_exists( 'rest_url' ) && version_compare( get_bloginfo( 'version' ), '5.6', '>=' );
	}

	/**
	 * Get the health checks.
	 *
	 * @return Checks
	 */
	protected function get_checks() {
		if ( null === $this->checks ) {
			$this->checks = new Checks();
		}
		return $this->checks;
	}
}
