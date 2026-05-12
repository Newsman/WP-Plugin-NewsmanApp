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

namespace Newsman\Elementor;

use Newsman\Subscribe\Lists;
use Newsman\Subscribe\Segments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor controls injector for the Newsman Elementor Forms integration.
 *
 * @class \Newsman\Elementor\FormControls
 */
class FormControls {
	/**
	 * Add a top-level "Newsman" section to the Form widget Content tab.
	 *
	 * Hooked on `elementor/element/form/section_form_fields/after_section_end`.
	 *
	 * @param \Elementor\Controls_Stack $element Elementor element being rendered.
	 * @param array                     $args    Section args (unused).
	 * @return void
	 */
	public function add_newsman_section( $element, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) ) {
			return;
		}

		/**
		 * Filter the slug used to register the Newsman section on the Form widget.
		 *
		 * @param string $slug    Default section slug.
		 * @param object $element Elementor element being rendered.
		 */
		$section_id = (string) apply_filters( 'newsman_form_section_id', 'section_newsman', $element );

		/**
		 * Filter the args passed to `start_controls_section()` for the Newsman section.
		 *
		 * @param array  $args    Section args (`label`, `tab`, ...).
		 * @param object $element Elementor element.
		 */
		$section_args = apply_filters(
			'newsman_form_section_args',
			array(
				'label' => esc_html__( 'Newsman', 'newsman' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			),
			$element
		);
		if ( ! is_array( $section_args ) ) {
			$section_args = array(
				'label' => esc_html__( 'Newsman', 'newsman' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			);
		}

		$element->start_controls_section( $section_id, $section_args );

		/**
		 * Filter the args passed to `add_control()` for the form-level `newsman_enable` switcher.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Elementor element.
		 */
		$enable_args = apply_filters(
			'newsman_form_enable_control_args',
			array(
				'label'        => esc_html__( 'Send to Newsman', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'newsman' ),
				'label_off'    => esc_html__( 'Off', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'When enabled, form submissions will subscribe the email field to the selected Newsman list with the marked fields as subscriber properties.', 'newsman' ),
			),
			$element
		);
		if ( is_array( $enable_args ) ) {
			$element->add_control( 'newsman_enable', $enable_args );
		}

		/**
		 * Filter the args passed to `add_control()` for the form-level `newsman_newsletter_form` switcher.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Elementor element.
		 */
		$newsletter_args = apply_filters(
			'newsman_form_newsletter_form_control_args',
			array(
				'label'        => esc_html__( 'Newsletter form', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'newsman' ),
				'label_off'    => esc_html__( 'Off', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'When enabled, submissions are routed to the Newsman list and segment configured in Newsman - Sync (the per-form List and Segment controls below are hidden because they no longer apply).', 'newsman' ),
			),
			$element
		);
		if ( is_array( $newsletter_args ) ) {
			$element->add_control( 'newsman_newsletter_form', $newsletter_args );
		}

		/**
		 * Filter the args passed to `add_control()` for the form-level `newsman_list_id` SELECT2.
		 *
		 * @param array  $args    Control args (note: `options` is already populated).
		 * @param object $element Elementor element.
		 */
		$list_args = apply_filters(
			'newsman_form_list_control_args',
			array(
				'label'       => esc_html__( 'Newsman List', 'newsman' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $this->get_lists_for_select(),
				'label_block' => true,
				'multiple'    => false,
				'conditions'  => array(
					'relation' => 'and',
					'terms'    => array(
						array(
							'name'     => 'newsman_enable',
							'operator' => '==',
							'value'    => 'yes',
						),
						array(
							'name'     => 'newsman_newsletter_form',
							'operator' => '!=',
							'value'    => 'yes',
						),
					),
				),
				'description' => esc_html__( 'List for this campaign-specific form. Hidden when "Newsletter form" is on (the Sync section list is used instead).', 'newsman' ) . ' ' . esc_html__( 'Do not pick the same list as the one configured in Newsman - Sync unless you intend submissions to land in your global newsletter list. To send to that list, turn on "Newsletter form" instead.', 'newsman' ),
			),
			$element
		);
		if ( is_array( $list_args ) ) {
			$element->add_control( 'newsman_list_id', $list_args );
		}

		// Render every segment as an option up-front; the editor-footer JS hooked on
		// `elementor/editor/footer` rebuilds the visible options whenever
		// `newsman_list_id` changes so the admin only sees segments belonging to the
		// selected list.
		$segments_by_list = Segments::get_by_list( get_current_blog_id() );
		$segment_options  = array( '' => esc_html__( '— none —', 'newsman' ) );
		foreach ( $segments_by_list as $segments_for_list ) {
			foreach ( $segments_for_list as $segment_id => $segment_name ) {
				$segment_options[ (string) $segment_id ] = (string) $segment_name;
			}
		}

		$segment_args = apply_filters(
			'newsman_form_segment_control_args',
			array(
				'label'       => esc_html__( 'Newsman Segment', 'newsman' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $segment_options,
				'label_block' => true,
				'multiple'    => false,
				'default'     => '',
				'conditions'  => array(
					'relation' => 'and',
					'terms'    => array(
						array(
							'name'     => 'newsman_enable',
							'operator' => '==',
							'value'    => 'yes',
						),
						array(
							'name'     => 'newsman_newsletter_form',
							'operator' => '!=',
							'value'    => 'yes',
						),
					),
				),
				'description' => esc_html__( 'Optional. Only segments belonging to the list selected above are shown. Changing the list clears any segment that no longer matches.', 'newsman' ),
			),
			$element
		);
		if ( is_array( $segment_args ) ) {
			$element->add_control( 'newsman_segment_id', $segment_args );
		}

		/**
		 * Filter the args passed to `add_control()` for the form-level `newsman_optin_mode` SELECT.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Elementor element.
		 */
		$optin_args = apply_filters(
			'newsman_form_optin_mode_control_args',
			array(
				'label'       => esc_html__( 'Opt-in mode', 'newsman' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array(
					'single' => esc_html__( 'Single opt-in', 'newsman' ),
					'double' => esc_html__( 'Double opt-in', 'newsman' ),
				),
				'default'     => 'single',
				'label_block' => true,
				'condition'   => array(
					'newsman_enable' => 'yes',
				),
				'description' => esc_html__( 'Single opt-in subscribes the user immediately. Double opt-in sends a confirmation email; the subscription is only completed once the user clicks the link.', 'newsman' ),
			),
			$element
		);
		if ( is_array( $optin_args ) ) {
			$element->add_control( 'newsman_optin_mode', $optin_args );
		}

		/**
		 * Fires inside the Newsman section between the built-in controls and `end_controls_section()`.
		 *
		 * Use this to append additional form-level Newsman controls (e.g. a property-mapping
		 * field). Call `$element->add_control(...)` inside the callback.
		 *
		 * @param object $element    Elementor element being rendered.
		 * @param string $section_id Section slug used in `start_controls_section()`.
		 */
		do_action( 'newsman_form_section_controls', $element, $section_id );

		$element->end_controls_section();

		/**
		 * Fires after the Newsman section has been closed.
		 *
		 * @param object $element    Elementor element.
		 * @param string $section_id Section slug used in `start_controls_section()`.
		 */
		do_action( 'newsman_form_section_after', $element, $section_id );
	}

	/**
	 * Inject per-field controls into the Form widget's `form_fields` repeater.
	 *
	 * Hooked on `elementor/element/form/section_form_fields/before_section_end`.
	 * Adds two switchers to each field: "Send to Newsman" and "Is email field".
	 *
	 * @param \Elementor\Controls_Stack $element Form widget element.
	 * @param array                     $args    Section args (unused).
	 * @return void
	 */
	public function inject_field_controls( $element, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\Repeater' ) ) {
			return;
		}

		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		$control_data     = $controls_manager->get_control_from_stack( $element->get_name(), 'form_fields' );
		if ( is_wp_error( $control_data ) ) {
			return;
		}

		$repeater = new \Elementor\Repeater();

		/**
		 * Filter the args passed to the repeater's `add_control()` for `newsman_send_field`.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Form widget element.
		 */
		$send_args = apply_filters(
			'newsman_form_field_send_control_args',
			array(
				'label'        => esc_html__( 'Send to Newsman', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'newsman' ),
				'label_off'    => esc_html__( 'Off', 'newsman' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( "Push this field's value as a subscriber property to Newsman (using the field's ID as the property key).", 'newsman' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_advanced_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			),
			$element
		);
		if ( is_array( $send_args ) ) {
			$repeater->add_control( 'newsman_send_field', $send_args );
		}

		/**
		 * Filter the args passed to the repeater's `add_control()` for `newsman_is_email`.
		 *
		 * @param array  $args    Control args (note: `conditions.terms` restricts visibility to email/text fields).
		 * @param object $element Form widget element.
		 */
		$is_email_args = apply_filters(
			'newsman_form_field_is_email_control_args',
			array(
				'label'        => esc_html__( 'Use as Newsman email', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'newsman' ),
				'label_off'    => esc_html__( 'No', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Mark this field as the email used to subscribe the user to the Newsman list.', 'newsman' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_advanced_tab',
				'tabs_wrapper' => 'form_fields_tabs',
				'conditions'   => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => 'in',
							'value'    => array( 'email', 'text' ),
						),
					),
				),
			),
			$element
		);
		if ( is_array( $is_email_args ) ) {
			$repeater->add_control( 'newsman_is_email', $is_email_args );
		}

		/**
		 * Filter the args passed to the repeater's `add_control()` for `newsman_is_firstname`.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Form widget element.
		 */
		$is_firstname_args = apply_filters(
			'newsman_form_field_is_firstname_control_args',
			array(
				'label'        => esc_html__( 'Use as Newsman firstname', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'newsman' ),
				'label_off'    => esc_html__( 'No', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Mark this field as the subscriber\'s firstname. The value is sent via context instead of being included in the subscriber properties.', 'newsman' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_advanced_tab',
				'tabs_wrapper' => 'form_fields_tabs',
				'conditions'   => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => 'in',
							'value'    => array( 'text', 'email' ),
						),
					),
				),
			),
			$element
		);
		if ( is_array( $is_firstname_args ) ) {
			$repeater->add_control( 'newsman_is_firstname', $is_firstname_args );
		}

		/**
		 * Filter the args passed to the repeater's `add_control()` for `newsman_is_lastname`.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Form widget element.
		 */
		$is_lastname_args = apply_filters(
			'newsman_form_field_is_lastname_control_args',
			array(
				'label'        => esc_html__( 'Use as Newsman lastname', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'newsman' ),
				'label_off'    => esc_html__( 'No', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Mark this field as the subscriber\'s lastname. The value is sent via context instead of being included in the subscriber properties.', 'newsman' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_advanced_tab',
				'tabs_wrapper' => 'form_fields_tabs',
				'conditions'   => array(
					'terms' => array(
						array(
							'name'     => 'field_type',
							'operator' => 'in',
							'value'    => array( 'text', 'email' ),
						),
					),
				),
			),
			$element
		);
		if ( is_array( $is_lastname_args ) ) {
			$repeater->add_control( 'newsman_is_lastname', $is_lastname_args );
		}

		/**
		 * Filter the args passed to the repeater's `add_control()` for `newsman_is_phone`.
		 *
		 * @param array  $args    Control args.
		 * @param object $element Form widget element.
		 */
		$is_phone_args = apply_filters(
			'newsman_form_field_is_phone_control_args',
			array(
				'label'        => esc_html__( 'Use as Newsman phone', 'newsman' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'newsman' ),
				'label_off'    => esc_html__( 'No', 'newsman' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Mark this field as the subscriber\'s phone. The value is pushed to Newsman under the `phone` property key.', 'newsman' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_advanced_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			),
			$element
		);
		if ( is_array( $is_phone_args ) ) {
			$repeater->add_control( 'newsman_is_phone', $is_phone_args );
		}

		/**
		 * Fires after Newsman's built-in repeater controls have been added.
		 *
		 * Use this to append further per-field Newsman controls (e.g. a custom property
		 * key override). Call `$repeater->add_control(...)` inside the callback.
		 *
		 * @param \Elementor\Repeater $repeater Form-fields repeater being built up.
		 * @param object              $element  Form widget element.
		 */
		do_action( 'newsman_form_field_repeater_controls', $repeater, $element );

		$new_controls = $repeater->get_controls();

		/**
		 * Filter the per-field controls that will be spliced into the `form_fields` repeater.
		 *
		 * Keyed by control name. Remove or add entries here.
		 *
		 * @param array  $new_controls Per-field controls to splice in.
		 * @param object $element      Form widget element.
		 * @param array  $control_data Existing `form_fields` control data.
		 */
		$new_controls = apply_filters( 'newsman_form_field_new_controls', $new_controls, $element, $control_data );
		if ( ! is_array( $new_controls ) ) {
			$new_controls = array();
		}

		/**
		 * Filter the name of the existing repeater field after which the Newsman controls
		 * are spliced in. Defaults to `custom_id`.
		 *
		 * @param string $anchor  Field name to splice after.
		 * @param object $element Form widget element.
		 */
		$anchor = (string) apply_filters( 'newsman_form_field_splice_anchor', 'custom_id', $element );

		// Splice the new controls into the existing repeater fields, after the anchor.
		$new_order = array();
		foreach ( $control_data['fields'] as $key => $field ) {
			$new_order[ $key ] = $field;
			if ( isset( $field['name'] ) && $anchor === $field['name'] ) {
				foreach ( $new_controls as $control_name => $control_def ) {
					$new_order[ $control_name ] = $control_def;
				}
			}
		}

		/**
		 * Filter the final ordered `form_fields` array after Newsman's controls have been spliced in.
		 *
		 * @param array  $new_order    Final ordered repeater fields.
		 * @param array  $new_controls Newsman controls that were spliced in.
		 * @param object $element      Form widget element.
		 */
		$new_order = apply_filters( 'newsman_form_field_order', $new_order, $new_controls, $element );
		if ( ! is_array( $new_order ) ) {
			$new_order = $control_data['fields'];
		}

		$control_data['fields'] = $new_order;
		$element->update_control( 'form_fields', $control_data );

		/**
		 * Fires after the Form widget's `form_fields` repeater has been updated with Newsman controls.
		 *
		 * @param object $element      Form widget element.
		 * @param array  $control_data Updated control data (with `fields` already containing Newsman entries).
		 * @param array  $new_controls Newsman controls that were spliced in.
		 */
		do_action( 'newsman_form_field_controls_injected', $element, $control_data, $new_controls );
	}

	/**
	 * Build the Newsman list dropdown options as `[ id => name ]`.
	 *
	 * Thin pass-through to the shared `\Newsman\Subscribe\Lists::get_for_select()` helper
	 * so all form-source integrations share the same transient cache and filter surface.
	 *
	 * @return array
	 */
	public function get_lists_for_select() {
		return Lists::get_for_select( get_current_blog_id() );
	}

	/**
	 * Print the editor-side JS that filters the `newsman_segment_id` SELECT2 options
	 * down to the segments belonging to the currently selected `newsman_list_id`.
	 *
	 * Elementor controls don't natively support dynamic option filtering driven by
	 * another control's value, so the entire segment catalog is rendered server-side
	 * and this JS rebuilds the visible options on every list change. Hooked on
	 * `elementor/editor/footer` (fires once per editor session, after the panel
	 * scripts are loaded) and runs only when the panel opens a `form` widget.
	 *
	 * @return void
	 */
	public function print_editor_segment_filter_script() {
		$segments_by_list = Segments::get_by_list( get_current_blog_id() );
		// Always include the "none" entry so the dropdown is never empty.
		$none_label = esc_html__( '— none —', 'newsman' );
		?>
		<script>
		(function ($) {
			var newsmanSegmentsByList = <?php echo wp_json_encode( $segments_by_list ); ?>;
			var newsmanNoneLabel      = <?php echo wp_json_encode( $none_label ); ?>;

			function getSegmentControlEls(panel) {
				if ( ! panel || ! panel.$el ) { return null; }
				var $row = panel.$el.find('.elementor-control-newsman_segment_id');
				if ( ! $row.length ) { return null; }
				return {
					row: $row,
					select: $row.find('select')
				};
			}

			function rebuildSegmentOptions(panel, settingsModel) {
				var els = getSegmentControlEls(panel);
				if ( ! els || ! els.select.length ) { return; }

				var listId        = String(settingsModel.get('newsman_list_id') || '');
				var savedSegment  = String(settingsModel.get('newsman_segment_id') || '');
				var segsForList   = (newsmanSegmentsByList && newsmanSegmentsByList[listId]) || {};
				var selectEl      = els.select[0];
				var stillVisible  = false;

				// Tear down SELECT2 first so we can rebuild the underlying <select> cleanly.
				if ( els.select.data('select2') ) {
					try { els.select.select2('destroy'); } catch ( e ) {}
				}

				// Empty + repopulate with "none" + the segments scoped to the selected list.
				selectEl.innerHTML = '';
				selectEl.appendChild(new Option(newsmanNoneLabel, '', false, '' === savedSegment));
				Object.keys(segsForList).forEach(function (segId) {
					var name     = String(segsForList[segId]);
					var selected = (savedSegment === String(segId));
					selectEl.appendChild(new Option(name, segId, selected, selected));
					if ( selected ) { stillVisible = true; }
				});

				// If the saved segment doesn't belong to the new list, drop it from the model.
				// Server-side validation (Segments::belongs_to_list in the processor) would catch
				// this at submit time too; clearing it here keeps the UI honest.
				if ( ! stillVisible && '' !== savedSegment ) {
					settingsModel.set('newsman_segment_id', '', { silent: false });
					selectEl.value = '';
				}

				// Re-init SELECT2 with Elementor's defaults; the editor's own logic re-applies it
				// when the panel re-renders, but doing it here avoids a flash of bare <select>.
				if ( $.fn.select2 ) {
					try {
						els.select.select2({
							dir: ( $('body').hasClass('rtl') ? 'rtl' : 'ltr' ),
							width: '100%'
						});
					} catch ( e ) {}
				}
			}

			$(window).on('elementor:init', function () {
				if ( typeof elementor === 'undefined' || ! elementor.hooks ) { return; }

				elementor.hooks.addAction('panel/open_editor/widget/form', function (panel, model) {
					var settings = model.get('settings');
					if ( ! settings ) { return; }

					function run() {
						rebuildSegmentOptions(panel, settings);
					}

					// `panel/open_editor/widget/form` fires before Elementor finishes rendering
					// the form widget's child controls — at that point the segment <select>
					// either doesn't exist yet in the DOM, or exists with the server-rendered
					// options but no SELECT2 wrapper yet. Poll up to ~2 s until both the row
					// and its SELECT2 instance are present, then rebuild. Once successful, the
					// change listeners below keep things in sync.
					var attempt    = 0;
					var maxAttempt = 40;
					var poll       = function () {
						var els = getSegmentControlEls(panel);
						if ( els && els.select.length && els.select.data('select2') ) {
							run();
							return;
						}
						if ( ++attempt < maxAttempt ) {
							setTimeout(poll, 50);
						} else if ( els && els.select.length ) {
							// Final attempt even without SELECT2 — our DOM edit still wins
							// when Elementor inits SELECT2 afterwards (it reads from the
							// underlying <select>).
							run();
						}
					};
					poll();

					// Conditions on `newsman_enable` and `newsman_newsletter_form` make Elementor
					// re-render the segment control when those toggles flip, which restores the
					// server-rendered (unfiltered) option set. Re-apply the filter on any of the
					// settings that affect visibility.
					settings.on(
						'change:newsman_list_id change:newsman_enable change:newsman_newsletter_form',
						run
					);
				});
			});
		})(jQuery);
		</script>
		<?php
	}
}
