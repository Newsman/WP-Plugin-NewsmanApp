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
			$show(
				'#newsman_remarketingexportwordpresssubscribers',
				true,
				[
					'#newsman_remarketingexportwordpresssubscribers_recurring_short_days',
					'#newsman_remarketingexportwordpresssubscribers_recurring_long_days'
				],
				'table-row'
			);
			$show(
				'#newsman_remarketingexportwoocommercesubscribers',
				true,
				[
					'#newsman_remarketingexportwoocommercesubscribers_recurring_short_days',
					'#newsman_remarketingexportwoocommercesubscribers_recurring_long_days'
				],
				'table-row'
			);
			$show(
				'#newsman_remarketingexportorders',
				true,
				[
					'#newsman_remarketingexportorders_recurring_short_days',
					'#newsman_remarketingexportorders_recurring_long_days'
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
		}
	);
} (jQuery));
