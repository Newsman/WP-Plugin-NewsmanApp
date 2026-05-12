<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman Contact Form 7 editor panel.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\ContactForm7;

use Newsman\Subscribe\Lists;
use Newsman\Subscribe\Segments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor panel injector for the Newsman Contact Form 7 integration.
 *
 * Adds a "Newsman" tab to the contact-form editor with: enable checkbox, list dropdown,
 * email-field dropdown (lists every form-tag, not only `[email]`-basetype ones — text /
 * tel / url tags are equally valid as the email holder for forms that don't use the
 * native email field), and a checkbox list of every form-tag for properties.
 *
 * Settings are persisted on the contact form via `WPCF7_ContactForm::set_properties()`
 * under the `newsman` key. CF7's `array_intersect_key` filter inside `set_properties()`
 * silently drops unknown keys, so the property must be pre-registered via the
 * `wpcf7_pre_construct_contact_form_properties` filter (see `register_property()`).
 *
 * @class \Newsman\ContactForm7\FormPanel
 */
class FormPanel {
	/**
	 * Property key under which the Newsman settings are persisted on a contact form.
	 */
	public const PROPERTY = 'newsman';

	/**
	 * Form-tag basetypes that never carry user-entered values; excluded from the
	 * email-field dropdown and the per-field property checkboxes.
	 *
	 * @var string[]
	 */
	protected const NON_DATA_BASETYPES = array(
		'submit',
		'acceptance',
		'recaptcha',
		'captchac',
		'captchar',
		'quiz',
		'file',
	);

