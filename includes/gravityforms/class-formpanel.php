<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman Gravity Forms Settings sub-page.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\GravityForms;

use Newsman\Subscribe\Lists;
use Newsman\Subscribe\Segments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form Settings sub-page injector for the Newsman Gravity Forms integration.
 *
 * Adds a "Newsman" item to each form's Form Settings sidebar menu and renders a
 * sub-page with: enable toggle, newsletter-form toggle, list + segment, opt-in
 * mode, email/firstname/lastname/phone field dropdowns, and a checkbox list of
 * every form field for properties.
 *
 * Settings are persisted onto the form's top-level array under `newsman_*`
 * keys and saved via `GFAPI::update_form`, which serializes back to
 * `wp_gf_form_meta.display_meta`.
 *
 * @class \Newsman\GravityForms\FormPanel
 */
class FormPanel {
	/**
	 * Sub-view slug used in `Form Settings -> Newsman` URLs (`&subview=newsman`).
	 */
	public const SUBVIEW = 'newsman';

	/**
	 * Field types that never carry user-entered values; excluded from both the
	 * email-field dropdown and the property checkboxes.
	 *
	 * @var string[]
	 */
	protected const NON_DATA_TYPES = array(
		'section',
		'page',
		'html',
		'captcha',
		'fileupload',
		'password', // sensitive; exclude by default
		'creditcard',
		'product',
		'total',
		'donation',
		'shipping',
		'option',
		'quantity',
	);

	/**
	 * Compound field types whose sub-inputs are exposed individually as choices
	 * (since `wp_gf_entry_meta.meta_key` rows are keyed by the dotted sub-input id).
	 *
	 * @var string[]
	 */
	protected const COMPOUND_FIELD_TYPES = array( 'name', 'address' );

