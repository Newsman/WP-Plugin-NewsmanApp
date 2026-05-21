/**
 * Title: Newsman admin
 *
 * @package NewsmanApp for WordPress
 */

( function ($) {
	$(
		function () {
			// Tabs top page.
			jQuery( '#newsmanBtn' ).on(
				'click',
				function () {
					location.href = NEWSMAN_URLS.admin_url + 'admin.php?page=Newsman';
				}
			);
			jQuery( '#syncBtn' ).on(
				'click',
				function () {
					location.href = NEWSMAN_URLS.admin_url + 'admin.php?page=NewsmanSync';
				}
			);
			jQuery( '#remarketingBtn' ).on(
				'click',
				function () {
					location.href = NEWSMAN_URLS.admin_url + 'admin.php?page=NewsmanRemarketing';
				}
			);
			jQuery( '#smsBtn' ).on(
				'click',
				function () {
					location.href = NEWSMAN_URLS.admin_url + 'admin.php?page=NewsmanSMS';
				}
			);
			jQuery( '#settingsBtn' ).on(
				'click',
				function () {
					location.href = NEWSMAN_URLS.admin_url + 'admin.php?page=NewsmanSettings';
				}
			);

			// Send SMS test now.
			jQuery( 'input[name="newsman_smsdevbtn"]' ).on(
				'click',
				function () {
					var phone = jQuery( '#newsman_smsdevtestnr' ).val();
					var msg   = jQuery( '#newsman_smsdevtestmsg' ).val();

					if (phone === '' || msg === '') {
						jQuery( '<p class="">Phone and Message cannot be empty</p>' ).appendTo( '.msg_smsdevbtn' );

						return false;
					}

					// Assign action.
					jQuery( 'input[name="newsman_action"]' ).val( 'newsman_smsdevbtn' );

					jQuery( '#mainForm' ).submit();
				}
			);

			var $show = function (checkboxSelector, onChecked, childsSelector, displayCss, useClosestTr = true) {
				var $checkbox = jQuery( checkboxSelector );

				$checkbox.on(
					'click',
					function () {
						jQuery.each(
							childsSelector,
							function (index, childSelector) {
								var child;

								if (useClosestTr) {
									child = jQuery( childSelector ).closest( 'tr' );
								} else {
									child = jQuery( childSelector );
								}

								if (child.length === 0) {
									return true;
								}

								if (onChecked) {
									if ($checkbox.is( ':checked' )) {
										child.css( 'display', displayCss );
									} else {
										child.css( 'display', 'none' );
									}
								} else {
									if ($checkbox.is( ':checked' )) {
										child.css( 'display', 'none' );
									} else {
										child.css( 'display', displayCss );
									}
								}
							}
						);
					}
				);
			}

			$show( '#newsman_api', true, ['#newsman_developerapitimeout'], 'table-row' );
			$show( '#newsman_senduserip', false, ['#newsman_serverip'], 'table-row' );
			$show( '#newsman_developeractiveuserip', true, ['#newsman_developeruserip'], 'table-row' );
			$show(
				'#newsman_checkoutnewsletter',
				true,
				[
					'#newsman_checkoutnewslettermessage',
					'#newsman_checkoutnewsletterdefault'
				],
				'table-row'
			);
			$show(
				'#newsman_checkout_order_status',
				true,
				[
					'#newsman_checkout_order_status_label',
					'#newsman_checkout_order_status_default'
				],
				'table-row'
			);
			$show(
				'#newsman_myaccountnewsletter',
				true,
				[
					'#newsman_myaccountnewsletter_menu_label',
					'#newsman_myaccountnewsletter_page_title',
					'#newsman_myaccountnewsletter_checkbox_label'
				],
				'table-row'
			);
			$show(
				'#newsman_developer_use_action_scheduler',
				true,
				[
					'#newsman_developer_use_as_subscribe',
					'#newsman_developer_use_as_unsubscribe'
				],
				'table-row'
			);

			$show( '#newsman_smsrefundedactivate', true, ['.newsman_smsrefundedtextPanel'], 'block', false );
			$show( '#newsman_smscancelledactivate', true, ['.newsman_smscancelledtextPanel'], 'block', false );
			$show( '#newsman_smscompletedactivate', true, ['.newsman_smscompletedtextPanel'], 'block', false );
			$show( '#newsman_smsprocessingactivate', true, ['.newsman_smsprocessingtextPanel'], 'block', false );
			$show( '#newsman_smsonholdactivate', true, ['.newsman_smsonholdtextPanel'], 'block', false );
			$show( '#newsman_smsfailedactivate', true, ['.newsman_smsfailedtextPanel'], 'block', false );
			$show( '#newsman_smspendingactivate', true, ['.newsman_smspendingtextPanel'], 'block', false );

			$show( '#newsman_sms_send_cargus_awb', true, ['#newsman_sms_cargus_awb_message'], 'table-row' );
			$show( '#newsman_sms_send_sameday_awb', true, ['#newsman_sms_sameday_awb_message'], 'table-row' );
			$show( '#newsman_sms_send_fancourier_awb', true, ['#newsman_sms_fancourier_awb_message'], 'table-row' );

			$show( '#newsman_elementor_export_subscribers', true, ['#newsman_elementor_export_form_id'], 'table-row' );
			$show( '#newsman_contact_form_7_export_subscribers', true, ['#newsman_contact_form_7_export_form_id'], 'table-row' );
			$show( '#newsman_wpforms_export_subscribers', true, ['#newsman_wpforms_export_form_id'], 'table-row' );
			$show( '#newsman_gravity_forms_export_subscribers', true, ['#newsman_gravity_forms_export_form_id'], 'table-row' );

			// Sync page: live list -> segment dropdown filter.
			// The full per-list segment map ([listId => {segmentId: name}]) is emitted as a
			// data attribute on #newsman_segments, so changing the list repopulates the segment
			// dropdown without a page reload (same cached map used by the form integrations).
			var $newsmanSegments = jQuery( '#newsman_segments' );
			if ($newsmanSegments.length) {
				var newsmanSegmentsByList = {};
				try {
					newsmanSegmentsByList = JSON.parse( $newsmanSegments.attr( 'data-segments-by-list' ) || '{}' );
				} catch (e) {
					newsmanSegmentsByList = {};
				}

				jQuery( '#newsman_list' ).on(
					'change',
					function () {
						var listId   = String( jQuery( this ).val() || '0' );
						var segments = newsmanSegmentsByList[listId] || {};
						var selected = String( $newsmanSegments.val() || '0' );

						// Keep the first (placeholder) option, drop the rest, then rebuild.
						$newsmanSegments.find( 'option:not(:first)' ).remove();
						Object.keys( segments ).forEach(
							function (segmentId) {
								var $option = jQuery( '<option></option>' )
									.attr( 'value', segmentId )
									.text( String( segments[segmentId] ) );
								if (selected === String( segmentId )) {
									$option.attr( 'selected', 'selected' );
								}
								$newsmanSegments.append( $option );
							}
						);

						// Previously selected segment no longer belongs to this list -> reset to placeholder.
						if (selected !== '0' && ! Object.prototype.hasOwnProperty.call( segments, selected )) {
							$newsmanSegments.val( '0' );
						}
					}
				);
			}
		}
	);
} (jQuery));
