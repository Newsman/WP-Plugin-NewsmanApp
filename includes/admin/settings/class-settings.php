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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin configuration settings
 *
 * @class \Newsman\Admin\Settings\Settings
 */
class Settings extends \Newsman\Admin\Settings {
	/**
	 * Page nonce action
	 *
	 * @var string
	 */
	public $nonce_action = 'newsman-settings-settings';

	/**
	 * Form ID. The HTML hidden input name.
	 *
	 * @var string
	 */
	public $form_id = 'newsman_submit';

	/**
	 * Form fields
	 *
	 * @var array
	 */
	public $form_fields = array(
		'newsman_userid',
		'newsman_apikey',
		'newsman_export_authorize_header_name',
		'newsman_export_authorize_header_key',
		'newsman_api',
		'newsman_senduserip',
		'newsman_serverip',
		'newsman_form_id',
		'newsman_newslettertype',
		'newsman_checkoutsms',
		'newsman_checkoutnewsletter',
		'newsman_checkoutnewslettermessage',
		'newsman_checkoutnewsletterdefault',
		'newsman_checkout_order_status',
		'newsman_checkout_order_status_label',
		'newsman_checkout_order_status_default',
		'newsman_myaccountnewsletter',
		'newsman_myaccountnewsletter_menu_label',
		'newsman_myaccountnewsletter_page_title',
		'newsman_myaccountnewsletter_checkbox_label',
		'newsman_developerlogseverity',
		'newsman_developerapitimeout',
		'newsman_developeractiveuserip',
		'newsman_developeruserip',
		'newsman_developerpluginlazypriority',
		'newsman_developer_use_action_scheduler',
		'newsman_developer_use_as_subscribe',
		'newsman_developer_use_as_unsubscribe',
		'newsman_elementor_active',
		'newsman_contact_form_7_active',
		'newsman_wpforms_active',
		'newsman_contact_form_7_export_subscribers',
		'newsman_contact_form_7_export_form_id',
		'newsman_elementor_export_subscribers',
		'newsman_elementor_export_form_id',
		'newsman_wpforms_export_subscribers',
		'newsman_wpforms_export_form_id',
		'newsman_gravity_forms_active',
		'newsman_gravity_forms_export_subscribers',
		'newsman_gravity_forms_export_form_id',
	);

