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
		$panels['newsman-panel'] = array(
			'title'    => __( 'Newsman', 'newsman' ),
			'callback' => array( $this, 'render' ),
		);
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
		$send_fields_set = array_flip( $prop['send_fields'] );

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
							<?php endif; ?>
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
											$is_email_field = ( $choice['name'] === $prop['email_field'] );
											$default_on     = empty( $prop['send_fields'] )
												? ! $is_email_field
												: isset( $send_fields_set[ $choice['name'] ] );
											?>
											<li>
												<label>
													<input
														type="checkbox"
														name="wpcf7-newsman[send_fields][]"
														value="<?php echo esc_attr( $choice['name'] ); ?>"
														<?php checked( $default_on ); ?>
														<?php disabled( $is_email_field ); ?>
													/>
													<?php echo esc_html( $this->format_choice_label( $choice ) ); ?>
													<?php if ( $is_email_field ) : ?>
														<em>(<?php esc_html_e( 'used as email', 'newsman' ); ?>)</em>
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

		$send_fields_raw = isset( $posted['send_fields'] ) && is_array( $posted['send_fields'] )
			? $posted['send_fields']
			: array();

		$prop = array(
			'enable'      => ! empty( $posted['enable'] ),
			'list_id'     => isset( $posted['list_id'] ) ? sanitize_text_field( (string) $posted['list_id'] ) : '',
			'email_field' => isset( $posted['email_field'] ) ? sanitize_text_field( (string) $posted['email_field'] ) : '',
			'send_fields' => array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $send_fields_raw ) ) ) ),
		);

		$contact_form->set_properties( array( self::PROPERTY => $prop ) );
	}

	/**
	 * Default Newsman property shape.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable'      => false,
			'list_id'     => '',
			'email_field' => '',
			'send_fields' => array(),
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
