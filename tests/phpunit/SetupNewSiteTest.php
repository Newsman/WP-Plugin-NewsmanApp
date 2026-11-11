<?php
/**
 * Tests for the setup of a new site of a network.
 *
 * @package NewsmanApp for WordPress
 */

/**
 * New network site setup tests. Run the suite with WP_MULTISITE=1.
 */
class SetupNewSiteTest extends NewsmanTestCase {
	/**
	 * Skip outside multisite.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run the suite with WP_MULTISITE=1.' );
		}
	}

	/**
	 * Mark the plugin active on the whole network, or not.
	 *
	 * @param bool $active Active network wide.
	 * @return void
	 */
	private function set_network_active( $active ) {
		// The name newsmanapp.php checks: plugin_basename() of the main plugin file.
		$name    = plugin_basename( dirname( __DIR__, 2 ) . '/newsmanapp.php' );
		$plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( $active ) {
			$plugins[ $name ] = time();
		} else {
			unset( $plugins[ $name ] );
		}
		update_site_option( 'active_sitewide_plugins', $plugins );
	}

	/**
	 * Setup version of a site.
	 *
	 * @param int $blog_id Site ID.
	 * @return mixed
	 */
	private function setup_version_of( $blog_id ) {
		switch_to_blog( $blog_id );
		$version = get_option( 'newsman_setup_version' );
		restore_current_blog();
		return $version;
	}

	/**
	 * A new site gets the Newsman setup when the plugin is network active, through
	 * wp_initialize_site (no deprecated wpmu_new_blog notice), even for an
	 * administrator: the old hook checked an activation nonce and stopped the
	 * request that adds the site ("The link you followed has expired.").
	 */
	public function test_new_site_is_set_up_when_network_active() {
		$this->set_network_active( true );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		grant_super_admin( get_current_user_id() );

		global $wpdb;
		$wpdb->last_error = '';
		$blog_id          = self::factory()->blog->create();
		$this->assertSame( '', $wpdb->last_error, 'No database error while the site is added.' );

		switch_to_blog( $blog_id );
		$as_table = $wpdb->prefix . 'actionscheduler_actions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check, must not be cached.
		$has_table = ! class_exists( '\ActionScheduler' ) || $as_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $as_table ) ) );
		restore_current_blog();
		if ( $has_table ) {
			$this->assertNotEmpty( $this->setup_version_of( $blog_id ), 'Set up when the site is added.' );
		} else {
			// Without the Action Scheduler tables yet, the site is set up on its first request.
			$this->assertEmpty( $this->setup_version_of( $blog_id ) );
			switch_to_blog( $blog_id );
			\Newsman\Setup::is_setup_executed();
			restore_current_blog();
			$this->assertNotEmpty( $this->setup_version_of( $blog_id ), 'Set up on the first request of the site.' );
		}
		$this->assertFalse( has_action( 'wpmu_new_blog' ), 'The deprecated hook must not be used on WordPress 5.1+.' );
		$this->set_network_active( false );
	}

	/**
	 * Without network activation a new site is left alone.
	 */
	public function test_new_site_is_not_set_up_when_not_network_active() {
		$this->set_network_active( false );

		$blog_id = self::factory()->blog->create();

		$this->assertEmpty( $this->setup_version_of( $blog_id ) );
	}
}
