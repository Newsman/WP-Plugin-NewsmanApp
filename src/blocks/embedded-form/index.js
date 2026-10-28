/**
 * NewsMAN Embedded Form block - editor script.
 *
 * Plain script without a build step: uses the wp.* globals listed in index.asset.php.
 * The front end is rendered in PHP by \Newsman\Form\Block\EmbeddedForm::render(),
 * the same as the [newsman_subscribe_widget formid="..."] shortcode.
 *
 * @package NewsmanApp for WordPress
 */

( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var blockEditor = wp.blockEditor;
	var components = wp.components;

	var instructions = __(
		'In your NewsMAN account: select the list, then Settings > Subscription forms > Create/Edit form > Embedded form. Copy the form ID from the shortcode.',
		'newsman'
	);

	function formIdControl( props ) {
		return el( components.TextControl, {
			label: __( 'Form ID', 'newsman' ),
			value: props.attributes.formId,
			onChange: function ( value ) {
				props.setAttributes( { formId: value.trim() } );
			},
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
		} );
	}

	wp.blocks.registerBlockType( 'newsman/embedded-form', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps();
			var formId = props.attributes.formId || '';

			var inspector = el(
				blockEditor.InspectorControls,
				null,
				el( components.PanelBody, { title: __( 'NewsMAN form', 'newsman' ) }, formIdControl( props ), el( 'p', { className: 'components-base-control__help' }, instructions ) )
			);

			var body;
			if ( formId === '' ) {
				body = el(
					components.Placeholder,
					{
						icon: 'feedback',
						label: __( 'NewsMAN Embedded Form', 'newsman' ),
						instructions: instructions,
					},
					formIdControl( props )
				);
			} else {
				body = el(
					components.Placeholder,
					{
						icon: 'feedback',
						label: __( 'NewsMAN Embedded Form', 'newsman' ),
						instructions: __( 'The form is loaded from NewsMAN and shown on the published page.', 'newsman' ),
					},
					el( 'code', null, formId )
				);
			}

			return el( Fragment, null, inspector, el( 'div', blockProps, body ) );
		},
		save: function () {
			return null;
		},
		transforms: {
			from: [
				{
					type: 'shortcode',
					tag: 'newsman_subscribe_widget',
					attributes: {
						formId: {
							type: 'string',
							shortcode: function ( attributes ) {
								return ( attributes.named && attributes.named.formid ) || '';
							},
						},
					},
				},
				{
					type: 'block',
					blocks: [ 'core/shortcode' ],
					isMatch: function ( attributes ) {
						return /^\s*\[newsman_subscribe_widget\s[^\]]*formid=/.test( attributes.text || '' );
					},
					transform: function ( attributes ) {
						var match = /formid=["']?([^"'\s\]]+)/.exec( attributes.text || '' );
						return wp.blocks.createBlock( 'newsman/embedded-form', { formId: match ? match[ 1 ] : '' } );
					},
				},
			],
		},
	} );
} )( window.wp );
