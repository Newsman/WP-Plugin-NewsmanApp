<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: NewsMAN WP-CLI commands.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Cli;

use Newsman\Config;
use Newsman\Export\Retriever\Pool;
use Newsman\Export\Retriever\SendOrders;
use Newsman\Export\Retriever\SubscribersWoocommerce;
use Newsman\Export\Retriever\SubscribersWordpress;
use Newsman\Health\Checks;
use Newsman\Logger;
use Newsman\Subscribe\Helper as SubscribeHelper;
use Newsman\Subscribe\Lists_Transient;
use Newsman\Subscribe\Segments_Transient;
use Newsman\Subscribe\SmsLists_Transient;
use Newsman\User\IpAddress;
use Newsman\Util\WooCommerceExist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage NewsMAN from the command line.
 *
 * The commands work on the site WordPress loads. On a multisite network,
 * choose the site with the global --url parameter, like with every WP-CLI
 * command.
 *
 * ## EXAMPLES
 *
 *     # Check the connection and the settings of the plugin.
 *     $ wp newsman status
 *
 *     # Print the first 5 products of the feed NewsMAN reads.
 *     $ wp newsman export products-feed --limit=5
 *
 *     # Subscribe an email on another site of a multisite network.
 *     $ wp newsman subscribe ana@example.com --url=example.com/shop2/
 *
 * @class \Newsman\Cli\Command
 */
class Command {
	/**
	 * Exports that only read data. The exports that create coupons, change subscriptions,
	 * send data to NewsMAN or run SQL are left out.
	 */
	public const EXPORT_TYPES = array(
		'products-feed',
		'customers',
		'orders',
		'subscribers',
		'subscribers-wordpress-feed',
		'subscribers-woocommerce-feed',
		'platform-name',
		'platform-version',
		'platform-language',
		'platform-language-version',
		'platform-has-products',
		'integration-name',
		'integration-version',
		'server-ip',
		'server-cloudflare',
		'sql-name',
		'sql-version',
	);

	/**
	 * Export arguments that are not filters.
	 */
	public const EXPORT_LIST_ARGS = array( 'start', 'limit', 'sort', 'order' );

	/**
	 * What the send command sends: type => retriever class.
	 */
	public const SEND_TYPES = array(
		'wordpress-subscribers'   => SubscribersWordpress::class,
		'woocommerce-subscribers' => SubscribersWoocommerce::class,
		'orders'                  => SendOrders::class,
	);

