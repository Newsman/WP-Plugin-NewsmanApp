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

use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Export Retriever Users
 *
 * @class \Newsman\Export\Retriever\Users
 */
class Users implements RetrieverInterface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Remarketing Config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Allowed rows to export
	 *
	 * @var array
	 */
	protected $allowed_roles = array(
		'subscriber',
		'customer',
	);

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->remarketing_config = RemarketingConfig::init();
		$this->logger             = Logger::init();
	}

	/**
	 * Process users retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws \Exception On errors.
	 */
	public function process( $data = array(), $blog_id = null ) {
		if ( empty( $data['wp_newsman_internal_role'] ) ) {
			throw new \Exception( 'wp_newsman_internal_role is empty!' );
		}
		$role = $data['wp_newsman_internal_role'];
		if ( ! in_array( $role, $this->allowed_roles, true ) ) {
			/* translators: 1: User role */
			throw new \Exception(
				sprintf(
					/* translators: 1: User role */
					esc_html__( 'wp_newsman_internal_role %s is not allowed!', 'newsman' ),
					esc_html( $role )
				)
			);
		}

		$start = ! empty( $data['start'] ) && $data['start'] > 0 ? $data['start'] : 0;
		$limit = empty( $data['limit'] ) ? self::DEFAULT_PAGE_SIZE : $data['limit'];

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: User role, 2: Batch start, 3: Batch end, 4: WP blog ID */
				esc_html__( 'Export %1$s %2$d, %3$d, blog ID %4$s', 'newsman' ),
				$role,
				$start,
				$limit,
				$blog_id
			)
		);

		$args      = array(
			'role'   => $role,
			'offset' => $start,
			'number' => $limit,
		);
		$customers = get_users( $args );

		if ( empty( $customers ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			return array();
		}

		$result = array();
		foreach ( $customers as $customer ) {
			try {
				$result[] = $this->process_customer( $customer, $role, $blog_id );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Exported %1$s %2$d, %3$d, blog ID %4$s', 'newsman' ),
				$role,
				$start,
				$limit,
				$blog_id
			)
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Process customer
	 *
	 * @param \WC_Customer $customer Customer instance.
	 * @param null|int     $role Role.
	 * @param null|int     $blog_id WP blog ID.
	 * @return array
	 */
	public function process_customer( $customer, $role, $blog_id = null ) {
		$data = get_user_meta( $customer->data->ID );

		$row = array(
			'email'     => $customer->data->user_email,
			'firstname' => $data['first_name'][0],
			'lastname'  => $data['last_name'][0],
		);

		return $row;
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
}
