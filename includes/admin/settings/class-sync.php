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

namespace Newsman\Admin\Settings;

use Newsman\Admin\Settings;
use Newsman\Config;
use Newsman\Subscribe\Lists;
use Newsman\Subscribe\Lists_Transient;
use Newsman\Subscribe\Segments;
use Newsman\Subscribe\Segments_Transient;
use Newsman\Subscribe\SmsLists_Transient;
use Newsman\Util\WooCommerceExist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin configuration synchronize
 *
 * @class \Newsman\Admin\Settings\Sync
 */
class Sync extends Settings {
	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings-sync';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = 'newsman_sync';

	/**
	 * Form fields
	 *
	 * @var array
	 */
	public $form_fields = array(
		'newsman_list',
		'newsman_segments',
		'newsman_smslist',
	);

	/**
	 * Available lists
	 *
	 * @var array
	 */
	public $available_lists = array();

	/**
	 * Available segments by current list ID
	 *
	 * @var array
	 */
	public $available_segments = array();

	/**
	 * Available SMS lists
	 *
	 * @var array
	 */
	public $available_sms_lists = array();

	/**
	 * Full map of segments grouped by list ID: `[ list_id => [ segment_id => segment_name ] ]`.
	 *
	 * Emitted to the page so the segment dropdown can be repopulated client-side when the
	 * selected list changes, without a page reload.
	 *
	 * @var array<string,array<string,string>>
	 */
	public $segments_by_list = array();

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend-sync.php';
	}

	/**
	 * Process form
	 *
	 * @return void
	 */
	public function process_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user', 'newsman' ) );
		}

		// Refresh-cache submit button branch. Runs the API-backed refresh of the
		// Lists / Segments transients across every blog in a network install, then
		// falls through to the normal read path so the page re-renders with the
		// just-refreshed data.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['newsman_sync_refresh'] ) ) {
			$this->refresh_lists_and_segments_cache();
		}

		$form_id_value           = '';
		$this->valid_credentials = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $this->form_id ] ) && ! empty( $_POST[ $this->form_id ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$form_id_value = sanitize_text_field( wp_unslash( $_POST[ $this->form_id ] ) );
		}

		// On Refresh, treat the request as a read (no save). The refresh handler
		// has already populated the transients + queued an admin notice; the
		// normal else branch below will then render the page with available_lists /
		// available_segments / available_sms_lists.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['newsman_sync_refresh'] ) ) {
			$form_id_value = '';
		}

		if ( 'Y' === $form_id_value ) {
			$this->init_form_values_from_post();
			$previous_list_id = get_option( 'newsman_list' );
			$this->save_form_values();
			$this->is_oauth();

			if ( ! empty( $this->get_form_value( 'newsman_list' ) ) ) {
				$authenticate_token = $this->ensure_authenticate_token();
				$integration_result = $this->save_list_integration_setup(
					$this->get_form_value( 'newsman_list' ),
					get_site_url() . '/?newsman_api=v1',
					$authenticate_token
				);
				if ( false === $integration_result ) {
					update_option( 'newsman_list', $previous_list_id, Config::AUTOLOAD_OPTIONS );
					$this->set_form_value( 'newsman_list', $previous_list_id );
					$this->set_message_backend( 'error', esc_html__( 'Could not save integration setup. The list was not changed.', 'newsman' ) );
				} else {
					$settings = $this->get_remarketing_settings( $this->get_form_value( 'newsman_list' ) );
					if ( ! empty( $settings ) && is_array( $settings ) && ! empty( $settings['javascript'] ) ) {
						$newsman_options = new \Newsman\Options();
						$newsman_options->update_option( 'newsman_scriptjs', $settings['javascript'] );
					}
				}
			}

			$this->install_products_feed();

			try {
				$this->available_lists    = $this->retrieve_api_all_lists();
				$this->available_segments = array();
				if ( false !== $this->available_lists ) {
					if ( ! empty( $this->get_form_value( 'newsman_list' ) ) ) {
						$this->available_segments = $this->retrieve_api_all_segments(
							$this->get_form_value( 'newsman_list' )
						);
					}

					// If the current list doesn't have the configured segment (ID) than save empty in configured segment ID.
					if ( ! empty( $this->get_form_value( 'newsman_segments' ) ) ) {
						$found_segment = false;
						foreach ( $this->available_segments as $item ) {
							if ( $this->get_form_value( 'newsman_segments' ) === (string) $item['segment_id'] ) {
								$found_segment = true;
								break;
							}
						}
						if ( ! $found_segment ) {
							update_option( 'newsman_segments', '', Config::AUTOLOAD_OPTIONS );
							$this->set_form_value( 'newsman_segments', '' );
						}
					}
				} else {
					$this->valid_credentials = false;
					$this->set_message_backend( 'error', esc_html__( 'Could not get the lists or the segments.', 'newsman' ) );
				}

				$this->available_sms_lists = $this->retrieve_api_sms_all_lists();
				if ( empty( $this->available_sms_lists ) ) {
					$this->available_sms_lists = array();
				}

				$this->set_message_backend( 'updated', esc_html__( 'Options saved.', 'newsman' ) );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Invalid Credentials', 'newsman' ) . ' | ' . $e->getMessage() );
			}
		} else {
			$this->init_form_values_from_option();

			try {
				$this->available_lists = array();

				$lists = $this->retrieve_api_all_lists();
				foreach ( $lists as $value ) {
					if ( 'sms' !== $value['list_type'] ) {
						$this->available_lists[] = $value;
					}
				}
				if ( false !== $this->available_lists ) {
					if ( ! empty( $this->get_form_value( 'newsman_list' ) ) ) {
						$this->available_segments = $this->retrieve_api_all_segments(
							$this->get_form_value( 'newsman_list' )
						);
					}
				} else {
					$this->valid_credentials = false;
					$this->set_message_backend( 'error', esc_html__( 'Could not get the lists or the segments.', 'newsman' ) );
				}

				$this->available_sms_lists = $this->retrieve_api_sms_all_lists();
				if ( empty( $this->available_sms_lists ) ) {
					$this->available_sms_lists = array();
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Could not get the lists or the segments.', 'newsman' ) . ' | ' . $e->getMessage() );
			}
		}

		// Full per-list segment map ([ list_id => [ segment_id => segment_name ] ]) for the
		// live, no-reload list -> segment dropdown filter on the Sync page. Sourced from the same
		// cached bulk `segment.all?list_id=all` payload used everywhere else, so it adds no API call.
		$this->segments_by_list = Segments::get_by_list( get_current_blog_id() );
	}

	/**
	 * Refresh the per-blog Lists / Segments / SMS-lists transient caches by
	 * hitting the Newsman API for every blog with credentials configured.
	 *
	 * On a network install (multisite) this iterates every site via `get_sites()`,
	 * temporarily switches to each one so `Config::get_*` and `set_transient`
	 * resolve against the right options table. Each blog makes at most three API
	 * calls: `list.all`, `segment.all?list_id=all` (one bulk call covers every
	 * list), and `sms_list.all`.
	 *
	 * Per-call failures are caught and logged. The transient row is *not*
	 * touched on failure — whatever previous value was cached stays valid until
	 * its TTL expires (the "stale-on-error" property). At the end a single admin
	 * notice summarises totals + any errors.
	 *
	 * @return void
	 */
	protected function refresh_lists_and_segments_cache() {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$sites    = function_exists( 'get_sites' ) ? get_sites( array( 'number' => 0 ) ) : array();
			$blog_ids = array();
			foreach ( $sites as $site ) {
				$blog_ids[] = (int) $site->blog_id;
			}
		} else {
			$blog_ids = array( (int) get_current_blog_id() );
		}

		$stats = array(
			'blogs_with_credentials' => 0,
			'lists'                  => 0,
			'segments'               => 0,
			'sms_lists'              => 0,
			'errors'                 => array(),
		);

		foreach ( $blog_ids as $blog_id ) {
			$switched = false;
			if ( function_exists( 'is_multisite' ) && is_multisite() && (int) get_current_blog_id() !== $blog_id ) {
				switch_to_blog( $blog_id );
				$switched = true;
			}

			$config  = Config::init();
			$user_id = (int) $config->get_user_id( $blog_id );
			$api_key = (string) $config->get_api_key( $blog_id );

			if ( $user_id <= 0 || '' === $api_key ) {
				if ( $switched ) {
					restore_current_blog();
				}
				continue;
			}

			++$stats['blogs_with_credentials'];

			try {
				$lists = Lists::fetch_from_api( $blog_id, $user_id, $api_key );
				Lists_Transient::save( $blog_id, $user_id, $lists );
				$stats['lists'] += count( $lists );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$stats['errors'][] = sprintf(
					/* translators: 1: blog id; 2: error message. */
					esc_html__( 'Blog #%1$d lists: %2$s', 'newsman' ),
					$blog_id,
					$e->getMessage()
				);
				// Stale-on-error: leave the transient row alone, skip segments
				// for this blog since we have no list catalogue to iterate.
				if ( $switched ) {
					restore_current_blog();
				}
				continue;
			}

			try {
				$by_list = Segments::fetch_all_from_api( $blog_id, $user_id, $api_key );
				Segments_Transient::save_all( $blog_id, $by_list );
				foreach ( $by_list as $segments ) {
					if ( is_array( $segments ) ) {
						$stats['segments'] += count( $segments );
					}
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$stats['errors'][] = sprintf(
					/* translators: 1: blog id; 2: error message. */
					esc_html__( 'Blog #%1$d segments: %2$s', 'newsman' ),
					$blog_id,
					$e->getMessage()
				);
				// Stale-on-error.
			}

			// SMS lists — separate Newsman API endpoint, separate transient. Same
			// stale-on-error pattern as above.
			try {
				$context = new \Newsman\Service\Context\Configuration\User();
				$context->set_user_id( $user_id )->set_api_key( $api_key );
				$sms_service = new \Newsman\Service\Configuration\Sms\GetListAll();
				$sms_lists   = $sms_service->execute( $context );
				if ( is_array( $sms_lists ) ) {
					SmsLists_Transient::save( $blog_id, $user_id, $sms_lists );
					$stats['sms_lists'] += count( $sms_lists );
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$stats['errors'][] = sprintf(
					/* translators: 1: blog id; 2: error message. */
					esc_html__( 'Blog #%1$d SMS lists: %2$s', 'newsman' ),
					$blog_id,
					$e->getMessage()
				);
				// Stale-on-error.
			}

			if ( $switched ) {
				restore_current_blog();
			}
		}

		if ( empty( $stats['errors'] ) ) {
			$this->set_message_backend(
				'updated',
				sprintf(
					/* translators: 1: blog count; 2: lists count; 3: segments count; 4: SMS lists count. */
					esc_html__( 'Newsman cache refreshed: %1$d blog(s), %2$d list(s), %3$d segment(s), %4$d SMS list(s).', 'newsman' ),
					(int) $stats['blogs_with_credentials'],
					(int) $stats['lists'],
					(int) $stats['segments'],
					(int) $stats['sms_lists']
				)
			);
		} else {
			$this->set_message_backend(
				'error',
				sprintf(
					/* translators: 1: blog count; 2: lists count; 3: segments count; 4: SMS lists count; 5: semicolon-separated error list. */
					esc_html__( 'Cache refresh completed with errors. Refreshed %1$d blog(s), %2$d list(s), %3$d segment(s), %4$d SMS list(s). Existing cached values for the failing API calls are kept until they expire. Errors: %5$s', 'newsman' ),
					(int) $stats['blogs_with_credentials'],
					(int) $stats['lists'],
					(int) $stats['segments'],
					(int) $stats['sms_lists'],
					implode( '; ', $stats['errors'] )
				)
			);
		}
	}

	/**
	 * Install or update products feed entry in Newsman
	 *
	 * @return void
	 */
	public function install_products_feed() {
		$exists = new WooCommerceExist();
		if ( empty( $this->get_form_value( 'newsman_list' ) ) || ! $exists->exist() ) {
			return;
		}

		$args           = array(
			'limit'        => -1,
			'return'       => 'ids',
			'status'       => 'publish',
			'stock_status' => 'instock',
		);
		$count_products = wc_get_products( $args );
		if ( empty( $count_products ) ) {
			return;
		}

		$url    = get_site_url() . '/?newsman=products.json&nzmhash=' . $this->get_config()->get_api_key();
		$result = $this->set_feed_on_list(
			$this->get_form_value( 'newsman_list' ),
			$url,
			get_site_url(),
			'NewsMAN',
			true,
		);

		if ( ( false === $result ) || ( 'false' === $result ) ) {
			$this->set_message_backend( 'error', esc_html__( 'Could not update feed list', 'newsman' ) );
		}

		if ( is_array( $result ) && ! empty( $result['feed_id'] ) ) {
			$auth_name  = $this->generate_random_header_name();
			$auth_value = \Newsman\Util\RandomPassword::generate();
			$result     = $this->update_feed_authorize(
				$this->get_form_value( 'newsman_list' ),
				$result['feed_id'],
				$auth_name,
				$auth_value
			);
			if ( false !== $result ) {
				$this->update_export_authorize_header( $auth_name, $auth_value );
			}
		}
	}
}