	/**
	 * Show the NewsMAN checks for this site.
	 *
	 * The same checks as the Status section of the NewsMAN page, and when
	 * NewsMAN last read each type of data. The command ends with exit code 1
	 * when a check finds a problem, so it can be used in monitoring.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp newsman status
	 *     $ wp newsman status --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		unset( $args );
		$format  = $this->get_format( $assoc_args, 'table' );
		$checks  = $this->create_checks();
		$rows    = array();
		$problem = false;

		foreach ( $this->get_status_checks( $checks ) as $check ) {
			$rows[] = array(
				'check'       => $check[0],
				'status'      => $check[1]['status'],
				'value'       => $check[1]['value'],
				'description' => $check[1]['description'],
			);
			if ( Checks::STATUS_CRITICAL === $check[1]['status'] ) {
				$problem = true;
			}
		}

		foreach ( $checks->get_last_exports() as $export ) {
			$rows[] = array(
				'check'       => __( 'Last exports', 'newsman' ) . ': ' . $export['label'],
				'status'      => Checks::STATUS_INFO,
				'value'       => $export['value'],
				'description' => __( 'When NewsMAN last read each type of data from this site.', 'newsman' ),
			);
		}

		if ( 'table' === $format ) {
			$this->log( $this->get_site_line() );
		}
		$this->format_items( $format, $rows, array( 'check', 'status', 'value', 'description' ) );

		if ( $problem ) {
			$this->halt( 1 );
		}
	}

	/**
	 * Print the data NewsMAN reads from this site.
	 *
	 * Runs the same export NewsMAN calls, without the API key, so you can see
	 * what NewsMAN receives. Only the exports that read data are available.
	 * Running it does not change when NewsMAN last read the data.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : The data to print.
	 * ---
	 * options:
	 *   - products-feed
	 *   - customers
	 *   - orders
	 *   - subscribers
	 *   - subscribers-wordpress-feed
	 *   - subscribers-woocommerce-feed
	 *   - platform-name
	 *   - platform-version
	 *   - platform-language
	 *   - platform-language-version
	 *   - platform-has-products
	 *   - integration-name
	 *   - integration-version
	 *   - server-ip
	 *   - server-cloudflare
	 *   - sql-name
	 *   - sql-version
	 * ---
	 *
	 * [--start=<start>]
	 * : Number of rows to skip.
	 *
	 * [--limit=<limit>]
	 * : Number of rows to print. Default: 1000, like NewsMAN.
	 *
	 * [--sort=<field>]
	 * : Sort field, for example created_at.
	 *
	 * [--order=<order>]
	 * : Sort order.
	 * ---
	 * options:
	 *   - asc
	 *   - desc
	 * ---
	 *
	 * [--<field>=<value>]
	 * : Only the rows where the field has this value, for example
	 * --email=ana@example.com or --order_id=123. Separate several IDs with
	 * commas, for example --order_ids=12,13.
	 *
	 * [--format=<format>]
	 * : Output format. Nested values are printed as JSON in csv, table and yaml.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - csv
	 *   - table
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp newsman export products-feed --limit=5
	 *     $ wp newsman export orders --email=ana@example.com --format=table
	 *     $ wp newsman export subscribers --limit=100 --format=csv > list.csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$type   = isset( $args[0] ) ? strtolower( (string) $args[0] ) : '';
		$format = $this->get_format( $assoc_args, 'json' );
		unset( $assoc_args['format'] );

		if ( ! in_array( $type, self::EXPORT_TYPES, true ) ) {
			$this->error(
				sprintf(
					/* translators: 1: Export type, 2: List of export types */
					__( 'The %1$s export is not available from the command line. Available exports: %2$s.', 'newsman' ),
					$type,
					implode( ', ', self::EXPORT_TYPES )
				)
			);
			return;
		}

		$entry = $this->get_pool_entry( $type );
		if ( ! empty( $entry['only_woocommerce'] ) && ! $this->has_woocommerce() ) {
			/* translators: %s: Export type */
			$this->error( sprintf( __( 'The %s export needs WooCommerce.', 'newsman' ), $type ) );
			return;
		}

		$error = $this->get_list_args_error( $assoc_args );
		if ( '' !== $error ) {
			$this->error( $error );
			return;
		}

		$result = null;
		$error  = '';
		try {
			$retriever = $this->create_pool()->get_retriever_by_code( $type, $assoc_args );
			$data      = $this->get_export_data( $retriever, $assoc_args );
			$error     = $this->get_export_args_error( $retriever, $type, $data );
			if ( '' === $error ) {
				$result = $retriever->process( $data, get_current_blog_id() );
			}
		} catch ( \Exception $e ) {
			$error = $e->getMessage();
		}

		if ( '' !== $error ) {
			$this->error( $error );
			return;
		}

		$this->print_export( $result, $format );
	}

	/**
	 * Send WordPress subscribers, WooCommerce subscribers or orders to NewsMAN.
	 *
	 * Subscribers go to the list and segment chosen in the plugin. Orders go to
	 * the list for remarketing. The rows are sent in batches, with a progress
	 * bar. A batch that fails is written to the NewsMAN log, the other batches
	 * are still sent, and the command ends with an error.
	 *
	 * Types:
	 *
	 * * wordpress-subscribers: WordPress users with the Subscriber role.
	 * * woocommerce-subscribers: billing name, email and phone of completed orders.
	 * * orders: all orders except drafts.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : What to send.
	 * ---
	 * options:
	 *   - wordpress-subscribers
	 *   - woocommerce-subscribers
	 *   - orders
	 * ---
	 *
	 * [--since=<date>]
	 * : Only users registered, or orders created, on or after this date
	 * (YYYY-MM-DD).
	 *
	 * [--batch-size=<number>]
	 * : Rows sent to NewsMAN in one request. Default: 1000 for subscribers (at
	 * most 9000), 200 for orders (at most 500).
	 *
	 * [--dry-run]
	 * : Show how many rows would be sent, without sending them.
	 *
	 * [--yes]
	 * : Send without asking for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp newsman send orders --since=2026-01-01 --dry-run
	 *     $ wp newsman send wordpress-subscribers --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function send( $args, $assoc_args ) {
		$type    = isset( $args[0] ) ? strtolower( (string) $args[0] ) : '';
		$blog_id = get_current_blog_id();
		$config  = Config::init();

		if ( ! isset( self::SEND_TYPES[ $type ] ) ) {
			$this->error(
				sprintf(
					/* translators: 1: Send type, 2: List of send types */
					__( 'Unknown type %1$s. Types: %2$s.', 'newsman' ),
					$type,
					implode( ', ', array_keys( self::SEND_TYPES ) )
				)
			);
			return;
		}

		$error = $this->get_connection_error( $config, $blog_id );
		if ( '' !== $error ) {
			$this->error( $error );
			return;
		}

		if ( 'wordpress-subscribers' !== $type && ! $this->has_woocommerce() ) {
			/* translators: %s: Send type */
			$this->error( sprintf( __( 'Sending %s needs WooCommerce.', 'newsman' ), $type ) );
			return;
		}

		$since = isset( $assoc_args['since'] ) ? trim( (string) $assoc_args['since'] ) : '';
		if ( '' !== $since && ! $this->is_valid_date( $since ) ) {
			$this->error( __( 'Use the YYYY-MM-DD format for --since, for example 2026-01-31.', 'newsman' ) );
			return;
		}

		$class      = self::SEND_TYPES[ $type ];
		$max_batch  = $class::BATCH_SIZE;
		$batch_size = isset( $assoc_args['batch-size'] ) ? (string) $assoc_args['batch-size'] : (string) $class::DEFAULT_PAGE_SIZE;
		if ( ! $this->is_whole_number( $batch_size ) || (int) $batch_size < 1 || (int) $batch_size > $max_batch ) {
			/* translators: %d: Largest batch size */
			$this->error( sprintf( __( 'Use a --batch-size from 1 to %d.', 'newsman' ), $max_batch ) );
			return;
		}
		$batch_size = (int) $batch_size;

		$retriever = $this->create_send_retriever( $type );
		$total     = (int) ( 'orders' === $type ?
			$retriever->get_count_orders( $blog_id, '' === $since ? null : $since ) :
			$retriever->get_count_subscribers( $blog_id, '' === $since ? null : $since ) );
		$list_id   = (string) $config->get_list_id( $blog_id );

		$this->log( $this->get_site_line() );

		if ( 0 === $total ) {
			$this->success( $this->get_nothing_to_send_message( $type ) );
			return;
		}

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$this->success(
				sprintf(
					/* translators: 1: Number of rows, 2: What is sent, for example orders, 3: NewsMAN list ID */
					__( 'Dry run: %1$d %2$s would be sent to the NewsMAN list %3$s.', 'newsman' ),
					$total,
					$this->get_send_label( $type, $total ),
					$list_id
				)
			);
			return;
		}

		$this->confirm(
			sprintf(
				/* translators: 1: Number of rows, 2: What is sent, for example orders, 3: NewsMAN list ID */
				__( 'Send %1$d %2$s to the NewsMAN list %3$s?', 'newsman' ),
				$total,
				$this->get_send_label( $type, $total ),
				$list_id
			),
			$assoc_args
		);

		$sent     = 0;
		$read     = 0;
		$failed   = false;
		$progress = $this->make_progress_bar( __( 'Sending to NewsMAN', 'newsman' ), $total );
		for ( $start = 0; $start < $total; $start += $batch_size ) {
			$data = array(
				'start' => $start,
				'limit' => $batch_size,
			);
			if ( '' !== $since ) {
				$data['date_created'] = $since;
			}

			try {
				$retriever->process( $data, $blog_id );
			} catch ( \Exception $e ) {
				Logger::init()->log_exception( $e );
				$failed = true;
			}

			$counts = $retriever->get_last_counts();
			$sent  += $counts['sent'];
			$read  += $counts['read'];
			if ( $counts['sent'] < $counts['read'] ) {
				$failed = true;
			}
			$progress->tick( min( $batch_size, $total - $start ) );
		}
		$progress->finish();

		if ( $failed ) {
			$this->error(
				sprintf(
					/* translators: 1: Number of rows sent, 2: Number of rows read, 3: What is sent, for example orders */
					__( 'Sent %1$d of %2$d %3$s to NewsMAN. Some batches failed, the NewsMAN log has the errors.', 'newsman' ),
					$sent,
					$read,
					$this->get_send_label( $type, $read )
				)
			);
			return;
		}

		/* translators: 1: Number of rows sent, 2: What is sent, for example orders */
		$this->success( sprintf( __( 'Sent %1$d %2$s to NewsMAN.', 'newsman' ), $sent, $this->get_send_label( $type, $sent ) ) );
	}

	/**
	 * Subscribe an email to the NewsMAN list of this site.
	 *
	 * Uses the list and the segment chosen in the plugin. Without --single-optin
	 * or --double-optin, the newsletter opt-in type from Settings is used. An
	 * email that unsubscribed or never confirmed is subscribed again, like with
	 * the checkout checkbox. An email that is already subscribed only gets the
	 * new name and segment.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Email address.
	 *
	 * [--firstname=<firstname>]
	 * : First name.
	 *
	 * [--lastname=<lastname>]
	 * : Last name.
	 *
	 * [--single-optin]
	 * : Subscribe the email right away.
	 *
	 * [--double-optin]
	 * : NewsMAN sends a confirmation email first; the email is subscribed after
	 * it is confirmed.
	 *
	 * [--segment=<segment-id>]
	 * : NewsMAN segment ID. Default: the segment chosen in the plugin.
	 *
	 * [--form-id=<form-id>]
	 * : NewsMAN form ID of the confirmation email, for double opt-in. Default:
	 * the Confirmation email Form ID from Settings.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp newsman subscribe ana@example.com --firstname=Ana --single-optin
	 *     $ wp newsman subscribe ana@example.com --double-optin --form-id=123
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function subscribe( $args, $assoc_args ) {
		$email   = isset( $args[0] ) ? trim( (string) $args[0] ) : '';
		$blog_id = get_current_blog_id();
		$config  = Config::init();

		$error = $this->get_subscription_args_error( $config, $blog_id, $email, $assoc_args );
		if ( '' !== $error ) {
			$this->error( $error );
			return;
		}

		$optin_mode = $this->get_optin_mode( $config, $blog_id, $assoc_args );
		$list_id    = (string) $config->get_list_id( $blog_id );
		$segment_id = isset( $assoc_args['segment'] ) ? trim( (string) $assoc_args['segment'] ) : (string) $config->get_segment_id( $blog_id );
		$form_id    = isset( $assoc_args['form-id'] ) ? (string) $assoc_args['form-id'] : '';
		$form_id    = ( 'double' === $optin_mode ) ? SubscribeHelper::get_confirmation_form_id( $form_id, true, $blog_id ) : '';
		$firstname  = isset( $assoc_args['firstname'] ) ? (string) $assoc_args['firstname'] : '';
		$lastname   = isset( $assoc_args['lastname'] ) ? (string) $assoc_args['lastname'] : '';

		$outcome  = 'existing';
		$recorder = function () use ( &$outcome ) {
			$outcome = current_action();
		};
		$actions  = array( 'newsman_subscribed', 'newsman_init_subscribed', 'newsman_resubscribed' );
		foreach ( $actions as $action ) {
			add_action( $action, $recorder );
		}

		$error = '';
		try {
			SubscribeHelper::subscribe_with_props(
				$blog_id,
				$list_id,
				$email,
				array(),
				IpAddress::init()->get_ip(),
				$optin_mode,
				$firstname,
				$lastname,
				$segment_id,
				$form_id,
				true
			);
		} catch ( \Exception $e ) {
			$error = $e->getMessage();
		}

		foreach ( $actions as $action ) {
			remove_action( $action, $recorder );
		}

		if ( '' !== $error ) {
			/* translators: 1: Email, 2: Error message */
			$this->error( sprintf( __( 'NewsMAN could not subscribe %1$s: %2$s', 'newsman' ), $email, $error ) );
			return;
		}

		$this->success( $this->get_subscribe_message( $outcome, $optin_mode, $email, $list_id ) );
	}

	/**
	 * Unsubscribe an email from the NewsMAN list of this site.
	 *
	 * Without --single-optin or --double-optin, the newsletter opt-in type from
	 * Settings is used, like on the My Account page.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Email address.
	 *
	 * [--single-optin]
	 * : Unsubscribe the email right away.
	 *
	 * [--double-optin]
	 * : NewsMAN sends an email that asks to confirm the unsubscribe.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp newsman unsubscribe ana@example.com --single-optin
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function unsubscribe( $args, $assoc_args ) {
		$email   = isset( $args[0] ) ? trim( (string) $args[0] ) : '';
		$blog_id = get_current_blog_id();
		$config  = Config::init();

		$error = $this->get_subscription_args_error( $config, $blog_id, $email, $assoc_args );
		if ( '' !== $error ) {
			$this->error( $error );
			return;
		}

		$optin_mode = $this->get_optin_mode( $config, $blog_id, $assoc_args );
		$list_id    = (string) $config->get_list_id( $blog_id );
		$ip         = IpAddress::init()->get_ip();

		$error = '';
		try {
			if ( 'double' === $optin_mode ) {
				$context = new \Newsman\Service\Context\InitUnsubscribeEmail();
				$context->set_blog_id( $blog_id )
					->set_list_id( $list_id )
					->set_email( $email )
					->set_ip( $ip )
					->set_options( array() );
				( new \Newsman\Service\InitUnsubscribeEmail() )->execute( $context );
			} else {
				$context = new \Newsman\Service\Context\UnsubscribeEmail();
				$context->set_blog_id( $blog_id )
					->set_list_id( $list_id )
					->set_email( $email )
					->set_ip( $ip );
				( new \Newsman\Service\UnsubscribeEmail() )->execute( $context );
			}
		} catch ( \Exception $e ) {
			$error = $e->getMessage();
		}

		if ( '' !== $error ) {
			/* translators: 1: Email, 2: Error message */
			$this->error( sprintf( __( 'NewsMAN could not unsubscribe %1$s: %2$s', 'newsman' ), $email, $error ) );
			return;
		}

		if ( 'double' === $optin_mode ) {
			/* translators: %s: Email */
			$this->success( sprintf( __( 'NewsMAN will send %s an email to confirm the unsubscribe.', 'newsman' ), $email ) );
			return;
		}

		/* translators: 1: Email, 2: NewsMAN list ID */
		$this->success( sprintf( __( '%1$s is unsubscribed from the NewsMAN list %2$s.', 'newsman' ), $email, $list_id ) );
	}

	/**
	 * Clear the cached NewsMAN lists, segments and SMS lists of this site.
	 *
	 * The plugin reads them again from NewsMAN the next time they are needed,
	 * for example after a list or a segment was added in NewsMAN. The copy kept
	 * for when NewsMAN cannot be reached stays.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp newsman flush-cache
	 *
	 * @subcommand flush-cache
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function flush_cache( $args, $assoc_args ) {
		unset( $args, $assoc_args );
		$blog_id = get_current_blog_id();
		Lists_Transient::invalidate( $blog_id, null, true );
		Segments_Transient::invalidate( $blog_id, true );
		SmsLists_Transient::invalidate( $blog_id, null, true );
		$this->success( __( 'The cached NewsMAN lists, segments and SMS lists were cleared.', 'newsman' ) );
	}

	/**
	 * Status checks with their labels, as on the NewsMAN page.
	 *
	 * @param Checks $checks Health checks.
	 * @return array List of [ label, result ].
	 */
	protected function get_status_checks( $checks ) {
		return array(
			array( __( 'API credentials', 'newsman' ), $checks->check_credentials() ),
			array( __( 'Email list', 'newsman' ), $checks->check_list() ),
			array( __( 'Allow API access', 'newsman' ), $checks->check_api_access() ),
			array( __( 'Remarketing', 'newsman' ), $checks->check_remarketing() ),
			array( __( 'Action Scheduler', 'newsman' ), $checks->check_action_scheduler() ),
			array( __( 'WooCommerce', 'newsman' ), $checks->check_woocommerce() ),
		);
	}

	/**
	 * Line naming the site the command works on.
	 *
	 * @return string
	 */
	protected function get_site_line() {
		/* translators: 1: Site URL, 2: WordPress blog ID */
		return sprintf( __( 'Site: %1$s (blog ID %2$d)', 'newsman' ), home_url( '/' ), get_current_blog_id() );
	}

	/**
	 * Output format from the arguments.
	 *
	 * @param array  $assoc_args Named arguments.
	 * @param string $fallback   Format used when none or an unknown one is given.
	 * @return string
	 */
	protected function get_format( $assoc_args, $fallback ) {
		$format = isset( $assoc_args['format'] ) ? strtolower( (string) $assoc_args['format'] ) : $fallback;
		return in_array( $format, array( 'table', 'json', 'csv', 'yaml' ), true ) ? $format : $fallback;
	}

	/**
	 * Pool entry of an export type.
	 *
	 * @param string $type Export type.
	 * @return array
	 */
	protected function get_pool_entry( $type ) {
		foreach ( $this->create_pool()->get_retriever_list() as $entry ) {
			if ( isset( $entry['code'] ) && $entry['code'] === $type ) {
				return $entry;
			}
		}
		return array();
	}

	/**
	 * Error message for --start and --limit, empty when they are fine.
	 *
	 * @param array $assoc_args Named arguments.
	 * @return string
	 */
	protected function get_list_args_error( $assoc_args ) {
		foreach ( array( 'start', 'limit' ) as $name ) {
			if ( isset( $assoc_args[ $name ] ) && ! $this->is_whole_number( $assoc_args[ $name ] ) ) {
				/* translators: %s: Argument name */
				return sprintf( __( 'Use a whole number for --%s.', 'newsman' ), $name );
			}
		}
		if ( isset( $assoc_args['limit'] ) && (int) $assoc_args['limit'] < 1 ) {
			return __( 'Use a --limit of at least 1.', 'newsman' );
		}
		return '';
	}

	/**
	 * Export data from the arguments: list arguments and filters. Filters that take
	 * several values are split at commas.
	 *
	 * @param object $retriever  Export retriever.
	 * @param array  $assoc_args Named arguments, without --format.
	 * @return array
	 */
	protected function get_export_data( $retriever, $assoc_args ) {
		$mapping = method_exists( $retriever, 'get_where_parameters_mapping' ) ? (array) $retriever->get_where_parameters_mapping() : array();
		$data    = array();
		foreach ( $assoc_args as $name => $value ) {
			$value = is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value;
			if ( ! empty( $mapping[ $name ]['multiple'] ) ) {
				$value = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
			}
			$data[ $name ] = $value;
		}
		return $data;
	}

	/**
	 * Error message for unknown filters or sort fields, empty when they are fine. Only
	 * checked when the export defines them; other exports pass them on.
	 *
	 * @param object $retriever Export retriever.
	 * @param string $type      Export type.
	 * @param array  $data      Export data.
	 * @return string
	 */
	protected function get_export_args_error( $retriever, $type, $data ) {
		$filters = array_diff( array_keys( $data ), self::EXPORT_LIST_ARGS );
		$mapping = method_exists( $retriever, 'get_where_parameters_mapping' ) ? (array) $retriever->get_where_parameters_mapping() : array();
		if ( ! empty( $filters ) && ! empty( $mapping ) ) {
			$unknown = array_diff( $filters, array_keys( $mapping ) );
			if ( ! empty( $unknown ) ) {
				return sprintf(
					/* translators: 1: Export type, 2: Unknown filters, 3: Known filters */
					__( 'Unknown filter for the %1$s export: %2$s. Filters: %3$s.', 'newsman' ),
					$type,
					implode( ', ', $unknown ),
					implode( ', ', array_keys( $mapping ) )
				);
			}
		}

		$sort_fields = method_exists( $retriever, 'get_allowed_sort_fields' ) ? (array) $retriever->get_allowed_sort_fields() : array();
		if ( isset( $data['sort'] ) && ! empty( $sort_fields ) && ! isset( $sort_fields[ $data['sort'] ] ) ) {
			return sprintf(
				/* translators: 1: Export type, 2: Unknown sort field, 3: Known sort fields */
				__( 'Unknown sort field for the %1$s export: %2$s. Sort fields: %3$s.', 'newsman' ),
				$type,
				$data['sort'],
				implode( ', ', array_keys( $sort_fields ) )
			);
		}

		return '';
	}

	/**
	 * Print an export result.
	 *
	 * @param mixed  $result Export result.
	 * @param string $format Output format.
	 * @return void
	 */
	protected function print_export( $result, $format ) {
		$result = is_array( $result ) ? $result : array( 'value' => $result );
		if ( 'json' === $format ) {
			$this->output( (string) wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}

		$rows   = ( array() === $result || array_keys( $result ) === range( 0, count( $result ) - 1 ) ) ? $result : array( $result );
		$fields = array();
		$items  = array();
		foreach ( $rows as $row ) {
			$row  = is_array( $row ) ? $row : array( 'value' => $row );
			$item = array();
			foreach ( $row as $name => $value ) {
				$fields[ $name ] = true;
				$item[ $name ]   = ( is_scalar( $value ) || null === $value ) ? $value : wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
			$items[] = $item;
		}
		if ( empty( $items ) ) {
			return;
		}

		$fields = array_keys( $fields );
		foreach ( $items as $index => $item ) {
			foreach ( $fields as $name ) {
				if ( ! array_key_exists( $name, $item ) ) {
					$items[ $index ][ $name ] = '';
				}
			}
		}
		$this->format_items( $format, $items, $fields );
	}

	/**
	 * Error message when the plugin cannot send to NewsMAN, empty when it can.
	 *
	 * @param Config $config  Plugin config.
	 * @param int    $blog_id WP blog ID.
	 * @return string
	 */
	protected function get_connection_error( $config, $blog_id ) {
		if ( ! $config->has_api_access( $blog_id ) ) {
			return __( 'The plugin is not connected to NewsMAN. Connect it with NewsMAN OAuth or save the API key and User ID in Settings.', 'newsman' );
		}
		if ( '' === (string) $config->get_list_id( $blog_id ) ) {
			return __( 'No NewsMAN list is chosen. Choose one on the Sync page of the plugin.', 'newsman' );
		}
		return '';
	}

	/**
	 * Error message for the subscribe and unsubscribe arguments, empty when they are fine.
	 *
	 * @param Config $config     Plugin config.
	 * @param int    $blog_id    WP blog ID.
	 * @param string $email      Email address.
	 * @param array  $assoc_args Named arguments.
	 * @return string
	 */
	protected function get_subscription_args_error( $config, $blog_id, $email, $assoc_args ) {
		if ( ! is_email( $email ) ) {
			/* translators: %s: Email */
			return sprintf( __( '%s is not a valid email address.', 'newsman' ), $email );
		}
		if ( ! empty( $assoc_args['single-optin'] ) && ! empty( $assoc_args['double-optin'] ) ) {
			return __( 'Use either --single-optin or --double-optin, not both.', 'newsman' );
		}
		return $this->get_connection_error( $config, $blog_id );
	}

	/**
	 * Opt-in mode from the arguments, or from Settings.
	 *
	 * @param Config $config     Plugin config.
	 * @param int    $blog_id    WP blog ID.
	 * @param array  $assoc_args Named arguments.
	 * @return string `single` or `double`.
	 */
	protected function get_optin_mode( $config, $blog_id, $assoc_args ) {
		if ( ! empty( $assoc_args['double-optin'] ) ) {
			return 'double';
		}
		if ( ! empty( $assoc_args['single-optin'] ) ) {
			return 'single';
		}
		return $config->is_newsletter_double_optin( $blog_id ) ? 'double' : 'single';
	}

	/**
	 * Message after a subscribe.
	 *
	 * @param string $outcome    Action that ran, or `existing` for a subscriber who was already subscribed.
	 * @param string $optin_mode `single` or `double`.
	 * @param string $email      Email address.
	 * @param string $list_id    NewsMAN list ID.
	 * @return string
	 */
	protected function get_subscribe_message( $outcome, $optin_mode, $email, $list_id ) {
		if ( 'newsman_init_subscribed' === $outcome || ( 'newsman_resubscribed' === $outcome && 'double' === $optin_mode ) ) {
			/* translators: %s: Email */
			return sprintf( __( 'NewsMAN will send a confirmation email to %s. The email is subscribed after it is confirmed.', 'newsman' ), $email );
		}
		if ( 'newsman_subscribed' === $outcome || 'newsman_resubscribed' === $outcome ) {
			/* translators: 1: Email, 2: NewsMAN list ID */
			return sprintf( __( '%1$s is subscribed to the NewsMAN list %2$s.', 'newsman' ), $email, $list_id );
		}
		/* translators: 1: Email, 2: NewsMAN list ID */
		return sprintf( __( '%1$s was already subscribed to the NewsMAN list %2$s.', 'newsman' ), $email, $list_id );
	}

	/**
	 * Label of what the send command sends, for a number of rows.
	 *
	 * @param string $type  Send type.
	 * @param int    $count Number of rows.
	 * @return string
	 */
	protected function get_send_label( $type, $count ) {
		switch ( $type ) {
			case 'wordpress-subscribers':
				return _n( 'WordPress subscriber', 'WordPress subscribers', $count, 'newsman' );
			case 'woocommerce-subscribers':
				return _n( 'WooCommerce subscriber', 'WooCommerce subscribers', $count, 'newsman' );
			case 'orders':
				return _n( 'order', 'orders', $count, 'newsman' );
		}
		return $type;
	}

	/**
	 * Message when there is nothing to send.
	 *
	 * @param string $type Send type.
	 * @return string
	 */
	protected function get_nothing_to_send_message( $type ) {
		switch ( $type ) {
			case 'wordpress-subscribers':
				return __( 'No WordPress subscribers to send.', 'newsman' );
			case 'woocommerce-subscribers':
				return __( 'No WooCommerce subscribers to send.', 'newsman' );
		}
		return __( 'No orders to send.', 'newsman' );
	}

	/**
	 * Whether a value is a whole number of digits.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	protected function is_whole_number( $value ) {
		return is_scalar( $value ) && 1 === preg_match( '/^\d+$/', (string) $value );
	}

	/**
	 * Whether a date is a real date in the YYYY-MM-DD format.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	protected function is_valid_date( $date ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts ) ) {
			return false;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	protected function has_woocommerce() {
		return ( new WooCommerceExist() )->exist();
	}

	/**
	 * Health checks.
	 *
	 * @return Checks
	 */
	protected function create_checks() {
		return new Checks();
	}

	/**
	 * Export retriever pool.
	 *
	 * @return Pool
	 */
	protected function create_pool() {
		return new Pool();
	}

	/**
	 * Retriever that sends a type to NewsMAN.
	 *
	 * @param string $type Send type.
	 * @return \Newsman\Export\Retriever\CronSubscribers|\Newsman\Export\Retriever\SendOrders
	 */
	protected function create_send_retriever( $type ) {
		$class = self::SEND_TYPES[ $type ];
		return new $class();
	}

	/**
	 * Print data, also with --quiet.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	protected function output( $text ) {
		\WP_CLI::line( $text );
	}

	/**
	 * Print an information message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	protected function log( $message ) {
		\WP_CLI::log( $message );
	}

	/**
	 * Print a success message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	protected function success( $message ) {
		\WP_CLI::success( $message );
	}

	/**
	 * Print an error message and end the command with exit code 1.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	protected function error( $message ) {
		\WP_CLI::error( $message );
	}

	/**
	 * End the command with an exit code.
	 *
	 * @param int $code Exit code.
	 * @return void
	 */
	protected function halt( $code ) {
		\WP_CLI::halt( $code );
	}

	/**
	 * Ask for confirmation, unless --yes is given. Ends the command on no.
	 *
	 * @param string $question   Question.
	 * @param array  $assoc_args Named arguments.
	 * @return void
	 */
	protected function confirm( $question, $assoc_args ) {
		\WP_CLI::confirm( $question, $assoc_args );
	}

	/**
	 * Print rows in a format.
	 *
	 * @param string $format Output format.
	 * @param array  $items  Rows.
	 * @param array  $fields Columns.
	 * @return void
	 */
	protected function format_items( $format, $items, $fields ) {
		\WP_CLI\Utils\format_items( $format, $items, $fields );
	}

	/**
	 * Progress bar.
	 *
	 * @param string $message Message.
	 * @param int    $count   Number of rows.
	 * @return object Object with tick() and finish().
	 */
	protected function make_progress_bar( $message, $count ) {
		return \WP_CLI\Utils\make_progress_bar( $message, $count );
	}
}