	/**
	 * Includes the html for the admin page.
	 *
	 * @return void
	 */
	public function include_page() {
		include_once plugin_dir_path( __FILE__ ) . '../../../src/backend-settings.php';
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

		$feed_flash = get_transient( 'newsman_feed_message_' . get_current_user_id() );
		if ( ! empty( $feed_flash ) && is_array( $feed_flash ) ) {
			delete_transient( 'newsman_feed_message_' . get_current_user_id() );
			$this->set_message_backend( $feed_flash['status'], $feed_flash['message'] );
		}

		$form_id_value           = '';
		$this->valid_credentials = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $this->form_id ] ) && ! empty( $_POST[ $this->form_id ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$form_id_value = sanitize_text_field( wp_unslash( $_POST[ $this->form_id ] ) );
		}

		if ( 'Y' === $form_id_value ) {
			$previous_userid = get_option( 'newsman_userid' );
			$previous_apikey = get_option( 'newsman_apikey' );
			$this->init_form_values_from_post();
			$this->save_form_values();

			// Invalidate the Elementor + WPForms + Gravity Forms newsletter-forms scans so a freshly-flipped form shows up.
			delete_transient( 'newsman_elementor_newsletter_forms_' . get_current_blog_id() );
			delete_transient( 'newsman_wpforms_newsletter_forms_' . get_current_blog_id() );
			delete_transient( 'newsman_gravity_forms_newsletter_forms_' . get_current_blog_id() );

			$this->is_oauth();

			$new_userid = $this->get_form_value( 'newsman_userid' );
			$new_apikey = $this->get_form_value( 'newsman_apikey' );

			if (
				! empty( $new_userid ) &&
				! empty( $new_apikey ) &&
				( $new_userid !== $previous_userid || $new_apikey !== $previous_apikey )
			) {
				$list_id = get_option( 'newsman_list' );
				if ( ! empty( $list_id ) ) {
					$authenticate_token = $this->ensure_authenticate_token();
					$this->save_list_integration_setup(
						$list_id,
						get_site_url() . '/?newsman_api=v1',
						$authenticate_token
					);

					$settings = $this->get_remarketing_settings( $list_id, $new_userid, $new_apikey );
					if ( ! empty( $settings ) && is_array( $settings ) && ! empty( $settings['javascript'] ) ) {
						$newsman_options = new \Newsman\Options();
						$newsman_options->update_option( 'newsman_scriptjs', $settings['javascript'] );
					}
				}
			}

			try {
				$available_lists = $this->retrieve_api_all_lists(
					$this->get_form_value( 'newsman_userid' ),
					$this->get_form_value( 'newsman_apikey' )
				);

				if ( false === $available_lists ) {
					$this->valid_credentials = false;
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
				$available_lists = $this->retrieve_api_all_lists();

				if ( false === $available_lists ) {
					$this->valid_credentials = false;
				}
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
				$this->valid_credentials = false;
				$this->set_message_backend( 'error', esc_html__( 'Invalid Credentials', 'newsman' ) . ' | ' . $e->getMessage() );
			}
		}

		$this->warn_if_multiple_export_sources_active();
	}

	/**
	 * Push an admin error notice when more than one "Export Subscribers from Form
	 * Submissions" source is fully configured (toggle on AND a Source Form selected).
	 *
	 * The retriever priority chain (Elementor - CF7 - WPForms - Gravity Forms)
	 * silently picks the first eligible source, so a misconfigured admin would
	 * otherwise not realize the later sources are being ignored.
	 *
	 * @return void
	 */
	protected function warn_if_multiple_export_sources_active() {
		$active = array();
		if ( 'on' === (string) $this->get_form_value( 'newsman_elementor_export_subscribers' )
			&& '' !== (string) $this->get_form_value( 'newsman_elementor_export_form_id' ) ) {
			$active[] = esc_html__( 'Elementor', 'newsman' );
		}
		if ( 'on' === (string) $this->get_form_value( 'newsman_contact_form_7_export_subscribers' )
			&& '' !== (string) $this->get_form_value( 'newsman_contact_form_7_export_form_id' ) ) {
			$active[] = esc_html__( 'Contact Form 7', 'newsman' );
		}
		if ( 'on' === (string) $this->get_form_value( 'newsman_wpforms_export_subscribers' )
			&& '' !== (string) $this->get_form_value( 'newsman_wpforms_export_form_id' ) ) {
			$active[] = esc_html__( 'WPForms', 'newsman' );
		}
		if ( 'on' === (string) $this->get_form_value( 'newsman_gravity_forms_export_subscribers' )
			&& '' !== (string) $this->get_form_value( 'newsman_gravity_forms_export_form_id' ) ) {
			$active[] = esc_html__( 'Gravity Forms', 'newsman' );
		}

		if ( count( $active ) <= 1 ) {
			return;
		}

		$this->set_message_backend(
			'error',
			sprintf(
				/* translators: %s: comma-separated list of integration names (e.g. "Elementor, Contact Form 7"). */
				esc_html__( 'More than one "Export Subscribers from Form Submissions" is enabled (%s). Only the first in priority order (Elementor - Contact Form 7 - WPForms - Gravity Forms) will be used by subscriber.list; the others are ignored. Disable all but one.', 'newsman' ),
				implode( ', ', $active )
			)
		);
	}

	/**
	 * Build the CF7 form dropdown for the "Export Subscribers from Form Submissions" option.
	 *
	 * Returns CF7 forms (post_id => "post_title (slug)") whose persisted `newsman` property
	 * has both `enable=true` AND `newsletter_form=true`. The retriever resolves the post_id
	 * to the post_name (slug) at query time for the Flamingo channel taxonomy filter.
	 *
	 * @return array<int,string>
	 */
	public function get_cf7_newsletter_forms() {
		if ( ! class_exists( '\WPCF7_ContactForm' ) ) {
			return array();
		}

		// Include trashed CF7 forms: Flamingo persists submissions to a channel taxonomy
		// keyed by form slug, independent of the form post's status. `'post_status'=>'any'`
		// would silently exclude trash (it filters statuses with exclude_from_search=true),
		// so list the statuses explicitly.
		$forms = array();
		$query = new \WP_Query(
			array(
				'post_type'              => 'wpcf7_contact_form',
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			)
		);

		if ( empty( $query->posts ) ) {
			return $forms;
		}

		$trash_suffix = ' [' . esc_html__( 'Trash', 'newsman' ) . ']';
		foreach ( $query->posts as $post_id ) {
			$contact_form = \WPCF7_ContactForm::get_instance( $post_id );
			if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'prop' ) ) {
				continue;
			}
			$prop = (array) $contact_form->prop( \Newsman\ContactForm7\FormPanel::PROPERTY );
			if ( empty( $prop['enable'] ) || empty( $prop['newsletter_form'] ) ) {
				continue;
			}
			$title = method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '';
			if ( '' === $title ) {
				$title = (string) get_post_field( 'post_name', $post_id );
			}
			if ( '' === $title ) {
				$title = sprintf( /* translators: %d: CF7 form post id. */ __( 'Form #%d', 'newsman' ), (int) $post_id );
			}
			if ( 'trash' === (string) get_post_field( 'post_status', $post_id ) ) {
				$title .= $trash_suffix;
			}
			$forms[ (int) $post_id ] = $title;
		}

		return apply_filters( 'newsman_admin_settings_cf7_newsletter_forms', $forms );
	}

	/**
	 * Build the Elementor form dropdown for the "Export Subscribers from Form Submissions" option.
	 *
	 * Scans `_elementor_data` post meta on every post/page to extract every Form / Atomic_Form
	 * widget whose `newsman_enable` AND `newsman_newsletter_form` are both truthy. Result is
	 * keyed by the 8-char widget ID — same value that appears in Elementor's submissions grid
	 * and matches `wp_e_submissions.form_id`. Cached for 5 minutes in a per-blog transient;
	 * invalidated on Newsman settings save and on every `elementor/document/after_save`
	 * (see `Newsman\Elementor\Integration::invalidate_form_dropdown_cache`).
	 *
	 * @return array<string,string>  widget_id => "Host page title — Form label (widget id)"
	 */
	public function get_elementor_newsletter_forms() {
		$transient_key = 'newsman_elementor_newsletter_forms_' . get_current_blog_id();
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$forms = array();
		global $wpdb;

		// Include trashed pages: the Elementor form's submissions live in wp_e_submissions
		// keyed by widget id, so they survive when the host page is trashed. Exclude
		// revisions (post_type='revision', status='inherit') — they duplicate their
		// parent's _elementor_data and would otherwise leak forms whose parent is in trash.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value, p.post_title, p.post_status
				 FROM   {$wpdb->postmeta} pm
				 JOIN   {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE  pm.meta_key = %s
				   AND  pm.meta_value LIKE %s
				   AND  pm.meta_value LIKE %s
				   AND  p.post_status NOT IN ('auto-draft')
				   AND  p.post_type != 'revision'",
				'_elementor_data',
				'%newsman_enable%',
				'%newsman_newsletter_form%'
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$trash_suffix = ' [' . esc_html__( 'Trash', 'newsman' ) . ']';
		foreach ( $rows as $row ) {
			$data = json_decode( (string) $row['meta_value'], true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$post_title = (string) $row['post_title'];
			if ( 'trash' === (string) $row['post_status'] ) {
				$post_title .= $trash_suffix;
			}
			// `_elementor_data` decodes to a list of top-level sections; walk each one
			// individually since the walker inspects keys directly on the node it receives.
			foreach ( $data as $top_node ) {
				$this->walk_elementor_data_for_newsletter_forms( $top_node, $post_title, $forms );
			}
		}

		$forms = apply_filters( 'newsman_admin_settings_elementor_newsletter_forms', $forms );

		set_transient( $transient_key, $forms, 5 * MINUTE_IN_SECONDS );
		return $forms;
	}

	/**
	 * Build the WPForms form dropdown for the "Export Subscribers from Form Submissions" option.
	 *
	 * Returns WPForms forms (post_id => "Form Title (#post_id)") whose persisted form
	 * settings have both `newsman_enable='1'` AND `newsman_newsletter_form='1'`. The
	 * post_id is also the value of `wp_wpforms_entries.form_id`, which the retriever
	 * uses to scope its query.
	 *
	 * Cached for 5 minutes in a per-blog transient; invalidated on Newsman settings
	 * save and on `wpforms_save_form` (see `Newsman\WPForms\Integration`).
	 *
	 * @return array<int,string>
	 */
	public function get_wpforms_newsletter_forms() {
		$transient_key = 'newsman_wpforms_newsletter_forms_' . get_current_blog_id();
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$forms = array();

		// WPForms can be Lite (free) or Pro — both define WPFORMS_VERSION when their
		// main plugin file loads. We don't gate on `post_type_exists('wpforms')` since
		// the post type only registers on `init`, and this dropdown may be built
		// from contexts where `init` has not yet fired.
		if ( ! defined( 'WPFORMS_VERSION' ) ) {
			set_transient( $transient_key, $forms, 5 * MINUTE_IN_SECONDS );
			return $forms;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'wpforms',
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $query->posts ) ) {
			set_transient( $transient_key, $forms, 5 * MINUTE_IN_SECONDS );
			return $forms;
		}

		// Include trashed WPForms forms: entries persist in wp_wpforms_entries keyed by
		// form_id (= post_id), independent of the form post's status. Exclude auto-draft
		// only.
		$trash_suffix = ' [' . esc_html__( 'Trash', 'newsman' ) . ']';
		foreach ( $query->posts as $post ) {
			if ( 'auto-draft' === $post->post_status ) {
				continue;
			}
			$data = json_decode( (string) $post->post_content, true );
			if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
				continue;
			}
			$settings = $data['settings'];

			$enable     = isset( $settings['newsman_enable'] ) ? (string) $settings['newsman_enable'] : '';
			$newsletter = isset( $settings['newsman_newsletter_form'] ) ? (string) $settings['newsman_newsletter_form'] : '';
			if ( '1' !== $enable || '1' !== $newsletter ) {
				continue;
			}

			$title = isset( $settings['form_title'] ) ? trim( (string) $settings['form_title'] ) : '';
			if ( '' === $title ) {
				$title = (string) $post->post_title;
			}
			if ( '' === $title ) {
				$title = sprintf( /* translators: %d: WPForms form post id. */ __( 'Form #%d', 'newsman' ), (int) $post->ID );
			}
			$label = sprintf( '%1$s (#%2$d)', $title, (int) $post->ID );
			if ( 'trash' === (string) $post->post_status ) {
				$label .= $trash_suffix;
			}
			$forms[ (int) $post->ID ] = $label;
		}

		$forms = apply_filters( 'newsman_admin_settings_wpforms_newsletter_forms', $forms );

		set_transient( $transient_key, $forms, 5 * MINUTE_IN_SECONDS );
		return $forms;
	}

	/**
	 * Build the Gravity Forms form dropdown for the "Export Subscribers from Form Submissions" option.
	 *
	 * Returns GF forms (form_id => "Form Title (#form_id)") whose persisted form
	 * settings (inside `wp_gf_form_meta.display_meta` JSON) have both
	 * `newsman_enable='1'` AND `newsman_newsletter_form='1'`. The form_id matches
	 * `wp_gf_entry.form_id`, which the retriever uses to scope its query.
	 *
	 * Cached for 5 minutes in a per-blog transient; invalidated on Newsman settings
	 * save and on `gform_after_save_form` (see `Newsman\GravityForms\Integration`).
	 *
	 * @return array<int,string>
	 */
	public function get_gravity_forms_newsletter_forms() {
		$transient_key = 'newsman_gravity_forms_newsletter_forms_' . get_current_blog_id();
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$forms = array();

		// Gate on the GF main constant. Querying wp_gf_form directly avoids the
		// GFAPI-bootstrap timing trap (GF init runs late, same priority as our
		// API router).
		if ( ! defined( 'GF_MIN_WP_VERSION' ) ) {
			set_transient( $transient_key, $forms, 5 * MINUTE_IN_SECONDS );
			return $forms;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT f.id, f.title, f.is_trash, fm.display_meta
			 FROM {$wpdb->prefix}gf_form f
			 LEFT JOIN {$wpdb->prefix}gf_form_meta fm ON fm.form_id = f.id",
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$trash_suffix = ' [' . esc_html__( 'Trash', 'newsman' ) . ']';
		foreach ( $rows as $row ) {
			$data = isset( $row['display_meta'] ) ? json_decode( (string) $row['display_meta'], true ) : null;
			if ( ! is_array( $data ) ) {
				continue;
			}
			$enable     = isset( $data['newsman_enable'] ) ? (string) $data['newsman_enable'] : '';
			$newsletter = isset( $data['newsman_newsletter_form'] ) ? (string) $data['newsman_newsletter_form'] : '';
			if ( '1' !== $enable || '1' !== $newsletter ) {
				continue;
			}

			$title = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
			if ( '' === $title && isset( $data['title'] ) ) {
				$title = trim( (string) $data['title'] );
			}
			if ( '' === $title ) {
				$title = sprintf( /* translators: %d: Gravity Forms form id. */ __( 'Form #%d', 'newsman' ), (int) $row['id'] );
			}
			$label = sprintf( '%1$s (#%2$d)', $title, (int) $row['id'] );
			if ( ! empty( $row['is_trash'] ) ) {
				$label .= $trash_suffix;
			}
			$forms[ (int) $row['id'] ] = $label;
		}

		$forms = apply_filters( 'newsman_admin_settings_gravity_forms_newsletter_forms', $forms );

		set_transient( $transient_key, $forms, 5 * MINUTE_IN_SECONDS );
		return $forms;
	}

	/**
	 * Recursive walker for `_elementor_data` that collects qualifying form widgets.
	 *
	 * Matches both legacy Pro Form widgets (`widgetType === 'form'`) and Atomic Forms
	 * (`elType === 'e-form'`; Atomic Form is a container element, not a widget). The
	 * flag values are stored either as legacy 'yes' strings or as Atomic boolean
	 * prop-objects (`['$$type' => 'boolean', 'value' => true]`).
	 *
	 * @param array  $node       Current Elementor element node.
	 * @param string $post_title Title of the hosting post (for dropdown labels).
	 * @param array  $accumulator Out-param map of `widget_id => label`.
	 * @return void
	 */
	protected function walk_elementor_data_for_newsletter_forms( $node, $post_title, &$accumulator ) {
		if ( ! is_array( $node ) ) {
			return;
		}

		$widget_type = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
		$el_type     = isset( $node['elType'] ) ? (string) $node['elType'] : '';
		$settings    = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$id          = isset( $node['id'] ) ? (string) $node['id'] : '';

		// Legacy Pro Form widget stores its kind in `widgetType` ('form'); Atomic Form
		// (Elementor 4.x) is a container element and stores its kind in `elType` ('e-form').
		$is_form = ( 'form' === $widget_type || 'e-form' === $widget_type || 'e-form' === $el_type );

		if ( '' !== $id && $is_form ) {
			$enable          = $this->resolve_elementor_flag( $settings, 'newsman_enable' );
			$newsletter_form = $this->resolve_elementor_flag( $settings, 'newsman_newsletter_form' );

			if ( $enable && $newsletter_form ) {
				$form_name = $this->resolve_elementor_string( $settings, 'form_name' );
				if ( '' === $form_name ) {
					$form_name = esc_html__( 'Untitled form', 'newsman' );
				}
				$label = sprintf( '%1$s — %2$s (%3$s)', $post_title, $form_name, $id );

				$accumulator[ $id ] = $label;
			}
		}

		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				$this->walk_elementor_data_for_newsletter_forms( $child, $post_title, $accumulator );
			}
		}
	}

	/**
	 * Resolve a boolean Elementor setting that may be stored in legacy ('yes'/'') or
	 * Atomic (`['$$type' => 'boolean', 'value' => true]`) shape.
	 *
	 * @param array  $settings Elementor element settings.
	 * @param string $key      Setting key.
	 * @return bool
	 */
	protected function resolve_elementor_flag( $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return false;
		}
		$value = $settings[ $key ];
		if ( is_array( $value ) ) {
			if ( isset( $value['value'] ) ) {
				$value = $value['value'];
			} else {
				return false;
			}
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return 'yes' === $value || '1' === $value || 'true' === $value;
		}
		return (bool) $value;
	}

	/**
	 * Resolve a string Elementor setting in either legacy or Atomic shape.
	 *
	 * @param array  $settings Elementor element settings.
	 * @param string $key      Setting key.
	 * @return string
	 */
	protected function resolve_elementor_string( $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return '';
		}
		$value = $settings[ $key ];
		if ( is_array( $value ) && isset( $value['value'] ) ) {
			$value = $value['value'];
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