	/**
	 * Pre-register the `newsman` property so `set_properties()` persists it.
	 *
	 * @param array  $properties   Existing property defaults.
	 * @param object $contact_form Contact form being constructed.
	 * @return array
	 */
	public function register_property( $properties, $contact_form ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_array( $properties ) ) {
			return $properties;
		}
		if ( ! isset( $properties[ self::PROPERTY ] ) ) {
			$properties[ self::PROPERTY ] = self::defaults();
		}
		return $properties;
	}

	/**
	 * Inject the "Newsman" tab into the CF7 editor panels.
	 *
	 * Hooked on `wpcf7_editor_panels`. The callback receives the current `WPCF7_ContactForm`
	 * via `$formatter->call_user_func()` inside CF7's editor renderer.
	 *
	 * @param array $panels Existing editor panels.
	 * @return array
	 */
	public function add_panel( $panels ) {
		/**
		 * Filter the Newsman editor panel definition before it is appended to the
		 * CF7 editor panels array.
		 *
		 * Return an empty array (or a value that fails `is_array()`) to suppress
		 * the panel entirely; mutate the `title` / `callback` keys to swap labels
		 * or replace the renderer with a fully custom one.
		 *
		 * @param array $panel  Panel definition (`title`, `callback`).
		 * @param array $panels Existing panels keyed by slug.
		 */
		$panel = apply_filters(
			'newsman_cf7_editor_panel',
			array(
				'title'    => __( 'Newsman', 'newsman' ),
				'callback' => array( $this, 'render' ),
			),
			$panels
		);

		if ( ! is_array( $panel ) || empty( $panel ) ) {
			return $panels;
		}

		/**
		 * Filter the slug under which the Newsman editor panel is registered.
		 *
		 * @param string $slug   Default panel slug.
		 * @param array  $panel  Panel definition.
		 * @param array  $panels Existing panels.
		 */
		$slug = apply_filters( 'newsman_cf7_editor_panel_slug', 'newsman-panel', $panel, $panels );

		$panels[ (string) $slug ] = $panel;
		return $panels;
	}

	/**
	 * Render the Newsman editor tab for a single contact form.
	 *
	 * @param object $contact_form Current `WPCF7_ContactForm` instance.
	 * @return void
	 */
	public function render( $contact_form ) {
		$prop    = self::resolve_prop( $contact_form );
		$choices = $this->scan_field_choices( $contact_form );
		$lists   = Lists::get_for_select( get_current_blog_id() );

		// Default selection on first render: first scanned email-basetype tag, else first tag.
		if ( '' === $prop['email_field'] && ! empty( $choices ) ) {
			$prop['email_field'] = self::pick_default_email_field( $choices );
		}

		// Default send_fields: every choice except the resolved email field.
		if ( ! is_array( $prop['send_fields'] ) ) {
			$prop['send_fields'] = array();
		}

		/**
		 * Filter the resolved Newsman property array used to render the panel.
		 *
		 * @param array  $prop         Resolved property: `enable`, `list_id`, `email_field`, `send_fields`.
		 * @param object $contact_form Current contact form.
		 * @param array  $choices      Field choices scanned from the form template.
		 * @param array  $lists        Newsman list dropdown options.
		 */
		$prop = apply_filters( 'newsman_cf7_panel_prop', $prop, $contact_form, $choices, $lists );
		if ( ! is_array( $prop ) ) {
			$prop = self::defaults();
		}

		/**
		 * Filter the Newsman list dropdown options shown in the panel.
		 *
		 * @param array  $lists        Lists keyed by id.
		 * @param object $contact_form Current contact form.
		 * @param array  $prop         Resolved property.
		 * @param array  $choices      Field choices.
		 */
		$lists = apply_filters( 'newsman_cf7_panel_lists', $lists, $contact_form, $prop, $choices );

		$send_fields_set = array_flip( is_array( $prop['send_fields'] ) ? $prop['send_fields'] : array() );

		/**
		 * Fires before the Newsman CF7 editor panel renders.
		 *
		 * Third-party code may echo a fully custom panel here, then short-circuit the
		 * default markup via the `newsman_cf7_panel_skip_default` filter (return true).
		 * Do NOT collect HTML — echo directly inside the callback.
		 *
		 * @param object $contact_form Current contact form.
		 * @param array  $prop         Resolved Newsman property.
		 * @param array  $choices      Field choices.
		 * @param array  $lists        Newsman list dropdown options.
		 */
		do_action( 'newsman_cf7_panel_render', $contact_form, $prop, $choices, $lists );

		/**
		 * Suppress the default panel HTML.
		 *
		 * Hook in alongside `newsman_cf7_panel_render` and return true to skip the built-in
		 * markup once you've echoed your own.
		 *
		 * @param bool   $skip         Default false.
		 * @param object $contact_form Current contact form.
		 * @param array  $prop         Resolved Newsman property.
		 * @param array  $choices      Field choices.
		 * @param array  $lists        Newsman list dropdown options.
		 */
		if ( apply_filters( 'newsman_cf7_panel_skip_default', false, $contact_form, $prop, $choices, $lists ) ) {
			return;
		}

		?>
		<h2><?php esc_html_e( 'Newsman', 'newsman' ); ?></h2>
		<fieldset>
			<legend>
				<?php esc_html_e( 'Subscribe form submissions to a Newsman list. The selected email field is used as the subscriber email; the chosen properties are pushed as Newsman subscriber properties.', 'newsman' ); ?>
			</legend>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="wpcf7-newsman-enable"><?php esc_html_e( 'Send to Newsman', 'newsman' ); ?></label>
						</th>
						<td>
							<input
								type="checkbox"
								name="wpcf7-newsman[enable]"
								id="wpcf7-newsman-enable"
								value="1"
								<?php checked( ! empty( $prop['enable'] ) ); ?>
							/>
							<span class="description">
								<?php esc_html_e( 'When enabled, every successful submission of this form is subscribed to the selected Newsman list.', 'newsman' ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpcf7-newsman-newsletter-form"><?php esc_html_e( 'Newsletter form', 'newsman' ); ?></label>
						</th>
						<td>
							<input
								type="checkbox"
								name="wpcf7-newsman[newsletter_form]"
								id="wpcf7-newsman-newsletter-form"
								value="1"
								<?php checked( ! empty( $prop['newsletter_form'] ) ); ?>
							/>
							<span class="description">
								<?php esc_html_e( 'When enabled, submissions are routed to the Newsman list and segment configured in Newsman - Sync (the per-form Newsman list and Segment rows below are hidden because they no longer apply).', 'newsman' ); ?>
							</span>
						</td>
					</tr>
					<tr class="newsman-list-row">
						<th scope="row">
							<label for="wpcf7-newsman-list-id"><?php esc_html_e( 'Newsman list', 'newsman' ); ?></label>
						</th>
						<td>
							<?php if ( empty( $lists ) ) : ?>
								<em>
									<?php esc_html_e( 'No Newsman lists are available. Configure your Newsman API key on the Newsman settings page first.', 'newsman' ); ?>
								</em>
							<?php else : ?>
								<select name="wpcf7-newsman[list_id]" id="wpcf7-newsman-list-id">
									<option value=""><?php esc_html_e( '— select a list —', 'newsman' ); ?></option>
									<?php foreach ( $lists as $list_id => $list_name ) : ?>
										<option value="<?php echo esc_attr( (string) $list_id ); ?>" <?php selected( (string) $prop['list_id'], (string) $list_id ); ?>>
											<?php echo esc_html( (string) $list_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'List for this campaign-specific form. Ignored when "Newsletter form" is on (the configured Sync list is used instead).', 'newsman' ); ?>
									<?php esc_html_e( 'Do not pick the same list as the one configured in Newsman - Sync unless you intend submissions to land in your global newsletter list. To send to that list, turn on "Newsletter form" instead.', 'newsman' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<?php
					// Lazy-load: only fetch segments for the currently-saved list. Switching
					// the list dropdown triggers `wp_ajax_newsman_load_segments` to replace
					// these options. Mitigates Newsman's `segment.all` 10/min rate limit.
					$cf7_current_list_id = isset( $prop['list_id'] ) ? (string) $prop['list_id'] : '';
					$cf7_segments_now    = '' !== $cf7_current_list_id
						? Segments::get_for_list( get_current_blog_id(), $cf7_current_list_id )
						: array();
					?>
					<tr class="newsman-segment-row">
						<th scope="row">
							<label for="wpcf7-newsman-segment-id"><?php esc_html_e( 'Newsman segment', 'newsman' ); ?></label>
						</th>
						<td>
							<select name="wpcf7-newsman[segment_id]" id="wpcf7-newsman-segment-id">
								<option value=""><?php esc_html_e( '— none —', 'newsman' ); ?></option>
								<?php foreach ( $cf7_segments_now as $segment_id => $segment_name ) : ?>
									<option value="<?php echo esc_attr( (string) $segment_id ); ?>" <?php selected( (string) $prop['segment_id'], (string) $segment_id ); ?>>
										<?php echo esc_html( (string) $segment_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Optional. Segments are list-scoped — only segments belonging to the selected list above are shown. Changing the list refreshes this dropdown.', 'newsman' ); ?>
							</p>
						</td>
					</tr>
					<?php
					// CF7's WPCF7_HTMLFormatter kses-filters the panel callback output and
					// strips any inline <script> tags. Defer the script print to admin_footer,
					// which fires after the formatter has emitted the panel HTML.
					if ( ! has_action( 'admin_footer', array( __CLASS__, 'print_panel_script' ) ) ) {
						add_action( 'admin_footer', array( __CLASS__, 'print_panel_script' ) );
					}
					?>
					<tr>
						<th scope="row">
							<label for="wpcf7-newsman-optin-mode"><?php esc_html_e( 'Opt-in mode', 'newsman' ); ?></label>
						</th>
						<td>
							<select name="wpcf7-newsman[optin_mode]" id="wpcf7-newsman-optin-mode">
								<option value="single" <?php selected( (string) $prop['optin_mode'], 'single' ); ?>>
									<?php esc_html_e( 'Single opt-in', 'newsman' ); ?>
								</option>
								<option value="double" <?php selected( (string) $prop['optin_mode'], 'double' ); ?>>
									<?php esc_html_e( 'Double opt-in', 'newsman' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Single opt-in subscribes the user immediately. Double opt-in sends a confirmation email; the subscription is only completed once the user clicks the link.', 'newsman' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpcf7-newsman-email-field"><?php esc_html_e( 'Email field', 'newsman' ); ?></label>
						</th>
						<td>
							<?php if ( empty( $choices ) ) : ?>
								<em>
									<?php esc_html_e( 'Add a field to the form template to enable Newsman.', 'newsman' ); ?>
								</em>
							<?php else : ?>
								<select name="wpcf7-newsman[email_field]" id="wpcf7-newsman-email-field">
									<?php foreach ( $choices as $choice ) : ?>
										<option value="<?php echo esc_attr( $choice['name'] ); ?>" <?php selected( $prop['email_field'], $choice['name'] ); ?>>
											<?php echo esc_html( $this->format_choice_label( $choice ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'The selected field provides the subscriber email. Any field type may be used (text, tel, url, email, …).', 'newsman' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpcf7-newsman-firstname-field"><?php esc_html_e( 'Firstname field', 'newsman' ); ?></label>
						</th>
						<td>
							<?php if ( empty( $choices ) ) : ?>
								<em>
									<?php esc_html_e( 'Add a field to the form template to enable Newsman.', 'newsman' ); ?>
								</em>
							<?php else : ?>
								<select name="wpcf7-newsman[firstname_field]" id="wpcf7-newsman-firstname-field">
									<option value="" <?php selected( $prop['firstname_field'], '' ); ?>>
										<?php esc_html_e( '— none —', 'newsman' ); ?>
									</option>
									<?php foreach ( $choices as $choice ) : ?>
										<option value="<?php echo esc_attr( $choice['name'] ); ?>" <?php selected( $prop['firstname_field'], $choice['name'] ); ?>>
											<?php echo esc_html( $this->format_choice_label( $choice ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Optional. When set, the field value is sent as the subscriber\'s firstname instead of being included in the subscriber properties.', 'newsman' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpcf7-newsman-lastname-field"><?php esc_html_e( 'Lastname field', 'newsman' ); ?></label>
						</th>
						<td>
							<?php if ( empty( $choices ) ) : ?>
								<em>
									<?php esc_html_e( 'Add a field to the form template to enable Newsman.', 'newsman' ); ?>
								</em>
							<?php else : ?>
								<select name="wpcf7-newsman[lastname_field]" id="wpcf7-newsman-lastname-field">
									<option value="" <?php selected( $prop['lastname_field'], '' ); ?>>
										<?php esc_html_e( '— none —', 'newsman' ); ?>
									</option>
									<?php foreach ( $choices as $choice ) : ?>
										<option value="<?php echo esc_attr( $choice['name'] ); ?>" <?php selected( $prop['lastname_field'], $choice['name'] ); ?>>
											<?php echo esc_html( $this->format_choice_label( $choice ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Optional. When set, the field value is sent as the subscriber\'s lastname instead of being included in the subscriber properties.', 'newsman' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpcf7-newsman-phone-field"><?php esc_html_e( 'Phone field', 'newsman' ); ?></label>
						</th>
						<td>
							<?php if ( empty( $choices ) ) : ?>
								<em>
									<?php esc_html_e( 'Add a field to the form template to enable Newsman.', 'newsman' ); ?>
								</em>
							<?php else : ?>
								<select name="wpcf7-newsman[phone_field]" id="wpcf7-newsman-phone-field">
									<option value="" <?php selected( $prop['phone_field'], '' ); ?>>
										<?php esc_html_e( '— none —', 'newsman' ); ?>
									</option>
									<?php foreach ( $choices as $choice ) : ?>
										<option value="<?php echo esc_attr( $choice['name'] ); ?>" <?php selected( $prop['phone_field'], $choice['name'] ); ?>>
											<?php echo esc_html( $this->format_choice_label( $choice ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Optional. When set, the field value is sent as the subscriber\'s phone under the `phone` property key.', 'newsman' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Send as properties', 'newsman' ); ?>
						</th>
						<td>
							<?php if ( empty( $choices ) ) : ?>
								<em>
									<?php esc_html_e( 'Add a field to the form template first.', 'newsman' ); ?>
								</em>
							<?php else : ?>
								<fieldset>
									<legend class="screen-reader-text">
										<?php esc_html_e( 'Send these fields to Newsman as subscriber properties', 'newsman' ); ?>
									</legend>
									<ul style="margin:0;padding:0;list-style:none;">
										<?php foreach ( $choices as $choice ) : ?>
											<?php
											$is_email_field     = ( $choice['name'] === $prop['email_field'] );
											$is_firstname_field = ( '' !== $prop['firstname_field'] && $choice['name'] === $prop['firstname_field'] );
											$is_lastname_field  = ( '' !== $prop['lastname_field'] && $choice['name'] === $prop['lastname_field'] );
											$is_phone_field     = ( '' !== $prop['phone_field'] && $choice['name'] === $prop['phone_field'] );
											$is_reserved        = ( $is_email_field || $is_firstname_field || $is_lastname_field || $is_phone_field );
											$default_on         = empty( $prop['send_fields'] )
												? ! $is_reserved
												: isset( $send_fields_set[ $choice['name'] ] );
											?>
											<li>
												<label>
													<input
														type="checkbox"
														name="wpcf7-newsman[send_fields][]"
														value="<?php echo esc_attr( $choice['name'] ); ?>"
														<?php checked( $default_on ); ?>
														<?php disabled( $is_reserved ); ?>
													/>
													<?php echo esc_html( $this->format_choice_label( $choice ) ); ?>
													<?php if ( $is_email_field ) : ?>
														<em>(<?php esc_html_e( 'used as email', 'newsman' ); ?>)</em>
													<?php elseif ( $is_firstname_field ) : ?>
														<em>(<?php esc_html_e( 'used as firstname', 'newsman' ); ?>)</em>
													<?php elseif ( $is_lastname_field ) : ?>
														<em>(<?php esc_html_e( 'used as lastname', 'newsman' ); ?>)</em>
													<?php elseif ( $is_phone_field ) : ?>
														<em>(<?php esc_html_e( 'used as phone', 'newsman' ); ?>)</em>
													<?php endif; ?>
												</label>
											</li>
										<?php endforeach; ?>
									</ul>
									<p class="description">
										<?php esc_html_e( 'Each checked field is sent to Newsman as a subscriber property keyed by its form-tag name.', 'newsman' ); ?>
									</p>
								</fieldset>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>
		<?php

		/**
		 * Fires after the Newsman CF7 editor panel has rendered its default HTML.
		 *
		 * Use this to append additional rows or notices below the standard panel
		 * without replacing it. Echo directly inside the callback.
		 *
		 * @param object $contact_form Current contact form.
		 * @param array  $prop         Resolved Newsman property.
		 * @param array  $choices      Field choices.
		 * @param array  $lists        Newsman list dropdown options.
		 */
		do_action( 'newsman_cf7_panel_after_render', $contact_form, $prop, $choices, $lists );
	}

	/**
	 * Persist the Newsman settings when CF7 saves the contact form.
	 *
	 * @param object $contact_form Contact form being saved.
	 * @param array  $args         Save args (unused).
	 * @param string $context      Save context (unused).
	 * @return void
	 */
	public function save( $contact_form, $args, $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'set_properties' ) ) {
			return;
		}

		$posted = wpcf7_superglobal_post( 'wpcf7-newsman', array() );
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}

		/**
		 * Filter the raw posted Newsman panel payload before sanitization.
		 *
		 * @param array  $posted       Raw `wpcf7-newsman` POST array.
		 * @param object $contact_form Contact form being saved.
		 * @param array  $args         CF7 save args.
		 * @param string $context      CF7 save context.
		 */
		$posted = apply_filters( 'newsman_cf7_panel_posted', $posted, $contact_form, $args, $context );
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}

		$send_fields_raw = isset( $posted['send_fields'] ) && is_array( $posted['send_fields'] )
			? $posted['send_fields']
			: array();

		$optin_mode = isset( $posted['optin_mode'] ) ? sanitize_text_field( (string) $posted['optin_mode'] ) : 'single';
		if ( 'double' !== $optin_mode ) {
			$optin_mode = 'single';
		}

		$list_id    = isset( $posted['list_id'] ) ? sanitize_text_field( (string) $posted['list_id'] ) : '';
		$segment_id = isset( $posted['segment_id'] ) ? sanitize_text_field( (string) $posted['segment_id'] ) : '';
		// Drop a stale segment_id whose list no longer matches (admin switched lists
		// in the editor without re-picking a segment, or the segment was removed).
		if ( '' !== $segment_id && ! Segments::belongs_to_list( get_current_blog_id(), $list_id, $segment_id ) ) {
			$segment_id = '';
		}

		$prop = array(
			'enable'          => ! empty( $posted['enable'] ),
			'newsletter_form' => ! empty( $posted['newsletter_form'] ),
			'list_id'         => $list_id,
			'segment_id'      => $segment_id,
			'optin_mode'      => $optin_mode,
			'email_field'     => isset( $posted['email_field'] ) ? sanitize_text_field( (string) $posted['email_field'] ) : '',
			'firstname_field' => isset( $posted['firstname_field'] ) ? sanitize_text_field( (string) $posted['firstname_field'] ) : '',
			'lastname_field'  => isset( $posted['lastname_field'] ) ? sanitize_text_field( (string) $posted['lastname_field'] ) : '',
			'phone_field'     => isset( $posted['phone_field'] ) ? sanitize_text_field( (string) $posted['phone_field'] ) : '',
			'send_fields'     => array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $send_fields_raw ) ) ) ),
		);

		/**
		 * Filter the sanitized Newsman property right before it is persisted on the contact form.
		 *
		 * @param array  $prop         Sanitized property: `enable`, `list_id`, `email_field`, `send_fields`.
		 * @param object $contact_form Contact form being saved.
		 * @param array  $posted       Raw posted payload.
		 * @param array  $args         CF7 save args.
		 * @param string $context      CF7 save context.
		 */
		$prop = apply_filters( 'newsman_cf7_panel_save_prop', $prop, $contact_form, $posted, $args, $context );
		if ( ! is_array( $prop ) ) {
			$prop = self::defaults();
		}

		/**
		 * Allow short-circuiting the persistence step entirely. Return true to skip
		 * `set_properties()` (e.g. when a 3rd party stores Newsman settings elsewhere).
		 *
		 * @param bool   $skip         Default false.
		 * @param array  $prop         Sanitized property.
		 * @param object $contact_form Contact form being saved.
		 * @param array  $posted       Raw posted payload.
		 */
		if ( apply_filters( 'newsman_cf7_panel_skip_save', false, $prop, $contact_form, $posted ) ) {
			return;
		}

		$contact_form->set_properties( array( self::PROPERTY => $prop ) );

		/**
		 * Fires after the Newsman property has been persisted on the contact form.
		 *
		 * @param object $contact_form Contact form that was saved.
		 * @param array  $prop         Persisted property.
		 * @param array  $posted       Raw posted payload.
		 * @param array  $args         CF7 save args.
		 * @param string $context      CF7 save context.
		 */
		do_action( 'newsman_cf7_panel_saved', $contact_form, $prop, $posted, $args, $context );
	}

	/**
	 * Print the panel's filtering JS to admin_footer.
	 *
	 * CF7's `WPCF7_HTMLFormatter` runs every panel callback's output through a kses-style
	 * filter that strips `<script>` tags — so inline JS inside `render()` never reaches
	 * the page. Hooking on `admin_footer` from within `render()` sidesteps the formatter
	 * entirely and lets the DOM elements created by the panel still be present when this
	 * runs.
	 *
	 * @return void
	 */
	public static function print_panel_script() {
		$ajax_url   = esc_url_raw( admin_url( 'admin-ajax.php' ) );
		$ajax_nonce = \Newsman\Admin\Ajax\Segments_Endpoint::create_nonce();
		$none_label = esc_html__( '— none —', 'newsman' );
		?>
		<script>
		(function () {
			var ajaxUrl   = <?php echo wp_json_encode( $ajax_url ); ?>;
			var ajaxNonce = <?php echo wp_json_encode( $ajax_nonce ); ?>;
			var noneLabel = <?php echo wp_json_encode( $none_label ); ?>;

			function init() {
				var listEl    = document.getElementById('wpcf7-newsman-list-id');
				var segEl     = document.getElementById('wpcf7-newsman-segment-id');
				var newsEl    = document.getElementById('wpcf7-newsman-newsletter-form');
				var listRow   = document.querySelector('.newsman-list-row');
				var segRow    = document.querySelector('.newsman-segment-row');
				if ( ! segRow ) { return; }

				// Saved segment id at panel load — used to re-select it if it
				// still belongs to the list after an AJAX refresh.
				var savedSegment = segEl ? String(segEl.value || '') : '';

				function reloadSegments() {
					if ( ! segEl || ! listEl ) { return; }
					var currentListId = String(listEl.value || '');
					if ( '' === currentListId ) {
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
						if ( ! resp || ! resp.success ) { return; }
						var segments = (resp.data && resp.data.segments) || {};
						var prevSelected = String(segEl.value || '') || savedSegment;
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
						if ( segments.hasOwnProperty(prevSelected) ) {
							segEl.value = prevSelected;
						} else {
							segEl.value = '';
						}
					})['catch'](function () { segEl.disabled = false; });
				}

				function toggleNewsletterMode() {
					if ( ! newsEl ) { return; }
					var on = newsEl.checked;
					if ( listRow ) { listRow.style.display = on ? 'none' : ''; }
					if ( segRow )  { segRow.style.display  = on ? 'none' : ''; }
				}

				if ( listEl ) { listEl.addEventListener('change', reloadSegments); }
				if ( newsEl ) { newsEl.addEventListener('change', toggleNewsletterMode); }
				toggleNewsletterMode();
			}
			if ( document.readyState === 'loading' ) {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
		})();
		</script>
		<?php
	}

	/**
	 * Default Newsman property shape.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable'          => false,
			'newsletter_form' => false,
			'list_id'         => '',
			'segment_id'      => '',
			'optin_mode'      => 'single',
			'email_field'     => '',
			'firstname_field' => '',
			'lastname_field'  => '',
			'phone_field'     => '',
			'send_fields'     => array(),
		);
	}

	/**
	 * Resolve the Newsman property for a contact form, padded with defaults.
	 *
	 * @param object $contact_form Contact form.
	 * @return array
	 */
	public static function resolve_prop( $contact_form ) {
		$current = array();
		if ( is_object( $contact_form ) && method_exists( $contact_form, 'prop' ) ) {
			$current = (array) $contact_form->prop( self::PROPERTY );
		}
		return wp_parse_args( $current, self::defaults() );
	}

	/**
	 * Build the field choices shown in the email dropdown and property checkboxes.
	 *
	 * Excludes non-data tag types (submit, file, captchas, ...) and tags without a name.
	 *
	 * @param object $contact_form Contact form.
	 * @return array<int,array{name:string,type:string,basetype:string}>
	 */
	protected function scan_field_choices( $contact_form ) {
		$choices = array();

		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return $choices;
		}

		$tags = $contact_form->scan_form_tags();
		if ( ! is_array( $tags ) ) {
			return $choices;
		}

		$seen = array();
		foreach ( $tags as $tag ) {
			$name     = isset( $tag->name ) ? (string) $tag->name : '';
			$type     = isset( $tag->type ) ? (string) $tag->type : '';
			$basetype = isset( $tag->basetype ) ? (string) $tag->basetype : '';
			if ( '' === $name || isset( $seen[ $name ] ) ) {
				continue;
			}
			if ( in_array( $basetype, self::NON_DATA_BASETYPES, true ) ) {
				continue;
			}
			$seen[ $name ] = true;
			$choices[]     = array(
				'name'     => $name,
				'type'     => $type,
				'basetype' => $basetype,
			);
		}

		/**
		 * Filter the list of form-tag choices shown in the Newsman editor panel.
		 *
		 * @param array  $choices      Each entry: `[ 'name' => string, 'type' => string, 'basetype' => string ]`.
		 * @param object $contact_form Current contact form.
		 */
		$choices = apply_filters( 'newsman_cf7_field_choices', $choices, $contact_form );

		return is_array( $choices ) ? $choices : array();
	}

	/**
	 * Pick a sensible default email field on first render.
	 *
	 * @param array $choices Field choices.
	 * @return string
	 */
	protected static function pick_default_email_field( $choices ) {
		foreach ( $choices as $choice ) {
			if ( 'email' === $choice['basetype'] ) {
				return $choice['name'];
			}
		}
		return isset( $choices[0]['name'] ) ? $choices[0]['name'] : '';
	}

	/**
	 * Render a "name (basetype)" label for the field choice.
	 *
	 * @param array $choice Field choice.
	 * @return string
	 */
	protected function format_choice_label( $choice ) {
		$name     = isset( $choice['name'] ) ? (string) $choice['name'] : '';
		$basetype = isset( $choice['basetype'] ) ? (string) $choice['basetype'] : '';
		if ( '' === $basetype ) {
			return $name;
		}
		return sprintf( '%1$s (%2$s)', $name, $basetype );
	}
}
