<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman custom SQL retriever class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Export\Retriever;

use PHPSQLParser\PHPSQLParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Custom SQL
 *
 * Executes SELECT-only SQL queries with WordPress table prefix placeholder replacement.
 * Table names use {table_name} syntax, e.g. {posts} becomes wp_posts.
 *
 * @class \Newsman\Export\Retriever\CustomSql
 */
class CustomSql extends AbstractRetriever implements RetrieverInterface {

	/**
	 * Statement types that are not allowed.
	 *
	 * @var array
	 */
	protected $disallowed_statements = array(
		'INSERT',
		'UPDATE',
		'DELETE',
		'DROP',
		'ALTER',
		'TRUNCATE',
		'CREATE',
		'REPLACE',
		'RENAME',
		'SET',
		'GRANT',
		'REVOKE',
		'LOCK',
		'UNLOCK',
	);

	/**
	 * Process custom SQL retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws \Exception Throws exception on invalid input.
	 */
	public function process( $data = array(), $blog_id = null ) {
		$sql = $this->get_raw_sql();

		if ( empty( $sql ) ) {
			throw new \Exception( 'The "sql" parameter is required.' );
		}

		$this->validate_select_only( $sql );

		$sql = $this->replace_table_placeholders( $sql, $blog_id );

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_results( $sql, ARRAY_A );

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		if ( null === $result ) {
			throw new \Exception( 'SQL query error: ' . $wpdb->last_error );
		}

		return $result;
	}

	/**
	 * Get raw SQL from request, bypassing sanitize_text_field which corrupts SQL operators.
	 *
	 * @return string
	 */
	protected function get_raw_sql() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$sql = isset( $_POST['sql'] ) ? wp_unslash( $_POST['sql'] ) : '';
		if ( empty( $sql ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
			$sql = isset( $_GET['sql'] ) ? wp_unslash( $_GET['sql'] ) : '';
		}
		return trim( (string) $sql );
	}

	/**
	 * Validate that the SQL is a SELECT-only query.
	 *
	 * @param string $sql SQL query.
	 * @return void
	 * @throws \Exception Throws exception if query is not SELECT-only.
	 */
	protected function validate_select_only( $sql ) {
		$this->validate_no_multiple_statements( $sql );

		$parser = new PHPSQLParser();
		$parsed = $parser->parse( $sql );

		if ( empty( $parsed ) ) {
			throw new \Exception( 'Unable to parse the SQL query.' );
		}

		$statement_type = key( $parsed );

		if ( 'SELECT' !== $statement_type ) {
			throw new \Exception( 'Only SELECT queries are allowed. Got: ' . $statement_type );
		}

		if ( isset( $parsed['INTO'] ) ) {
			throw new \Exception( 'SELECT INTO is not allowed.' );
		}
	}

	/**
	 * Check for semicolons outside of string literals.
	 *
	 * @param string $sql SQL query.
	 * @return void
	 * @throws \Exception Throws exception if multiple statements detected.
	 */
	protected function validate_no_multiple_statements( $sql ) {
		$stripped = preg_replace( "/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/s", '', $sql );
		$stripped = preg_replace( '/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/s', '', $stripped );

		if ( strpos( $stripped, ';' ) !== false ) {
			throw new \Exception( 'Multiple statements are not allowed.' );
		}
	}

	/**
	 * Replace {table_name} placeholders with prefixed table names.
	 *
	 * @param string   $sql SQL query with placeholders.
	 * @param null|int $blog_id WP blog ID.
	 * @return string SQL with resolved table names.
	 */
	protected function replace_table_placeholders( $sql, $blog_id = null ) {
		global $wpdb;

		$prefix = $wpdb->prefix;
		if ( null !== $blog_id && function_exists( 'is_multisite' ) && is_multisite() ) {
			$prefix = $wpdb->get_blog_prefix( $blog_id );
		}

		return preg_replace_callback(
			'/\{([a-zA-Z0-9_]+)\}/',
			function ( $matches ) use ( $prefix ) {
				return $prefix . $matches[1];
			},
			$sql
		);
	}
}
