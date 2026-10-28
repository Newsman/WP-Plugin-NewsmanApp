/**
 * NewsMAN Subscribe Form block - editor script.
 *
 * Plain script without a build step: uses the wp.* globals listed in index.asset.php.
 * The front end is rendered in PHP by \Newsman\Form\Block\SubscribeForm::render().
 *
 * @package NewsmanApp for WordPress
 */

( function ( wp, data ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var blockEditor = wp.blockEditor;
	var components = wp.components;

	data = data || {};
	var defaults = data.defaults || {};

	/**
	 * Text of an attribute, or its default when empty.
	 *
	 * @param {Object} attributes Block attributes.
	 * @param {string} key        Attribute name.
	 * @return {string} Text.
	 */
	function text( attributes, key ) {
		var value = attributes[ key ];
		return value && value.trim() !== '' ? value : defaults[ key ] || '';
	}

	/**
	 * Strip HTML tags for the editor preview (the front end allows links in the consent text).
	 *
	 * @param {string} value HTML.
	 * @return {string} Text.
	 */
	function stripTags( value ) {
		var doc = new window.DOMParser().parseFromString( value, 'text/html' );
		return doc.body.textContent || '';
	}

	function listOptions() {
		var options = [
			{
				value: '',
				label: data.syncListName
					? sprintf(
							/* translators: %s: NewsMAN list name */
							__( 'Default list from NewsMAN Sync (%s)', 'newsman' ),
							data.syncListName
					  )
					: __( 'Default list from NewsMAN Sync', 'newsman' ),
			},
		];
		return options.concat( data.lists || [] );
	}

	function segmentOptions( listId ) {
		var segments = ( data.segments && data.segments[ listId ] ) || [];
		return [ { value: '', label: __( 'No segment', 'newsman' ) } ].concat( segments );
	}

	/**
	 * Sidebar controls stacked with even spacing (the controls have no bottom margin).
	 *
	 * @param {Array}  children Controls.
	 * @param {string} gap      CSS gap.
	 * @param {Object} extra    Extra props.
	 * @return {Object} Element.
	 */
	function stack( children, gap, extra ) {
		var props = Object.assign( { style: { display: 'flex', flexDirection: 'column', gap: gap || '16px' } }, extra || {} );
		return el( 'div', props, children );
	}

	function fieldPreview( attributes, key, labelKey, type, required ) {
		return el(
			'p',
			{ className: 'nzm-subscribe-form__field nzm-subscribe-form__field--' + key, key: key },
			el(
				'label',
				null,
				text( attributes, labelKey ),
				required ? el( 'span', { className: 'nzm-subscribe-form__required', 'aria-hidden': 'true' }, ' *' ) : null
			),
			el( 'input', { type: type, readOnly: true, tabIndex: -1 } )
		);
	}

	function preview( attributes ) {
		var children = [];

		if ( attributes.showHeading ) {
			children.push( el( 'h3', { className: 'nzm-subscribe-form__heading', key: 'heading' }, text( attributes, 'headingText' ) ) );
		}

		var fields = [ fieldPreview( attributes, 'email', 'emailLabel', 'email', true ) ];
		if ( attributes.showFirstname ) {
			fields.push( fieldPreview( attributes, 'firstname', 'firstnameLabel', 'text', attributes.requireFirstname ) );
		}
		if ( attributes.showLastname ) {
			fields.push( fieldPreview( attributes, 'lastname', 'lastnameLabel', 'text', attributes.requireLastname ) );
		}
		if ( attributes.showPhone ) {
			fields.push( fieldPreview( attributes, 'phone', 'phoneLabel', 'tel', attributes.requirePhone ) );
		}
		if ( attributes.showConsent ) {
			fields.push(
				el(
					'p',
					{ className: 'nzm-subscribe-form__field nzm-subscribe-form__field--consent', key: 'consent' },
					el(
						'label',
						null,
						el( 'input', { type: 'checkbox', readOnly: true, tabIndex: -1 } ),
						el( 'span', null, stripTags( text( attributes, 'consentText' ) ) )
					)
				)
			);
		}
		fields.push(
			el(
				'p',
				{ className: 'nzm-subscribe-form__actions', key: 'actions' },
				el(
					'button',
					{ type: 'button', className: 'wp-block-button__link wp-element-button nzm-subscribe-form__button', tabIndex: -1 },
					text( attributes, 'buttonText' )
				)
			)
		);

		children.push( el( 'div', { className: 'nzm-subscribe-form__form', key: 'form' }, fields ) );

		return children;
	}

	function textControl( attributes, setAttributes, key, label ) {
		return el( components.TextControl, {
			key: key,
			label: label,
			value: attributes[ key ],
			placeholder: defaults[ key ] || '',
			onChange: function ( value ) {
				var update = {};
				update[ key ] = value;
				setAttributes( update );
			},
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
		} );
	}

	function toggle( attributes, setAttributes, key, label, help ) {
		return el( components.ToggleControl, {
			key: key,
			label: label,
			help: help,
			checked: !! attributes[ key ],
			onChange: function ( value ) {
				var update = {};
				update[ key ] = value;
				setAttributes( update );
			},
			__nextHasNoMarginBottom: true,
		} );
	}

	function fieldToggles( attributes, setAttributes, suffix, label ) {
		var controls = [ toggle( attributes, setAttributes, 'show' + suffix, label ) ];
		if ( attributes[ 'show' + suffix ] ) {
			controls.push(
				el( 'div', { key: 'required', style: { marginLeft: '40px' } }, toggle( attributes, setAttributes, 'require' + suffix, __( 'Required', 'newsman' ) ) )
			);
		}
		return stack( controls, '8px', { key: suffix } );
	}

	function inspector( attributes, setAttributes ) {
		var listId = attributes.listId || '';
		var segments = segmentOptions( listId );

		var listPanel = [
			el( components.SelectControl, {
				key: 'list',
				label: __( 'List', 'newsman' ),
				value: listId,
				options: listOptions(),
				onChange: function ( value ) {
					setAttributes( { listId: value, segmentId: '' } );
				},
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
		];
		if ( listId !== '' && segments.length > 1 ) {
			listPanel.push(
				el( components.SelectControl, {
					key: 'segment',
					label: __( 'Segment', 'newsman' ),
					value: attributes.segmentId || '',
					options: segments,
					onChange: function ( value ) {
						setAttributes( { segmentId: value } );
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} )
			);
		}
		if ( data.doubleOptin ) {
			listPanel.push(
				el( components.TextControl, {
					key: 'confirmationFormId',
					label: __( 'Confirmation email Form ID', 'newsman' ),
					help: __( 'Optional. Leave empty to use the Confirmation email Form ID from NewsMAN > Settings.', 'newsman' ),
					value: attributes.confirmationFormId,
					placeholder: data.globalFormId || '',
					onChange: function ( value ) {
						setAttributes( { confirmationFormId: value.trim() } );
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} )
			);
		}
		listPanel.push(
			el(
				'p',
				{ key: 'optin', className: 'components-base-control__help', style: { margin: 0 } },
				listId === ''
					? __( 'Subscribers go to the list and segment selected in NewsMAN > Sync.', 'newsman' ) + ' '
					: '',
				data.doubleOptin
					? __( 'Opt-in type from NewsMAN > Settings: Double Opt-in (a confirmation email is sent).', 'newsman' )
					: __( 'Opt-in type from NewsMAN > Settings: Opt-in (subscribed right away).', 'newsman' )
			)
		);

		var fieldsPanel = [
			el( 'p', { key: 'email', className: 'components-base-control__help', style: { margin: 0 } }, __( 'The email field is always shown and required.', 'newsman' ) ),
			fieldToggles( attributes, setAttributes, 'Firstname', __( 'Show first name', 'newsman' ) ),
			fieldToggles( attributes, setAttributes, 'Lastname', __( 'Show last name', 'newsman' ) ),
			fieldToggles( attributes, setAttributes, 'Phone', __( 'Show phone', 'newsman' ) ),
			toggle(
				attributes,
				setAttributes,
				'showConsent',
				__( 'Show consent checkbox', 'newsman' ),
				__( 'A required checkbox. Subscribers must check it to subscribe.', 'newsman' )
			),
			toggle( attributes, setAttributes, 'showHeading', __( 'Show heading', 'newsman' ) ),
		];

		var textsPanel = [];
		if ( attributes.showHeading ) {
			textsPanel.push( textControl( attributes, setAttributes, 'headingText', __( 'Heading', 'newsman' ) ) );
		}
		textsPanel.push( textControl( attributes, setAttributes, 'emailLabel', __( 'Email label', 'newsman' ) ) );
		if ( attributes.showFirstname ) {
			textsPanel.push( textControl( attributes, setAttributes, 'firstnameLabel', __( 'First name label', 'newsman' ) ) );
		}
		if ( attributes.showLastname ) {
			textsPanel.push( textControl( attributes, setAttributes, 'lastnameLabel', __( 'Last name label', 'newsman' ) ) );
		}
		if ( attributes.showPhone ) {
			textsPanel.push( textControl( attributes, setAttributes, 'phoneLabel', __( 'Phone label', 'newsman' ) ) );
		}
		if ( attributes.showConsent ) {
			textsPanel.push(
				el( components.TextareaControl, {
					key: 'consentText',
					label: __( 'Consent text', 'newsman' ),
					help: __( 'Leave empty to use the default text with a link to the privacy policy page. Links, bold and italic are allowed.', 'newsman' ),
					value: attributes.consentText,
					placeholder: defaults.consentText || '',
					onChange: function ( value ) {
						setAttributes( { consentText: value } );
					},
					__nextHasNoMarginBottom: true,
				} )
			);
		}
		textsPanel.push( textControl( attributes, setAttributes, 'buttonText', __( 'Button text', 'newsman' ) ) );
		textsPanel.push( textControl( attributes, setAttributes, 'successMessage', __( 'Success message', 'newsman' ) ) );

		return el(
			blockEditor.InspectorControls,
			null,
			el( components.PanelBody, { title: __( 'NewsMAN list', 'newsman' ) }, stack( listPanel ) ),
			el( components.PanelBody, { title: __( 'Fields', 'newsman' ) }, stack( fieldsPanel ) ),
			el( components.PanelBody, { title: __( 'Texts', 'newsman' ), initialOpen: false }, stack( textsPanel ) )
		);
	}

	function notConfigured() {
		return el(
			components.Placeholder,
			{
				icon: 'email-alt',
				label: __( 'NewsMAN Subscribe Form', 'newsman' ),
				instructions: __( 'Connect the NewsMAN plugin to your NewsMAN account to use this form. The form is not shown on the site until then.', 'newsman' ),
			},
			el( components.ExternalLink, { href: data.settingsUrl }, __( 'Open NewsMAN settings', 'newsman' ) )
		);
	}

	wp.blocks.registerBlockType( 'newsman/subscribe-form', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps( { className: 'nzm-subscribe-form' } );

			if ( ! data.configured ) {
				return el( 'div', blockProps, notConfigured() );
			}

			return el(
				Fragment,
				null,
				inspector( props.attributes, props.setAttributes ),
				el( 'div', blockProps, el( components.Disabled, null, preview( props.attributes ) ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp, window.newsmanSubscribeFormBlock );
