<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite, WooCommerce (when
 * installed in tests/tmp/wp-content/plugins) and the plugin itself.
 *
 * Environment variables:
 * - NEWSMAN_TESTS_SKIP_WOOCOMMERCE=1 runs the suite without WooCommerce even
 *   when it is installed (WooCommerce-dependent tests are skipped).
 *
 * @package NewsmanApp for WordPress
 */

$newsman_tests_root = dirname( __DIR__ );

require_once $newsman_tests_root . '/vendor/autoload.php';

$newsman_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( empty( $newsman_tests_dir ) || ! file_exists( $newsman_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find the WordPress test library. Run: composer install\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

if ( ! file_exists( $newsman_tests_root . '/tests/wordpress/wp-settings.php' ) ) {
	fwrite( STDERR, "Could not find WordPress core in tests/wordpress. Run: composer install\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

$newsman_tests_content_dir = $newsman_tests_root . '/tests/tmp/wp-content';
foreach ( array( '', '/plugins', '/mu-plugins', '/uploads' ) as $newsman_tests_sub_dir ) {
	if ( ! is_dir( $newsman_tests_content_dir . $newsman_tests_sub_dir ) ) {
		mkdir( $newsman_tests_content_dir . $newsman_tests_sub_dir, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
	}
}

require_once $newsman_tests_dir . '/includes/functions.php';

$newsman_tests_woocommerce = $newsman_tests_content_dir . '/plugins/woocommerce/woocommerce.php';
$newsman_tests_with_wc     = file_exists( $newsman_tests_woocommerce ) && '1' !== getenv( 'NEWSMAN_TESTS_SKIP_WOOCOMMERCE' );

tests_add_filter(
	'muplugins_loaded',
	function () use ( $newsman_tests_root, $newsman_tests_woocommerce, $newsman_tests_with_wc ) {
		if ( $newsman_tests_with_wc ) {
			require_once $newsman_tests_woocommerce;
		}
		require_once $newsman_tests_root . '/newsmanapp.php';
	}
);

if ( $newsman_tests_with_wc ) {
	// Same sequence WooCommerce uses in its own test bootstrap: create tables and
	// roles once WordPress is installed, then reload the capabilities.
	tests_add_filter(
		'setup_theme',
		function () {
			echo 'Installing WooCommerce...' . PHP_EOL;
			WC_Install::install();
			if ( class_exists( '\Automattic\WooCommerce\Admin\Install' ) && method_exists( '\Automattic\WooCommerce\Admin\Install', 'create_tables' ) ) {
				\Automattic\WooCommerce\Admin\Install::create_tables();
			}
			$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_roles();
		}
	);
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output before WordPress is loaded.
echo 'WordPress core: ' . $newsman_tests_root . '/tests/wordpress' . PHP_EOL;
echo 'WooCommerce   : ' . ( $newsman_tests_with_wc ? 'loaded from ' . dirname( $newsman_tests_woocommerce ) : 'not loaded' ) . PHP_EOL;
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

require_once $newsman_tests_dir . '/includes/bootstrap.php';

require_once $newsman_tests_root . '/tests/phpunit/NewsmanTestCase.php';
