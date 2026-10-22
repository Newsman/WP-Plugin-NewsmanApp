<?php
/**
 * Base test case for the NewsmanApp plugin.
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Shared helpers: enabling the API gate, creating users, WooCommerce guards.
 */
abstract class NewsmanTestCase extends WP_UnitTestCase {
	/**
	 * API key stored in the plugin options by enable_api().
	 */
	public const API_KEY = 'test-api-key-0123456789';

	/**
	 * Store the options that make Config::is_enabled_with_api() return true.
	 *
	 * @param string $api_key API key to store.
	 * @return void
	 */
	protected function enable_api( $api_key = self::API_KEY ) {
		update_option( 'newsman_apikey', $api_key );
		update_option( 'newsman_userid', '42' );
		update_option( 'newsman_api', 'on' );
		update_option( 'newsman_list', '1' );
		$this->mark_plugin_active();
	}

	/**
	 * Add the plugin to the active_plugins option, as Config::is_active() checks it.
	 *
	 * @return void
	 */
	protected function mark_plugin_active() {
		$active = (array) get_option( 'active_plugins', array() );
		if ( ! in_array( \WP_Newsman::NZ_PLUGIN_PATH, $active, true ) ) {
			$active[] = \WP_Newsman::NZ_PLUGIN_PATH;
			update_option( 'active_plugins', $active );
		}
	}

	/**
	 * Whether WooCommerce was loaded by the bootstrap.
	 *
	 * @return bool
	 */
	protected function has_woocommerce() {
		return class_exists( '\WooCommerce' ) && function_exists( 'wc_create_order' );
	}

	/**
	 * Skip the current test unless WooCommerce is loaded.
	 *
	 * @return void
	 */
	protected function require_woocommerce() {
		if ( ! $this->has_woocommerce() ) {
			$this->markTestSkipped( 'WooCommerce is not installed in the test environment (see tests/bin/install-woocommerce.sh).' );
		}
	}

	/**
	 * Create a user with the given role, email and names.
	 *
	 * @param string $role       User role.
	 * @param string $email      Email address.
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param array  $extra      Extra arguments for the user factory.
	 * @return int User ID.
	 */
	protected function create_user( $role, $email, $first_name, $last_name, $extra = array() ) {
		return self::factory()->user->create(
			array_merge(
				array(
					'role'       => $role,
					'user_email' => $email,
					'user_login' => $email,
					'first_name' => $first_name,
					'last_name'  => $last_name,
				),
				$extra
			)
		);
	}

	/**
	 * Walk a paginated export and return the ordered list of collected IDs.
	 *
	 * @param callable $fetch_page Callback receiving ($start, $limit) and returning rows.
	 * @param string   $id_field   Row key holding the entity ID.
	 * @param int      $limit      Page size.
	 * @return array
	 */
	protected function walk_pages( $fetch_page, $id_field, $limit ) {
		$ids   = array();
		$start = 0;
		for ( $page = 0; $page < 50; $page++ ) {
			$rows = $fetch_page( $start, $limit );
			$this->assertLessThanOrEqual( $limit, count( $rows ), 'A page returned more rows than the limit.' );
			foreach ( $rows as $row ) {
				$ids[] = (int) $row[ $id_field ];
			}
			if ( count( $rows ) < $limit ) {
				break;
			}
			$start += $limit;
		}
		return $ids;
	}
}
