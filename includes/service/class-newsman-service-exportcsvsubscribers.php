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
 * API Client Service Export Csv Subscribers
 *
 * @class Newsman_Service_ExportCsvSubscribers
 */
class Newsman_Service_ExportCsvSubscribers extends Newsman_Service_Abstract_Service {
	/**
	 * Export CSV with subscribers to Newsman API endpoint
	 *
	 * @see https://kb.newsman.com/api/1.2/import.csv
	 */
	public const ENDPOINT = 'import.csv';

	/**
	 * Export CSV subscribers
	 *
	 * @param Newsman_Service_Context_ExportCsvSubscribers $context Export CSV subscribers context.
	 * @return array
	 * @throws Exception Throw exception on errors.
	 */
	public function execute( $context ) {
		$api_context = $this->create_api_context()
			->set_list_id( $context->get_list_id() )
			->set_blog_id( $context->get_blog_id() )
			->set_endpoint( self::ENDPOINT );

		$this->logger->info(
			sprintf(
				/* translators: 1: Count subscribers */
				esc_html__( 'Try to export CSV with %s subscribers', 'newsman' ),
				count( $context->get_csv_data() )
			)
		);

		$client = $this->create_api_client();
		$result = $client->post(
			$api_context,
			array(
				'list_id'  => $context->get_list_id(),
				'segments' => ! empty( $context->get_segment_id() ) ? array( $context->get_segment_id() )
					: $context->get_null_value(),
				'csv_data' => $this->serialize_csv_data( $context ),
			)
		);

		if ( $client->has_error() ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( esc_html__( $client->get_error_message(), 'newsman' ), $client->get_error_code() );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Count subscribers */
				esc_html__( 'Sent export CSV with %s subscribers', 'newsman' ),
				count( $context->get_csv_data() )
			)
		);

		return $result;
	}

	/**
	 * Create CSV file format and return it.
	 *
	 * @param Newsman_Service_Context_ExportCsvSubscribers $context Context.
	 * @param string                                       $source Source column value.
	 * @return string
	 */
	public function serialize_csv_data( $context, $source = 'WP CRON' ) {
		$header            = $this->get_csv_header( $context );
		$column_count      = count( $header );
		$csv_data          = $context->get_csv_data();
		$additional_fields = $context->get_additional_fields();

		$csv = '"' . implode( '","', $this->get_csv_header( $context ) ) . "\"\n";
		foreach ( $csv_data as $key => $row ) {
			$export_row = array_combine( $header, array_fill( 0, $column_count, '' ) );
			foreach ( $row as $column => &$value ) {
				if ( 'additional' !== $column ) {
					if ( null === $value ) {
						$value = '';
					}
					$value = trim( str_replace( '"', '', $value ) );
				} elseif ( null === $value ) {
					$value = array();
				}
			}
			$row['source'] = $source;

			foreach ( $additional_fields as $attribute_code => $field ) {
				$row[ $field ] = '';
				if ( isset( $row['additional'][ $attribute_code ] ) ) {
					$row[ $field ] = $row['additional'][ $attribute_code ];
				}
			}

			foreach ( $export_row as $export_key => &$export_value ) {
				if ( isset( $row[ $export_key ] ) ) {
					$export_value = $row[ $export_key ];
				}
			}

			$csv .= $this->get_csv_line( $export_row, $key );
		}

		return $csv;
	}

	/**
	 * Get CSV header
	 *
	 * @param Newsman_Service_Context_ExportCsvSubscribers $context Context.
	 * @return array
	 */
	public function get_csv_header( $context ) {
		$header = array(
			'email',
			'firstname',
			'lastname',
		);

		$header[] = 'tel';
		$header[] = 'telephone';
		$header[] = 'billing_telephone';
		$header[] = 'shipping_telephone';

		$header[] = 'source';

		foreach ( $this->get_additional_fields_names( $context ) as $field ) {
			if ( ! in_array( $field, $header, true ) ) {
				$header[] = $field;
			}
		}
		return $header;
	}

	/**
	 * Get CSV line
	 *
	 * @param array $row CSV data rpw.
	 * @param int   $key Index key.
	 * @return string
	 */
	public function get_csv_line( $row, $key ) {
		unset( $row['additional'] );
		return '"' . implode( '","', $row ) . "\"\n";
	}

	/**
	 * Get additional fields names
	 *
	 * @param Newsman_Service_Context_ExportCsvSubscribers $context Context.
	 * @return array
	 */
	public function get_additional_fields_names( $context ) {
		return array_values( $context->get_additional_fields() );
	}
}