	/**
	 * Add the "Newsman" item to the Form Settings sidebar.
	 *
	 * Hooked on `gform_form_settings_menu`.
	 *
	 * @param array $tabs    Existing menu items.
	 * @param int   $form_id Current form id.
	 * @return array
	 */
	public function add_menu_item( $tabs, $form_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_array( $tabs ) ) {
			return $tabs;
		}
		/**
		 * Filter the label of the Newsman item in the Gravity Forms Form Settings sidebar.
		 *
		 * @param string $label   Default label.
		 * @param int    $form_id Current form id.
		 */
		$label = apply_filters(
			'newsman_gravity_forms_menu_label',
			esc_html__( 'Newsman', 'newsman' ),
			$form_id
		);
		// GF's `GFCommon::get_icon_markup()` accepts a URL and renders it as
		// <img>. The 20x20 `newsman-mini-a60400.png` (red-on-transparent variant
		// of newsman-mini.png) slots cleanly into the sidebar icon space and is
		// legible on GF's light sidebar background.
		$icon = defined( 'NEWSMAN_PLUGIN_URL' ) ? NEWSMAN_PLUGIN_URL . 'src/img/newsman-mini-a60400.png' : 'gform-icon--newsletter';
		/**
		 * Filter the icon used for the Newsman item in the Gravity Forms Form
		 * Settings sidebar. Can be a URL (default), an inline `<svg>` string, a
		 * `gform-icon--*` class, a `dashicons-*` class, or a font-awesome class.
		 *
		 * @param string $icon    Default icon (Newsman mini-logo URL).
		 * @param int    $form_id Current form id.
		 */
		$icon = apply_filters( 'newsman_gravity_forms_menu_icon', $icon, $form_id );
		$tabs[] = array(
			'name'  => self::SUBVIEW,
			'label' => (string) $label,
			'icon'  => (string) $icon,
		);
		return $tabs;
	}

	/**
	 * Render the Newsman sub-page (and handle its POST save).
	 *
	 * Hooked on `gform_form_settings_page_newsman`. Gravity Forms wraps this
	 * output inside the standard Form Settings chrome (header, sidebar, footer).
	 *
	 * @return void
	 */
	public function render() {
		if ( ! class_exists( '\GFAPI' ) || ! class_exists( '\GFFormSettings' ) ) {
			echo '<p>' . esc_html__( 'Gravity Forms is not loaded.', 'newsman' ) . '</p>';
			return;
		}

		// Wrap our content in Gravity Forms' standard Form Settings chrome: the
		// `gform_form_settings_page_{subview}` dispatcher does NOT call page_header
		// / page_footer itself (each consumer is responsible), so without these the
		// left-side sidebar (Settings / Confirmations / Notifications / Newsman /
		// Personal Data) would be missing.
		\GFFormSettings::page_header( esc_html__( 'Newsman', 'newsman' ) );

		$form_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $form_id <= 0 ) {
			echo '<p>' . esc_html__( 'Invalid form id.', 'newsman' ) . '</p>';
			\GFFormSettings::page_footer();
			return;
		}

		$form = \GFAPI::get_form( $form_id );
		if ( empty( $form ) ) {
			echo '<p>' . esc_html__( 'Form not found.', 'newsman' ) . '</p>';
			\GFFormSettings::page_footer();
			return;
		}

		// Handle POST save.
		if ( isset( $_POST['newsman_gf_save'] )
			&& isset( $_POST['_newsman_gf_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_newsman_gf_nonce'] ) ), 'newsman_gf_settings_' . $form_id )
			&& current_user_can( 'manage_options' )
		) {
			$form = self::apply_post_to_form( $form );
			\GFAPI::update_form( $form );
			// Invalidate the Settings page form-dropdown cache so a freshly-flipped
			// newsletter-form shows up immediately. Integration also hooks
			// `gform_after_save_form` for the same reason, but `GFAPI::update_form`
			// only fires that hook in some contexts.
			delete_transient( 'newsman_gravity_forms_newsletter_forms_' . get_current_blog_id() );
			echo '<div class="updated notice"><p>' . esc_html__( 'Newsman settings saved.', 'newsman' ) . '</p></div>';
		}

		$prop    = self::resolve_settings( $form );
		$choices = self::scan_field_choices( $form );

		$lists       = Lists::get_for_select( get_current_blog_id() );
		$list_select = array( '' => esc_html__( '— select a list —', 'newsman' ) );
		if ( is_array( $lists ) ) {
			foreach ( $lists as $list_id => $list_name ) {
				$list_select[ (string) $list_id ] = (string) $list_name;
			}
		}

		// Lazy-load: render the segments select with only the currently saved
		// list's segments. Switching the list triggers `wp_ajax_newsman_load_segments`
		// to repopulate. Mitigates Newsman's `segment.all` 10/min rate limit.
		$gf_current_list_id = isset( $prop['newsman_list_id'] ) ? (string) $prop['newsman_list_id'] : '';
		$segment_select     = array( '' => esc_html__( '— none —', 'newsman' ) );
		if ( '' !== $gf_current_list_id ) {
			$segments_for_list = Segments::get_for_list( get_current_blog_id(), $gf_current_list_id );
			foreach ( $segments_for_list as $segment_id => $segment_name ) {
				$segment_select[ (string) $segment_id ] = (string) $segment_name;
			}
		}

		$action_url = esc_url( admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=' . self::SUBVIEW . '&id=' . $form_id ) );

		// Tooltip strings — passed directly to `gform_tooltip()` (any multi-word
		// argument is treated as raw tooltip text by Gravity Forms). The leading
		// `<h6>` becomes the tooltip popover title.
		$tt = array(
			'enable'          => '<h6>' . esc_html__( 'Send to Newsman', 'newsman' ) . '</h6>' . esc_html__( 'When enabled, every successful submission of this form is subscribed to the selected Newsman list.', 'newsman' ),
			'newsletter_form' => '<h6>' . esc_html__( 'Newsletter form', 'newsman' ) . '</h6>' . esc_html__( 'When on, submissions go to the list and segment configured in Newsman - Sync (the per-form list and segment below are stored but ignored).', 'newsman' ),
			'list'            => '<h6>' . esc_html__( 'Newsman list', 'newsman' ) . '</h6>' . esc_html__( 'List for this campaign-specific form. Ignored when "Newsletter form" is on (the Sync section list is used instead).', 'newsman' ) . ' ' . esc_html__( 'Do not pick the same list as the one configured in Newsman - Sync unless you intend submissions to land in your global newsletter list. To send to that list, turn on "Newsletter form" instead.', 'newsman' ),
			'segment'         => '<h6>' . esc_html__( 'Newsman segment', 'newsman' ) . '</h6>' . esc_html__( 'Optional. Segments are list-scoped — only segments that belong to the selected list are kept. If they do not match, the segment is dropped at submit time.', 'newsman' ),
			'optin_mode'      => '<h6>' . esc_html__( 'Opt-in mode', 'newsman' ) . '</h6>' . esc_html__( 'Single opt-in subscribes the user immediately. Double opt-in sends a confirmation email; the subscription is only completed once the user clicks the link.', 'newsman' ),
			'email_field'     => '<h6>' . esc_html__( 'Email field', 'newsman' ) . '</h6>' . esc_html__( 'The selected field provides the subscriber email. Any field type may be used.', 'newsman' ),
			'firstname_field' => '<h6>' . esc_html__( 'Firstname field', 'newsman' ) . '</h6>' . esc_html__( 'Optional. For Name fields, pick the sub-input corresponding to the First Name (e.g. 1.3).', 'newsman' ),
			'lastname_field'  => '<h6>' . esc_html__( 'Lastname field', 'newsman' ) . '</h6>' . esc_html__( 'Optional. For Name fields, pick the sub-input corresponding to the Last Name (e.g. 1.6).', 'newsman' ),
			'phone_field'     => '<h6>' . esc_html__( 'Phone field', 'newsman' ) . '</h6>' . esc_html__( 'Optional. When set, the field value is sent as the subscriber phone under the `phone` property key.', 'newsman' ),
			'send_fields'     => '<h6>' . esc_html__( 'Send as properties', 'newsman' ) . '</h6>' . esc_html__( 'Each checked field is sent to Newsman as a subscriber property keyed by the field label sanitized to lowercase snake_case (or by the field id when no label is set).', 'newsman' ),
		);
		?>
		<form method="post" action="<?php echo $action_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="gform_settings_form">
			<?php wp_nonce_field( 'newsman_gf_settings_' . $form_id, '_newsman_gf_nonce' ); ?>
			<input type="hidden" name="newsman_gf_save" value="1" />

			<div class="gform-settings-panel gform-settings-panel--full">
				<header class="gform-settings-panel__header">
					<legend class="gform-settings-panel__title"><?php echo esc_html__( 'Newsman', 'newsman' ); ?></legend>
				</header>
				<div class="gform-settings-panel__content">
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="newsman_enable"><?php echo esc_html__( 'Send to Newsman', 'newsman' ); ?> <?php gform_tooltip( $tt['enable'] ); ?></label></th>
								<td>
									<input type="checkbox" name="newsman_enable" id="newsman_enable" value="1" <?php checked( '1', (string) $prop['newsman_enable'] ); ?> />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="newsman_newsletter_form"><?php echo esc_html__( 'Newsletter form', 'newsman' ); ?> <?php gform_tooltip( $tt['newsletter_form'] ); ?></label></th>
								<td>
									<input type="checkbox" name="newsman_newsletter_form" id="newsman_newsletter_form" value="1" <?php checked( '1', (string) $prop['newsman_newsletter_form'] ); ?> />
								</td>
							</tr>
							<tr class="newsman-gf-list-row">
								<th scope="row"><label for="newsman_list_id"><?php echo esc_html__( 'Newsman list', 'newsman' ); ?> <?php gform_tooltip( $tt['list'] ); ?></label></th>
								<td>
									<select name="newsman_list_id" id="newsman_list_id">
										<?php foreach ( $list_select as $value => $label ) : ?>
											<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $prop['newsman_list_id'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr class="newsman-gf-segment-row">
								<th scope="row"><label for="newsman_segment_id"><?php echo esc_html__( 'Newsman segment', 'newsman' ); ?> <?php gform_tooltip( $tt['segment'] ); ?></label></th>
								<td>
									<select name="newsman_segment_id" id="newsman_segment_id">
										<?php foreach ( $segment_select as $value => $label ) : ?>
											<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $prop['newsman_segment_id'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="newsman_optin_mode"><?php echo esc_html__( 'Opt-in mode', 'newsman' ); ?> <?php gform_tooltip( $tt['optin_mode'] ); ?></label></th>
								<td>
									<select name="newsman_optin_mode" id="newsman_optin_mode">
										<option value="single" <?php selected( 'single', (string) $prop['newsman_optin_mode'] ); ?>><?php echo esc_html__( 'Single opt-in', 'newsman' ); ?></option>
										<option value="double" <?php selected( 'double', (string) $prop['newsman_optin_mode'] ); ?>><?php echo esc_html__( 'Double opt-in', 'newsman' ); ?></option>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<?php if ( empty( $choices ) ) : ?>
				<div class="gform-settings-panel gform-settings-panel--full">
					<header class="gform-settings-panel__header">
						<legend class="gform-settings-panel__title"><?php echo esc_html__( 'Field mapping', 'newsman' ); ?></legend>
					</header>
					<div class="gform-settings-panel__content">
						<p><em><?php echo esc_html__( 'Add a field to the form to enable Newsman.', 'newsman' ); ?></em></p>
					</div>
				</div>
			<?php else : ?>
				<?php
				$email_options = array();
				foreach ( $choices as $field_id => $choice ) {
					$email_options[ (string) $field_id ] = self::format_choice_label( $choice );
				}
				$name_options = array( '' => esc_html__( '— none —', 'newsman' ) );
				foreach ( $email_options as $field_id => $label ) {
					$name_options[ (string) $field_id ] = $label;
				}
				$send_fields  = is_array( $prop['newsman_send_fields'] ) ? $prop['newsman_send_fields'] : array();
				$has_existing = ! empty( $send_fields );
				$reserved     = array(
					(string) $prop['newsman_email_field']     => 'email',
					(string) $prop['newsman_firstname_field'] => 'firstname',
					(string) $prop['newsman_lastname_field']  => 'lastname',
					(string) $prop['newsman_phone_field']     => 'phone',
				);
				unset( $reserved[''] );
				?>
				<div class="gform-settings-panel gform-settings-panel--full">
					<header class="gform-settings-panel__header">
						<legend class="gform-settings-panel__title"><?php echo esc_html__( 'Field mapping', 'newsman' ); ?></legend>
					</header>
					<div class="gform-settings-panel__content">
						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row"><label for="newsman_email_field"><?php echo esc_html__( 'Email field', 'newsman' ); ?> <?php gform_tooltip( $tt['email_field'] ); ?></label></th>
									<td>
										<select name="newsman_email_field" id="newsman_email_field">
											<?php foreach ( $email_options as $value => $label ) : ?>
												<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $prop['newsman_email_field'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="newsman_firstname_field"><?php echo esc_html__( 'Firstname field', 'newsman' ); ?> <?php gform_tooltip( $tt['firstname_field'] ); ?></label></th>
									<td>
										<select name="newsman_firstname_field" id="newsman_firstname_field">
											<?php foreach ( $name_options as $value => $label ) : ?>
												<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $prop['newsman_firstname_field'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="newsman_lastname_field"><?php echo esc_html__( 'Lastname field', 'newsman' ); ?> <?php gform_tooltip( $tt['lastname_field'] ); ?></label></th>
									<td>
										<select name="newsman_lastname_field" id="newsman_lastname_field">
											<?php foreach ( $name_options as $value => $label ) : ?>
												<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $prop['newsman_lastname_field'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="newsman_phone_field"><?php echo esc_html__( 'Phone field', 'newsman' ); ?> <?php gform_tooltip( $tt['phone_field'] ); ?></label></th>
									<td>
										<select name="newsman_phone_field" id="newsman_phone_field">
											<?php foreach ( $name_options as $value => $label ) : ?>
												<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $prop['newsman_phone_field'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="gform-settings-panel gform-settings-panel--full">
					<header class="gform-settings-panel__header">
						<legend class="gform-settings-panel__title"><?php echo esc_html__( 'Send as properties', 'newsman' ); ?> <?php gform_tooltip( $tt['send_fields'] ); ?></legend>
					</header>
					<div class="gform-settings-panel__content">
						<ul style="margin:0;padding:0;list-style:none;">
						<?php foreach ( $choices as $field_id => $choice ) : ?>
							<?php
							$is_reserved = isset( $reserved[ (string) $field_id ] );
							$default_on  = $has_existing ? ! empty( $send_fields[ (string) $field_id ] ) : ! $is_reserved;
							$id_attr     = 'newsman_gf_send_' . preg_replace( '/[^a-z0-9_]/i', '_', (string) $field_id );
							?>
							<li>
								<label for="<?php echo esc_attr( $id_attr ); ?>">
									<input type="checkbox" id="<?php echo esc_attr( $id_attr ); ?>" name="newsman_send_fields[<?php echo esc_attr( (string) $field_id ); ?>]" value="1" <?php checked( true, $default_on ); ?> <?php disabled( true, $is_reserved ); ?> />
									<?php echo esc_html( self::format_choice_label( $choice ) ); ?>
									<?php if ( $is_reserved ) : ?>
										<em>(<?php echo esc_html( $reserved[ (string) $field_id ] ); ?>)</em>
									<?php endif; ?>
								</label>
							</li>
						<?php endforeach; ?>
						</ul>
					</div>
				</div>
			<?php endif; ?>

			<p class="submit">
				<input type="submit" name="newsman_gf_submit" value="<?php echo esc_attr__( 'Update Settings', 'newsman' ); ?>" class="button button-primary button-large" />
			</p>
		</form>
		<script>
		(function () {
			var ajaxUrl   = <?php echo wp_json_encode( esc_url_raw( admin_url( 'admin-ajax.php' ) ) ); ?>;
			var ajaxNonce = <?php echo wp_json_encode( \Newsman\Admin\Ajax\Segments_Endpoint::create_nonce() ); ?>;
			var noneLabel = <?php echo wp_json_encode( esc_html__( '— none —', 'newsman' ) ); ?>;

			// Hide Newsman List + Newsman Segment when "Newsletter form" is ON.
			// When on, submissions go to the global Sync list/segment, so the
			// per-form rows are stored but ignored. Mirrors the same behavior in
			// the Elementor / CF7 / WPForms panels.
			var newsletter = document.getElementById('newsman_newsletter_form');
			function applyVisibility() {
				if (!newsletter) return;
				var hide = newsletter.checked;
				var list = document.querySelector('tr.newsman-gf-list-row');
				var seg  = document.querySelector('tr.newsman-gf-segment-row');
				if (list) list.style.display = hide ? 'none' : '';
				if (seg)  seg.style.display  = hide ? 'none' : '';
			}
			if (newsletter) {
				newsletter.addEventListener('change', applyVisibility);
				applyVisibility();
			}

			// Lazy-load segments on list change. See `wp_segment_all_rate_limit`
			// notes — Newsman caps `segment.all` at 10 calls/minute so eager
			// loading would trip on accounts with many lists.
			var listEl = document.getElementById('newsman_list_id');
			var segEl  = document.getElementById('newsman_segment_id');
			var savedSegment = segEl ? String(segEl.value || '') : '';
			function reloadSegments() {
				if (!listEl || !segEl) return;
				var currentListId = String(listEl.value || '');
				if ('' === currentListId) {
					segEl.innerHTML = '';
					var optNone = document.createElement('option');
					optNone.value = '';
					optNone.textContent = noneLabel;
					segEl.appendChild(optNone);
					segEl.value = '';
					return;
				}
				segEl.disabled = true;
				var body = new URLSearchParams();
				body.append('action', 'newsman_load_segments');
				body.append('list_id', currentListId);
				body.append('_ajax_nonce', ajaxNonce);
				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				}).then(function (r) { return r.json(); })
				.then(function (resp) {
					segEl.disabled = false;
					if (!resp || !resp.success) return;
					var segments = (resp.data && resp.data.segments) || {};
					var prev = String(segEl.value || '') || savedSegment;
					segEl.innerHTML = '';
					var optNone = document.createElement('option');
					optNone.value = '';
					optNone.textContent = noneLabel;
					segEl.appendChild(optNone);
					Object.keys(segments).forEach(function (id) {
						var o = document.createElement('option');
						o.value = id;
						o.textContent = segments[id];
						segEl.appendChild(o);
					});
					segEl.value = segments.hasOwnProperty(prev) ? prev : '';
				})['catch'](function () { segEl.disabled = false; });
			}
			if (listEl) listEl.addEventListener('change', reloadSegments);
		})();
		</script>
		<?php
		\GFFormSettings::page_footer();
	}

	/**
	 * Read the persisted Newsman settings for this form, with defaults.
	 *
	 * @param array $form Gravity Forms form array (as returned by `GFAPI::get_form`).
	 * @return array
	 */
	public static function resolve_settings( $form ) {
		if ( ! is_array( $form ) ) {
			$form = array();
		}
		$optin_mode = isset( $form['newsman_optin_mode'] ) ? (string) $form['newsman_optin_mode'] : 'single';
		if ( 'double' !== $optin_mode ) {
			$optin_mode = 'single';
		}

		return array(
			'newsman_enable'          => isset( $form['newsman_enable'] ) ? (string) $form['newsman_enable'] : '',
			'newsman_newsletter_form' => isset( $form['newsman_newsletter_form'] ) ? (string) $form['newsman_newsletter_form'] : '',
			'newsman_list_id'         => isset( $form['newsman_list_id'] ) ? (string) $form['newsman_list_id'] : '',
			'newsman_segment_id'      => isset( $form['newsman_segment_id'] ) ? (string) $form['newsman_segment_id'] : '',
			'newsman_optin_mode'      => $optin_mode,
			'newsman_email_field'     => isset( $form['newsman_email_field'] ) ? (string) $form['newsman_email_field'] : '',
			'newsman_firstname_field' => isset( $form['newsman_firstname_field'] ) ? (string) $form['newsman_firstname_field'] : '',
			'newsman_lastname_field'  => isset( $form['newsman_lastname_field'] ) ? (string) $form['newsman_lastname_field'] : '',
			'newsman_phone_field'     => isset( $form['newsman_phone_field'] ) ? (string) $form['newsman_phone_field'] : '',
			'newsman_send_fields'     => isset( $form['newsman_send_fields'] ) && is_array( $form['newsman_send_fields'] )
				? $form['newsman_send_fields']
				: array(),
		);
	}

	/**
	 * Build the field-id - metadata map shown in the panel.
	 *
	 * Compound fields (Name, Address) are flattened into their sub-inputs so the
	 * dotted ids match `wp_gf_entry_meta.meta_key`. Non-data types are excluded.
	 *
	 * @param array $form Gravity Forms form array.
	 * @return array<string,array{id:string,type:string,label:string}>
	 */
	public static function scan_field_choices( $form ) {
		$choices = array();
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $choices;
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! is_array( $field ) && ! is_object( $field ) ) {
				continue;
			}
			$field = (array) $field;
			$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
			$id    = isset( $field['id'] ) ? (string) $field['id'] : '';
			if ( '' === $id || in_array( $type, self::NON_DATA_TYPES, true ) ) {
				continue;
			}

			$label   = isset( $field['label'] ) ? (string) $field['label'] : '';
			$inputs  = isset( $field['inputs'] ) && is_array( $field['inputs'] ) ? $field['inputs'] : array();
			$is_comp = in_array( $type, self::COMPOUND_FIELD_TYPES, true );

			if ( $is_comp && ! empty( $inputs ) ) {
				foreach ( $inputs as $input ) {
					$input = (array) $input;
					if ( ! empty( $input['isHidden'] ) ) {
						continue;
					}
					$sub_id    = isset( $input['id'] ) ? (string) $input['id'] : '';
					$sub_label = isset( $input['label'] ) ? (string) $input['label'] : '';
					if ( '' === $sub_id ) {
						continue;
					}
					$combined = trim( $label . ' — ' . $sub_label, ' —' );
					if ( '' === $combined ) {
						$combined = $sub_id;
					}
					$choices[ $sub_id ] = array(
						'id'    => $sub_id,
						'type'  => $type,
						'label' => $combined,
					);
				}
				continue;
			}

			$choices[ $id ] = array(
				'id'    => $id,
				'type'  => $type,
				'label' => $label,
			);
		}

		/**
		 * Filter the list of field choices shown in the Newsman GF panel.
		 *
		 * @param array $choices Each entry: `[ 'id' => string, 'type' => string, 'label' => string ]` keyed by id.
		 * @param array $form    Current form array.
		 */
		$choices = apply_filters( 'newsman_gravity_forms_field_choices', $choices, $form );
		return is_array( $choices ) ? $choices : array();
	}

	/**
	 * Apply the submitted Newsman fields onto the form array (to be saved back).
	 *
	 * @param array $form Form array from GFAPI::get_form.
	 * @return array Updated form array.
	 */
	protected static function apply_post_to_form( $form ) {
		$post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is checked in the caller.

		$form['newsman_enable']          = ! empty( $post['newsman_enable'] ) ? '1' : '';
		$form['newsman_newsletter_form'] = ! empty( $post['newsman_newsletter_form'] ) ? '1' : '';
		$form['newsman_list_id']         = isset( $post['newsman_list_id'] ) ? sanitize_text_field( wp_unslash( $post['newsman_list_id'] ) ) : '';
		$form['newsman_segment_id']      = isset( $post['newsman_segment_id'] ) ? sanitize_text_field( wp_unslash( $post['newsman_segment_id'] ) ) : '';
		$optin                           = isset( $post['newsman_optin_mode'] ) ? sanitize_text_field( wp_unslash( $post['newsman_optin_mode'] ) ) : 'single';
		$form['newsman_optin_mode']      = ( 'double' === $optin ) ? 'double' : 'single';
		$form['newsman_email_field']     = isset( $post['newsman_email_field'] ) ? sanitize_text_field( wp_unslash( $post['newsman_email_field'] ) ) : '';
		$form['newsman_firstname_field'] = isset( $post['newsman_firstname_field'] ) ? sanitize_text_field( wp_unslash( $post['newsman_firstname_field'] ) ) : '';
		$form['newsman_lastname_field']  = isset( $post['newsman_lastname_field'] ) ? sanitize_text_field( wp_unslash( $post['newsman_lastname_field'] ) ) : '';
		$form['newsman_phone_field']     = isset( $post['newsman_phone_field'] ) ? sanitize_text_field( wp_unslash( $post['newsman_phone_field'] ) ) : '';

		$send_fields = array();
		if ( isset( $post['newsman_send_fields'] ) && is_array( $post['newsman_send_fields'] ) ) {
			foreach ( $post['newsman_send_fields'] as $field_id => $val ) {
				if ( ! empty( $val ) ) {
					$send_fields[ sanitize_text_field( wp_unslash( (string) $field_id ) ) ] = '1';
				}
			}
		}
		$form['newsman_send_fields'] = $send_fields;

		return $form;
	}

	/**
	 * Render a "label (type)" string for the field choice.
	 *
	 * @param array $choice Field choice.
	 * @return string
	 */
	protected static function format_choice_label( $choice ) {
		$label = isset( $choice['label'] ) ? trim( (string) $choice['label'] ) : '';
		$type  = isset( $choice['type'] ) ? (string) $choice['type'] : '';
		$id    = isset( $choice['id'] ) ? (string) $choice['id'] : '';
		if ( '' === $label ) {
			$label = sprintf(
				/* translators: %s: Gravity Forms field id. */
				esc_html__( 'Field #%s', 'newsman' ),
				$id
			);
		}
		return '' === $type ? sprintf( '%1$s (#%2$s)', $label, $id ) : sprintf( '%1$s (#%2$s, %3$s)', $label, $id, $type );
	}
}
