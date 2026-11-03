<?php
/**
 * Tests for the wp newsman commands.
 *
 * WP-CLI is not loaded in the test suite, so the command runs with its output methods
 * replaced: messages are recorded and an error throws an exception.
 *
 * @package NewsmanApp for WordPress
 */

use Newsman\Cli\Command;
use Newsman\Subscribe\Lists_Transient;
use Newsman\Subscribe\Segments_Transient;
use Newsman\Subscribe\SmsLists_Transient;

/**
 * WP-CLI command tests.
 */
class CliCommandTest extends NewsmanTestCase {
	/**
	 * Requests seen by the fake API, in call order: [ endpoint, decoded body ].
	 *
	 * @var array
	 */
	private $calls = array();

	/**
	 * Endpoints the fake API answers with an error.
	 *
	 * @var string[]
	 */
	private $failing = array();

	/**
	 * Number of the import.csv call that fails (1 for the first), 0 for none.
	 *
	 * @var int
	 */
	private $failing_import = 0;

	/**
	 * Status returned by subscriber.getByEmail, or null for "not found".
	 *
	 * @var string|null
	 */
	private $status = null;

	/**
	 * Connect the plugin and fake the NewsMAN API.
	 */
	public function set_up() {
		parent::set_up();
		$this->enable_api();
		update_option( 'newsman_segments', '' );
		update_option( 'newsman_newslettertype', 'save' );
		update_option( 'newsman_form_id', '' );
		$this->calls          = array();
		$this->failing        = array();
		$this->failing_import = 0;
		$this->status         = null;
		add_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10, 3 );
		add_filter( 'newsman_ip_address_get_ip', array( $this, 'fake_ip' ) );
	}

	/**
	 * Fixed IP, so the server IP is not looked up on the internet.
	 *
	 * @return string
	 */
	public function fake_ip() {
		return '203.0.113.9';
	}

	/**
	 * Remove the fake API.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_newsman_api' ), 10 );
		remove_filter( 'newsman_ip_address_get_ip', array( $this, 'fake_ip' ) );
		parent::tear_down();
	}

	/**
	 * Fake NewsMAN API.
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array
	 */
	public function fake_newsman_api( $pre, $args, $url ) {
		unset( $pre );
		$endpoint = preg_match( '#/([a-zA-Z]+\.[a-zA-Z]+)\.json#', $url, $m ) ? $m[1] : $url;
		$body     = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		if ( null === $body ) {
			$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
			wp_parse_str( $query, $body );
		}
		$this->calls[] = array( $endpoint, $body );

		$ok    = array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => 'true',
		);
		$error = array_merge(
			$ok,
			array(
				'response' => array(
					'code'    => 500,
					'message' => 'Error',
				),
				'body'     => '{"err":"Error","message":"Failure","code":500}',
			)
		);

		$imports = count( $this->endpoint_calls( 'import.csv' ) );
		if ( in_array( $endpoint, $this->failing, true ) || ( 'import.csv' === $endpoint && $imports === $this->failing_import ) ) {
			return $error;
		}

		switch ( $endpoint ) {
			case 'list.all':
				$ok['body'] = wp_json_encode(
					array(
						array(
							'list_id'   => '1',
							'list_name' => 'Store list',
						),
					)
				);
				break;
			case 'subscriber.getByEmail':
				// A new email is found after saveSubscribe created it.
				$status = $this->status;
				if ( null === $status && array() !== $this->endpoint_calls( 'subscriber.saveSubscribe' ) ) {
					$status = 'subscribed';
				}
				if ( null === $status ) {
					return $error;
				}
				$ok['body'] = wp_json_encode(
					array(
						'subscriber_id' => 77,
						'email'         => 'ana@example.com',
						'status'        => $status,
					)
				);
				break;
			case 'subscriber.saveSubscribe':
			case 'subscriber.initSubscribe':
				$ok['body'] = '77';
				break;
		}
		return $ok;
	}

	/**
	 * Bodies of the calls to one endpoint.
	 *
	 * @param string $endpoint Endpoint.
	 * @return array
	 */
	private function endpoint_calls( $endpoint ) {
		$bodies = array();
		foreach ( $this->calls as $call ) {
			if ( $call[0] === $endpoint ) {
				$bodies[] = $call[1];
			}
		}
		return $bodies;
	}

	/**
	 * Called endpoints, in call order.
	 *
	 * @return string[]
	 */
	private function endpoints() {
		return array_map(
			function ( $call ) {
				return $call[0];
			},
			$this->calls
		);
	}

	/**
	 * Command with its output recorded.
	 *
	 * @param null|bool $woocommerce Whether WooCommerce is active; null for the real check.
	 * @return Command
	 */
	private function make_command( $woocommerce = null ) {
		$command              = new class() extends Command {
			/**
			 * Output, messages and calls, by kind.
			 *
			 * @var array
			 */
			public $out = array(
				'output'    => array(),
				'log'       => array(),
				'success'   => array(),
				'items'     => array(),
				'confirm'   => array(),
				'exit_code' => null,
			);

			/**
			 * Whether WooCommerce is active; null for the real check.
			 *
			 * @var null|bool
			 */
			public $woocommerce = null;

			/**
			 * Print an export result.
			 *
			 * @param mixed  $result Export result.
			 * @param string $format Output format.
			 * @return void
			 */
			public function call_print_export( $result, $format ) {
				$this->print_export( $result, $format );
			}

			/**
			 * Record data output.
			 *
			 * @param string $text Text.
			 * @return void
			 */
			protected function output( $text ) {
				$this->out['output'][] = $text;
			}

			/**
			 * Record a message.
			 *
			 * @param string $message Message.
			 * @return void
			 */
			protected function log( $message ) {
				$this->out['log'][] = $message;
			}

			/**
			 * Record a success message.
			 *
			 * @param string $message Message.
			 * @return void
			 */
			protected function success( $message ) {
				$this->out['success'][] = $message;
			}

			/**
			 * End the command like WP-CLI does.
			 *
			 * @param string $message Message.
			 * @return void
			 * @throws \RuntimeException Always.
			 */
			protected function error( $message ) {
				throw new \RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			/**
			 * Record the exit code.
			 *
			 * @param int $code Exit code.
			 * @return void
			 */
			protected function halt( $code ) {
				$this->out['exit_code'] = $code;
			}

			/**
			 * Record the question and answer yes.
			 *
			 * @param string $question   Question.
			 * @param array  $assoc_args Named arguments.
			 * @return void
			 */
			protected function confirm( $question, $assoc_args ) {
				unset( $assoc_args );
				$this->out['confirm'][] = $question;
			}

			/**
			 * Record the rows.
			 *
			 * @param string $format Output format.
			 * @param array  $items  Rows.
			 * @param array  $fields Columns.
			 * @return void
			 */
			protected function format_items( $format, $items, $fields ) {
				$this->out['items'][] = array( $format, $items, $fields );
			}

			/**
			 * Progress bar that prints nothing.
			 *
			 * @param string $message Message.
			 * @param int    $count   Number of rows.
			 * @return object
			 */
			protected function make_progress_bar( $message, $count ) {
				unset( $message, $count );
				return new class() {
					/**
					 * Advance.
					 *
					 * @param int $increment Rows.
					 * @return void
					 */
					public function tick( $increment = 1 ) {
						unset( $increment );
					}

					/**
					 * Finish.
					 *
					 * @return void
					 */
					public function finish() {
					}
				};
			}

			/**
			 * Whether WooCommerce is active.
			 *
			 * @return bool
			 */
			protected function has_woocommerce() {
				return null === $this->woocommerce ? parent::has_woocommerce() : $this->woocommerce;
			}
		};
		$command->woocommerce = $woocommerce;
		return $command;
	}

	/**
	 * Run a command that must fail and return its error message.
	 *
	 * @param callable $run Runs the command.
	 * @return string
	 */
	private function error_of( $run ) {
		try {
			$run();
		} catch ( \RuntimeException $e ) {
			return $e->getMessage();
		}
		$this->fail( 'The command did not end with an error.' );
		return '';
	}

	/**
	 * Create users with the Subscriber role.
	 *
	 * @param int    $count      Number of users.
	 * @param string $registered Registration date.
	 * @return void
	 */
	private function create_subscribers( $count, $registered = '' ) {
		for ( $i = 1; $i <= $count; $i++ ) {
			$extra = '' === $registered ? array() : array( 'user_registered' => $registered );
			$this->create_user( 'subscriber', 'sub' . $i . '-' . wp_generate_password( 6, false ) . '@example.com', 'Sub', 'Scriber', $extra );
		}
	}

	/**
	 * The plugin registers the commands on cli_init, and only when WP-CLI is loaded.
	 */
	public function test_commands_are_registered_only_under_wp_cli() {
		$plugin = \WP_Newsman::init();
		$this->assertNotFalse( has_action( 'cli_init', array( $plugin, 'register_cli_commands' ) ) );
		$this->assertFalse( class_exists( '\WP_CLI', false ) );
		$plugin->register_cli_commands();
		$this->assertTrue( class_exists( Command::class ) );
	}

	/**
	 * Status lists the checks of the NewsMAN page and ends with exit code 1 on a problem.
	 */
	public function test_status_lists_the_checks() {
		$command = $this->make_command();
		$command->status( array(), array() );

		$rows   = $command->out['items'][0][1];
		$checks = wp_list_pluck( $rows, 'status', 'check' );
		$this->assertSame( 'table', $command->out['items'][0][0] );
		$this->assertSame( 'good', $checks['API credentials'] );
		$this->assertSame( 'good', $checks['Email list'] );
		$this->assertArrayHasKey( 'Last exports: Subscribers', $checks );
		$this->assertStringStartsWith( 'Site: ', $command->out['log'][0] );
		$this->assertNull( $command->out['exit_code'] );

		delete_option( 'newsman_apikey' );
		$command = $this->make_command();
		$command->status( array(), array( 'format' => 'json' ) );
		$checks = wp_list_pluck( $command->out['items'][0][1], 'status', 'check' );
		$this->assertSame( 'critical', $checks['API credentials'] );
		$this->assertSame( 1, $command->out['exit_code'] );
		$this->assertSame( array(), $command->out['log'], 'No site line in JSON output.' );
	}

	/**
	 * Export prints what the export returns, as JSON by default.
	 */
	public function test_export_prints_json() {
		$command = $this->make_command();
		$command->export( array( 'platform-name' ), array() );
		$this->assertSame( array( '{"name":"WordPress"}' ), $command->out['output'] );
	}

	/**
	 * Table, CSV and YAML get one row per entry, with nested values as JSON and missing columns empty.
	 */
	public function test_export_formats_flatten_rows() {
		$command = $this->make_command();
		$command->call_print_export(
			array(
				array(
					'id'   => 1,
					'tags' => array( 'a', 'b/c' ),
				),
				array(
					'id'    => 2,
					'extra' => null,
				),
			),
			'table'
		);
		$this->assertSame(
			array(
				'table',
				array(
					array(
						'id'    => 1,
						'tags'  => '["a","b/c"]',
						'extra' => '',
					),
					array(
						'id'    => 2,
						'extra' => null,
						'tags'  => '',
					),
				),
				array( 'id', 'tags', 'extra' ),
			),
			$command->out['items'][0]
		);

		$command->call_print_export( array( 'name' => 'WordPress' ), 'csv' );
		$this->assertSame( array( array( 'name' => 'WordPress' ) ), $command->out['items'][1][1] );

		$command->call_print_export( array(), 'table' );
		$this->assertCount( 2, $command->out['items'], 'Nothing is printed for no rows.' );
	}

	/**
	 * Exports that create coupons, change subscriptions, send data or run SQL are refused.
	 */
	public function test_export_refuses_types_that_change_data() {
		$types = array( 'coupons', 'subscriber-subscribe', 'subscriber-unsubscribe', 'send-orders', 'custom-sql', 'refresh-remarketing', 'wordpress', 'woocommerce', 'products', 'bogus' );
		foreach ( $types as $type ) {
			$command = $this->make_command();
			$message = $this->error_of(
				function () use ( $command, $type ) {
					$command->export( array( $type ), array() );
				}
			);
			$this->assertStringContainsString( 'is not available from the command line', $message, $type );
		}
		$this->assertSame( array(), $this->calls );
	}

	/**
	 * Filters and sort fields are checked against the export; a valid filter applies.
	 */
	public function test_export_checks_filters_and_sort() {
		$this->create_user( 'subscriber', 'ana@example.com', 'Ana', 'Pop' );
		$this->create_user( 'subscriber', 'ion@example.com', 'Ion', 'Pop' );
		$command = $this->make_command();

		$message = $this->error_of(
			function () use ( $command ) {
				$command->export( array( 'subscribers-wordpress-feed' ), array( 'emial' => 'ana@example.com' ) );
			}
		);
		$this->assertStringContainsString( 'Unknown filter for the subscribers-wordpress-feed export: emial.', $message );
		$this->assertStringContainsString( 'email', $message );

		$message = $this->error_of(
			function () use ( $command ) {
				$command->export( array( 'subscribers-wordpress-feed' ), array( 'sort' => 'bogus' ) );
			}
		);
		$this->assertStringContainsString( 'Unknown sort field', $message );

		foreach ( array( 'abc', '-1', '1.5' ) as $limit ) {
			$message = $this->error_of(
				function () use ( $command, $limit ) {
					$command->export( array( 'subscribers-wordpress-feed' ), array( 'limit' => $limit ) );
				}
			);
			$this->assertSame( 'Use a whole number for --limit.', $message );
		}

		$command->export( array( 'subscribers-wordpress-feed' ), array( 'email' => 'ana@example.com' ) );
		$rows = json_decode( $command->out['output'][0], true );
		$this->assertSame( array( 'ana@example.com' ), wp_list_pluck( $rows, 'email' ) );

		$command->export( array( 'subscribers-wordpress-feed' ), array( 'limit' => '1' ) );
		$this->assertCount( 1, json_decode( $command->out['output'][1], true ) );
	}

	/**
	 * Export does not change when NewsMAN last read the data and needs no API key.
	 */
	public function test_export_does_not_record_last_run() {
		delete_option( 'newsman_export_last_runs' );
		delete_option( 'newsman_apikey' );
		$this->create_user( 'subscriber', 'ana@example.com', 'Ana', 'Pop' );
		$this->make_command()->export( array( 'subscribers-wordpress-feed' ), array() );
		$this->assertFalse( get_option( 'newsman_export_last_runs' ) );
	}

	/**
	 * WooCommerce exports and sends need WooCommerce.
	 */
	public function test_woocommerce_types_need_woocommerce() {
		foreach ( array( 'orders', 'customers', 'products-feed', 'subscribers-woocommerce-feed' ) as $type ) {
			$command = $this->make_command( false );
			$message = $this->error_of(
				function () use ( $command, $type ) {
					$command->export( array( $type ), array() );
				}
			);
			$this->assertSame( 'The ' . $type . ' export needs WooCommerce.', $message );
		}
		foreach ( array( 'orders', 'woocommerce-subscribers' ) as $type ) {
			$command = $this->make_command( false );
			$message = $this->error_of(
				function () use ( $command, $type ) {
					$command->send( array( $type ), array( 'yes' => true ) );
				}
			);
			$this->assertSame( 'Sending ' . $type . ' needs WooCommerce.', $message );
		}
	}

	/**
	 * Flush-cache clears the cached lists, segments and SMS lists and keeps the copy for outages.
	 */
	public function test_flush_cache_clears_the_cache() {
		$blog_id = get_current_blog_id();
		$lists   = array( array( 'list_id' => '1' ) );
		Lists_Transient::save( $blog_id, 1, $lists );
		Segments_Transient::save_all( $blog_id, array( '1' => array( array( 'segment_id' => '9' ) ) ) );
		SmsLists_Transient::save( $blog_id, 1, $lists );

		$command = $this->make_command();
		$command->flush_cache( array(), array() );

		$this->assertNull( Lists_Transient::get( $blog_id, 1 ) );
		$this->assertSame( array(), Segments_Transient::get_all( $blog_id ) );
		$this->assertNull( SmsLists_Transient::get( $blog_id, 1 ) );
		$this->assertSame( $lists, Lists_Transient::get_stale( $blog_id, 1 ) );
		$this->assertSame( $lists, SmsLists_Transient::get_stale( $blog_id, 1 ) );
		$this->assertSame( array( 'The cached NewsMAN lists, segments and SMS lists were cleared.' ), $command->out['success'] );
	}

	/**
	 * Single opt-in subscribes a new email at once and adds the segment.
	 */
	public function test_subscribe_single_optin() {
		update_option( 'newsman_segments', '9' );
		$command = $this->make_command();
		$command->subscribe(
			array( 'ana@example.com' ),
			array(
				'firstname'    => 'Ana',
				'single-optin' => true,
			)
		);

		$this->assertSame( array( 'subscriber.getByEmail', 'subscriber.saveSubscribe', 'subscriber.getByEmail', 'segment.addSubscriber' ), $this->endpoints() );
		$save = $this->endpoint_calls( 'subscriber.saveSubscribe' )[0];
		$this->assertSame( 'ana@example.com', $save['email'] );
		$this->assertSame( 'Ana', $save['firstname'] );
		$this->assertSame( '1', (string) $save['list_id'] );
		$this->assertSame( array( 'ana@example.com is subscribed to the NewsMAN list 1.' ), $command->out['success'] );
	}

	/**
	 * Double opt-in sends initSubscribe with the confirmation Form ID and the segment.
	 */
	public function test_subscribe_double_optin() {
		update_option( 'newsman_segments', '9' );
		update_option( 'newsman_form_id', 'confirm-7' );
		$command = $this->make_command();
		$command->subscribe( array( 'ana@example.com' ), array( 'double-optin' => true ) );

		$this->assertSame( array( 'subscriber.getByEmail', 'subscriber.initSubscribe' ), $this->endpoints() );
		$init = $this->endpoint_calls( 'subscriber.initSubscribe' )[0];
		$this->assertSame( 'confirm-7', $init['options']['form_id'] );
		$this->assertSame( array( '9' ), array_map( 'strval', $init['options']['segments'] ) );
		$this->assertStringStartsWith( 'NewsMAN will send a confirmation email to ana@example.com.', $command->out['success'][0] );

		$this->calls = array();
		$command->subscribe(
			array( 'ana@example.com' ),
			array(
				'double-optin' => true,
				'form-id'      => 'own-3',
				'segment'      => '',
			)
		);
		$init = $this->endpoint_calls( 'subscriber.initSubscribe' )[0];
		$this->assertSame( 'own-3', $init['options']['form_id'] );
		$this->assertArrayNotHasKey( 'segments', $init['options'] );
	}

	/**
	 * Without a flag, the newsletter opt-in type from Settings applies.
	 */
	public function test_subscribe_follows_the_setting() {
		update_option( 'newsman_newslettertype', 'init' );
		$this->make_command()->subscribe( array( 'ana@example.com' ), array() );
		$this->assertContains( 'subscriber.initSubscribe', $this->endpoints() );

		$this->calls = array();
		$this->make_command()->subscribe( array( 'ana@example.com' ), array( 'single-optin' => true ) );
		$this->assertContains( 'subscriber.saveSubscribe', $this->endpoints() );
		$this->assertNotContains( 'subscriber.initSubscribe', $this->endpoints() );
	}

	/**
	 * Already subscribed: no new subscription; unsubscribed: subscribed again.
	 */
	public function test_subscribe_existing_emails() {
		$this->status = 'subscribed';
		$command      = $this->make_command();
		$command->subscribe( array( 'ana@example.com' ), array( 'single-optin' => true ) );
		$this->assertNotContains( 'subscriber.saveSubscribe', $this->endpoints() );
		$this->assertStringContainsString( 'was already subscribed', $command->out['success'][0] );

		$this->status = 'unsubscribed';
		$this->calls  = array();
		$command->subscribe( array( 'ana@example.com' ), array( 'single-optin' => true ) );
		$this->assertContains( 'subscriber.saveSubscribe', $this->endpoints() );
		$this->assertSame( 'ana@example.com is subscribed to the NewsMAN list 1.', $command->out['success'][1] );
	}

	/**
	 * Wrong arguments, a missing connection and API errors end with an error.
	 */
	public function test_subscribe_errors() {
		$command = $this->make_command();
		$cases   = array(
			'not an email is not a valid email address.' => array( array( 'not an email' ), array() ),
			'Use either --single-optin or --double-optin, not both.' => array(
				array( 'ana@example.com' ),
				array(
					'single-optin' => true,
					'double-optin' => true,
				),
			),
		);
		foreach ( $cases as $expected => $run ) {
			$message = $this->error_of(
				function () use ( $command, $run ) {
					$command->subscribe( $run[0], $run[1] );
				}
			);
			$this->assertSame( $expected, $message );
		}

		$this->failing = array( 'subscriber.saveSubscribe' );
		$message       = $this->error_of(
			function () use ( $command ) {
				$command->subscribe( array( 'ana@example.com' ), array( 'single-optin' => true ) );
			}
		);
		$this->assertStringStartsWith( 'NewsMAN could not subscribe ana@example.com: ', $message );

		update_option( 'newsman_list', '' );
		$message = $this->error_of(
			function () use ( $command ) {
				$command->subscribe( array( 'ana@example.com' ), array() );
			}
		);
		$this->assertStringStartsWith( 'No NewsMAN list is chosen.', $message );

		delete_option( 'newsman_userid' );
		$message = $this->error_of(
			function () use ( $command ) {
				$command->unsubscribe( array( 'ana@example.com' ), array() );
			}
		);
		$this->assertStringStartsWith( 'The plugin is not connected to NewsMAN.', $message );
	}

	/**
	 * Unsubscribe: saveUnsubscribe for single opt-in, initUnsubscribe for double opt-in.
	 */
	public function test_unsubscribe() {
		$command = $this->make_command();
		$command->unsubscribe( array( 'ana@example.com' ), array( 'single-optin' => true ) );
		$this->assertSame( array( 'subscriber.saveUnsubscribe' ), $this->endpoints() );
		$this->assertSame( 'ana@example.com', $this->endpoint_calls( 'subscriber.saveUnsubscribe' )[0]['email'] );
		$this->assertSame( 'ana@example.com is unsubscribed from the NewsMAN list 1.', $command->out['success'][0] );

		$this->calls = array();
		update_option( 'newsman_newslettertype', 'init' );
		$command->unsubscribe( array( 'ana@example.com' ), array() );
		$this->assertSame( array( 'subscriber.initUnsubscribe' ), $this->endpoints() );
		$this->assertSame( 'NewsMAN will send ana@example.com an email to confirm the unsubscribe.', $command->out['success'][1] );

		$this->failing = array( 'subscriber.initUnsubscribe' );
		$message       = $this->error_of(
			function () use ( $command ) {
				$command->unsubscribe( array( 'ana@example.com' ), array( 'double-optin' => true ) );
			}
		);
		$this->assertStringStartsWith( 'NewsMAN could not unsubscribe ana@example.com: ', $message );
	}

	/**
	 * A dry run counts and sends nothing; --since counts only the newer users.
	 */
	public function test_send_dry_run() {
		$command = $this->make_command();
		$command->send( array( 'wordpress-subscribers' ), array( 'dry-run' => true ) );
		$this->assertSame( array( 'No WordPress subscribers to send.' ), $command->out['success'] );

		$this->create_subscribers( 1 );
		$command = $this->make_command();
		$command->send( array( 'wordpress-subscribers' ), array( 'dry-run' => true ) );
		$this->assertSame( array( 'Dry run: 1 WordPress subscriber would be sent to the NewsMAN list 1.' ), $command->out['success'] );

		$this->create_subscribers( 2 );
		$this->create_subscribers( 2, '2020-05-01 10:00:00' );

		$command = $this->make_command();
		$command->send( array( 'wordpress-subscribers' ), array( 'dry-run' => true ) );
		$this->assertSame( array( 'Dry run: 5 WordPress subscribers would be sent to the NewsMAN list 1.' ), $command->out['success'] );

		$command->send(
			array( 'wordpress-subscribers' ),
			array(
				'dry-run' => true,
				'since'   => '2025-01-01',
			)
		);
		$this->assertSame( 'Dry run: 3 WordPress subscribers would be sent to the NewsMAN list 1.', $command->out['success'][1] );
		$this->assertSame( array(), $command->out['confirm'] );
		$this->assertSame( array(), $this->calls );
	}

	/**
	 * WordPress subscribers are sent in batches, after the confirmation.
	 */
	public function test_send_wordpress_subscribers() {
		$this->create_subscribers( 3 );
		$command = $this->make_command();
		$command->send( array( 'wordpress-subscribers' ), array( 'batch-size' => '2' ) );

		$this->assertSame( array( 'Send 3 WordPress subscribers to the NewsMAN list 1?' ), $command->out['confirm'] );
		$imports = $this->endpoint_calls( 'import.csv' );
		$this->assertCount( 2, $imports );
		$this->assertSame( 2, substr_count( $imports[0]['csv_data'], '@example.com' ) );
		$this->assertSame( 1, substr_count( $imports[1]['csv_data'], '@example.com' ) );
		$this->assertSame( array( 'Sent 3 WordPress subscribers to NewsMAN.' ), $command->out['success'] );
	}

	/**
	 * A failed batch is reported with the counts and ends with an error; the other batches are sent.
	 */
	public function test_send_reports_failed_batches() {
		$this->create_subscribers( 3 );
		$this->failing_import = 1;
		$command              = $this->make_command();
		$message              = $this->error_of(
			function () use ( $command ) {
				$command->send( array( 'wordpress-subscribers' ), array( 'batch-size' => '2' ) );
			}
		);
		$this->assertSame( 'Sent 1 of 3 WordPress subscribers to NewsMAN. Some batches failed, the NewsMAN log has the errors.', $message );
		$this->assertCount( 2, $this->endpoint_calls( 'import.csv' ) );
	}

	/**
	 * Wrong arguments and a missing connection end with an error before anything is sent.
	 */
	public function test_send_errors() {
		$this->create_subscribers( 1 );
		$command = $this->make_command();
		$cases   = array(
			array( array( 'since' => '2026-02-30' ), 'Use the YYYY-MM-DD format for --since, for example 2026-01-31.' ),
			array( array( 'since' => '01-01-2026' ), 'Use the YYYY-MM-DD format for --since, for example 2026-01-31.' ),
			array( array( 'batch-size' => '0' ), 'Use a --batch-size from 1 to 9000.' ),
			array( array( 'batch-size' => '9001' ), 'Use a --batch-size from 1 to 9000.' ),
			array( array( 'batch-size' => 'x' ), 'Use a --batch-size from 1 to 9000.' ),
		);
		foreach ( $cases as $case ) {
			$message = $this->error_of(
				function () use ( $command, $case ) {
					$command->send( array( 'wordpress-subscribers' ), $case[0] );
				}
			);
			$this->assertSame( $case[1], $message );
		}

		$message = $this->error_of(
			function () use ( $command ) {
				$command->send( array( 'bogus' ), array() );
			}
		);
		$this->assertSame( 'Unknown type bogus. Types: wordpress-subscribers, woocommerce-subscribers, orders.', $message );

		delete_option( 'newsman_apikey' );
		$message = $this->error_of(
			function () use ( $command ) {
				$command->send( array( 'wordpress-subscribers' ), array( 'yes' => true ) );
			}
		);
		$this->assertStringStartsWith( 'The plugin is not connected to NewsMAN.', $message );
		$this->assertSame( array(), $this->calls );
		$this->assertSame( array(), $command->out['confirm'] );
	}

	/**
	 * Orders are sent to remarketing.saveOrders in batches (WooCommerce job).
	 */
	public function test_send_orders() {
		$this->require_woocommerce();
		$product = new \WC_Product_Simple();
		$product->set_name( 'Mug' );
		$product->set_regular_price( '10' );
		$product->save();
		for ( $i = 1; $i <= 3; $i++ ) {
			$order = wc_create_order();
			$order->add_product( $product, 1 );
			$order->set_billing_email( 'buyer' . $i . '@example.com' );
			$order->calculate_totals();
			$order->set_status( 'completed' );
			$order->save();
		}

		$command = $this->make_command();
		$command->send( array( 'orders' ), array( 'batch-size' => '2' ) );

		$batches = $this->endpoint_calls( 'remarketing.saveOrders' );
		$this->assertCount( 2, $batches );
		$this->assertSame( array( 'Sent 3 orders to NewsMAN.' ), $command->out['success'] );

		$message = $this->error_of(
			function () use ( $command ) {
				$command->send( array( 'orders' ), array( 'batch-size' => '501' ) );
			}
		);
		$this->assertSame( 'Use a --batch-size from 1 to 500.', $message );
	}

	/**
	 * WooCommerce subscribers come from completed orders, each email once (WooCommerce job).
	 */
	public function test_send_woocommerce_subscribers() {
		$this->require_woocommerce();
		foreach ( array( 'ana@example.com', 'ana@example.com', 'ion@example.com' ) as $email ) {
			$order = wc_create_order();
			$order->set_billing_email( $email );
			$order->set_billing_first_name( 'Ana' );
			$order->set_status( 'completed' );
			$order->save();
		}
		$pending = wc_create_order();
		$pending->set_billing_email( 'pending@example.com' );
		$pending->save();

		$command = $this->make_command();
		$command->send( array( 'woocommerce-subscribers' ), array( 'yes' => true ) );

		$imports = $this->endpoint_calls( 'import.csv' );
		$this->assertCount( 1, $imports );
		$this->assertSame( 1, substr_count( $imports[0]['csv_data'], 'ana@example.com' ) );
		$this->assertStringContainsString( 'ion@example.com', $imports[0]['csv_data'] );
		$this->assertStringNotContainsString( 'pending@example.com', $imports[0]['csv_data'] );
		$this->assertSame( array( 'Sent 2 WooCommerce subscribers to NewsMAN.' ), $command->out['success'] );
	}
}
