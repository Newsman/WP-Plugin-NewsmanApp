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
 * Client Export Retriever Cron Subscribers to API Newsman
 *
 * @class Newsman_Export_Retriever_CronSubscribers
 */
class Newsman_Export_Retriever_CronSubscribers implements Newsman_Export_Retriever_Interface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Default batch API size
	 */
	public const BATCH_SIZE = 9000;

	/**
	 * Config
	 *
	 * @var Newsman_Config
	 */
	protected $config;

	/**
	 * Logger
	 *
	 * @var Newsman_WC_Logger
	 */
	protected $logger;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Newsman_Config::init();
		$this->logger = Newsman_WC_Logger::init();
	}

	/**
	 * Process cron subscribers retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws Exception On errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		$start    = ! empty( $data['start'] ) && $data['start'] > 0 ? $data['start'] : 0;
		$limit    = empty( $data['limit'] ) ? self::DEFAULT_PAGE_SIZE : $data['limit'];
		$cronlast = ! empty( $data['cronlast'] ) && 'true' === $data['cronlast'] ? true : false;

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
			/* translators: 1: Method, 2: Batch start, 3: Batch end, 4: WP blog ID */
				esc_html__( 'Export subscribers %1$s %2$d, %3$d, blog ID %4$s', 'newsman' ),
				$data['method'],
				$start,
				$limit,
				$blog_id
			)
		);

		$result      = array();
		$subscribers = $this->get_subscribers( $blog_id, $start, $limit, $cronlast );

		if ( empty( $subscribers ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			return array( 'status' => esc_html__( 'No subscribers found.', 'newsman' ) );
		}

		$count_subscribers = count( $subscribers );
		foreach ( $subscribers as $subscriber ) {
			try {
				if ( ! $this->is_valid_subscriber( $subscriber ) ) {
					continue;
				}
				$result[] = $this->process_subscriber( $subscriber, $blog_id );
			} catch ( Exception $e ) {
				$this->logger->error( $e->getCode() . ' ' . $e->getMessage() );
			}
		}

		$batches = array_chunk( $result, self::BATCH_SIZE );
		unset( $subscribers );
		unset( $result );

		$count       = 0;
		$api_results = array();
		foreach ( $batches as $batch ) {
			try {
				$context = new Newsman_Service_Context_ExportCsvSubscribers();
				$context->set_blog_id( $blog_id )
					->set_list_id( $this->config->get_list_id() )
					->set_segment_id( $this->config->get_segment_id() )
					->set_csv_data( $batch );

				$export        = new Newsman_Service_ExportCsvSubscribers();
				$api_results[] = $export->execute( $context );

				$count += count( $batch );

				unset( $context );
				unset( $export );
			} catch ( Exception $e ) {
				$this->logger->error( $e->getCode() . ' ' . $e->getMessage() );
			}
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Method, 2: Batch start, 3: Batch end, 4: WP blog ID */
				esc_html__( 'Exported subscribers %1$s %2$d, %3$d, blog ID %4$s', 'newsman' ),
				$data['method'],
				$start,
				$limit,
				$blog_id
			)
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return array(
			'status'  => sprintf(
				/* translators: 1: Sent subscribers count, 2: Total subscribers count */
				esc_html__( 'Sent to Newsman %1$d subscribers out of a total of %2$d.', 'newsman' ),
				$count,
				$count_subscribers
			),
			'results' => $api_results,
		);
	}

	/**
	 * Fetch subscribers
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @param null|int $start Start batch.
	 * @param null|int $limit Limit batch.
	 * @param bool     $cronlast Is last entities.
	 * @return array
	 */
	public function get_subscribers( $blog_id, $start, $limit, $cronlast ) {
		return array();
	}

	/**
	 * Process subscriber
	 *
	 * @param mixed    $subscriber Subscriber.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process_subscriber( $subscriber, $blog_id = null ) {
		return array();
	}

	/**
	 * Is valid subscriber
	 *
	 * @param mixed    $subscriber Subscriber.
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_valid_subscriber( $subscriber, $blog_id = null ) {
		return true;
	}

	/**
	 * Is different WP blog than current
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return bool
	 */
	public function is_different_blog( $blog_id = null ) {
		$current_blog_id = get_current_blog_id();
		return ( null !== $current_blog_id ) && ( (int) $blog_id !== $current_blog_id );
	}

	/**
	 * Clean telephone string
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public function clean_phone( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}
		$phone = str_replace( '+', '', $phone );
		$phone = preg_replace( '/\s\s+/', ' ', $phone );
		return trim( $phone );
	}
}
