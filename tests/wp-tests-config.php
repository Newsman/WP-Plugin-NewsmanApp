<?php
/**
 * WordPress test suite configuration.
 *
 * Every value can be overridden from the environment so the same file serves
 * local runs and CI. The database is DROPPED AND RECREATED on every run, so
 * always point it at a dedicated database.
 *
 * @package NewsmanApp for WordPress
 */

$newsman_tests_root = dirname( __DIR__ );

$newsman_tests_env = function ( $name, $default_value ) {
	$value = getenv( $name );
	return ( false === $value || '' === $value ) ? $default_value : $value;
};

// WordPress core installed by roots/wordpress (composer install).
define( 'ABSPATH', $newsman_tests_env( 'WP_TESTS_ABSPATH', $newsman_tests_root . '/tests/wordpress' ) . '/' );

// wp-content lives outside the core checkout; WooCommerce is installed here by tests/bin/install-woocommerce.sh.
define( 'WP_CONTENT_DIR', $newsman_tests_env( 'WP_TESTS_CONTENT_DIR', $newsman_tests_root . '/tests/tmp/wp-content' ) );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

define( 'DB_NAME', $newsman_tests_env( 'WP_TESTS_DB_NAME', 'newsman_wp_test' ) );
define( 'DB_USER', $newsman_tests_env( 'WP_TESTS_DB_USER', 'newsman_test' ) );
define( 'DB_PASSWORD', $newsman_tests_env( 'WP_TESTS_DB_PASSWORD', 'newsman_test' ) );
define( 'DB_HOST', $newsman_tests_env( 'WP_TESTS_DB_HOST', 'localhost' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'newsman.test' );
define( 'WP_TESTS_EMAIL', 'admin@newsman.test' );
define( 'WP_TESTS_TITLE', 'NewsmanApp Test Site' );
define( 'WP_PHP_BINARY', $newsman_tests_env( 'WP_PHP_BINARY', PHP_BINARY ) );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
