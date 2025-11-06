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

namespace Newsman\Export\Retriever;

use Newsman\Config;
use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Util\Telephone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Cron Subscribers to API Newsman
 *
 * @class \Newsman\Export\Retriever\CronSubscribers
 */
class CronSubscribers extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Default batch API size
	 */
	public const BATCH_SIZE = 9000;

	/**
	 * Subscribers emails cache
	 *
	 * @var array
	 */
	protected $emails_cache = array();

	/**
	 * Process cron subscribers retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws \Exception On errors.
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

		$count_subscribers = 0;
		foreach ( $subscribers as $subscriber ) {
			try {
				if ( ! $this->is_valid_subscriber( $subscriber ) ) {
					continue;
				}
				$data = $this->process_subscriber( $subscriber, $blog_id );
				if ( false === $data ) {
					continue;
				}
				$result[] = $data;
				++$count_subscribers;
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		unset( $subscribers );
		$batches = array_chunk( $result, self::BATCH_SIZE );
		unset( $result );

		$count       = 0;
		$api_results = array();
		foreach ( $batches as $batch ) {
			try {
				$context = new \Newsman\Service\Context\ExportCsvSubscribers();
				$context->set_blog_id( $blog_id )
					->set_list_id( $this->config->get_list_id() )
					->set_segment_id( $this->config->get_segment_id() )
					->set_csv_data( $batch )
					->set_additional_fields( $this->remarketing_config->get_customer_attributes() );

				$export        = new \Newsman\Service\ExportCsvSubscribers();
				$api_results[] = $export->execute( $context );

				$count += count( $batch );

				unset( $context );
				unset( $export );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
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
}
