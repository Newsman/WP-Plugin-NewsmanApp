/**
 * Title: Newsman admin
 *
 * @package NewsmanApp for WordPress
 */

( function ($) {
	$(
		function () {
			jQuery( '#newsmanBtn' ).on(
				'click',
				function () {
					location.href = '/wp-admin/admin.php?page=Newsman';
				}
			);
			jQuery( '#syncBtn' ).on(
				'click',
				function () {
					location.href = '/wp-admin/admin.php?page=NewsmanSync';
				}
			);
			jQuery( '#remarketingBtn' ).on(
				'click',
				function () {
					location.href = '/wp-admin/admin.php?page=NewsmanRemarketing';
				}
			);
			jQuery( '#smsBtn' ).on(
				'click',
				function () {
					location.href = '/wp-admin/admin.php?page=NewsmanSMS';
				}
			);
			jQuery( '#settingsBtn' ).on(
				'click',
				function () {
					location.href = '/wp-admin/admin.php?page=NewsmanSettings';
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

			// Settings checkout newsletter.
			jQuery( 'input[name="newsman_checkoutnewsletter"]' ).on(
				'click',
				function () {
					if (jQuery( 'input[name="newsman_checkoutnewsletter"]' ).is( ':checked' )) {
						jQuery( ".newsman_checkoutnewslettertypePanel" ).css( 'display', 'table-row' );
					} else {
						jQuery( ".newsman_checkoutnewslettertypePanel" ).css( 'display', 'none' );
					}
				}
			);

			jQuery( '#newsman_smsrefundedactivate' ).click(
				function () {
					if (jQuery( '#newsman_smsrefundedactivate' ).is( ':checked' )) {
						jQuery( '.newsman_smsrefundedtextPanel' ).css( 'display', 'block' );
					} else {
						jQuery( '.newsman_smsrefundedtextPanel' ).css( 'display', 'none' );
					}
				}
			);
			jQuery( '#newsman_smscancelledactivate' ).click(
				function () {
					if (jQuery( '#newsman_smscancelledactivate' ).is( ':checked' )) {
						jQuery( '.newsman_smscancelledtextPanel' ).css( 'display', 'block' );
					} else {
						jQuery( '.newsman_smscancelledtextPanel' ).css( 'display', 'none' );
					}
				}
			);
			jQuery( '#newsman_smscompletedactivate' ).click(
				function () {
					if (jQuery( '#newsman_smscompletedactivate' ).is( ':checked' )) {
						jQuery( '.newsman_smscompletedtextPanel' ).css( 'display', 'block' );
					} else {
						jQuery( '.newsman_smscompletedtextPanel' ).css( 'display', 'none' );
					}
				}
			);
			jQuery( '#newsman_smsprocessingactivate' ).click(
				function () {
					if (jQuery( '#newsman_smsprocessingactivate' ).is( ':checked' )) {
						jQuery( '.newsman_smsprocessingtextPanel' ).css( 'display', 'block' );
					} else {
						jQuery( '.newsman_smsprocessingtextPanel' ).css( 'display', 'none' );
					}
				}
			);
			jQuery( '#newsman_smsonholdactivate' ).click(
				function () {
					if (jQuery( '#newsman_smsonholdactivate' ).is( ':checked' )) {
						jQuery( '.newsman_smsonholdtextPanel' ).css( 'display', 'block' );
					} else {
						jQuery( '.newsman_smsonholdtextPanel' ).css( 'display', 'none' );
					}
				}
			);
			jQuery( '#newsman_smsfailedactivate' ).click(
				function () {
					if (jQuery( '#newsman_smsfailedactivate' ).is( ':checked' )) {
						jQuery( '.newsman_smsfailedtextPanel' ).css( 'display', 'block' );
					} else {
						jQuery( '.newsman_smsfailedtextPanel' ).css( 'display', 'none' );
					}
				}
			);
			jQuery( '#newsman_smspendingactivate' ).click(
				function () {
					if (jQuery( '#newsman_smspendingactivate' ).is( ':checked' )) {
						jQuery( '.newsman_smspendingtextPanel' ).css( 'display', 'block' );
					} else {
						jQuery( '.newsman_smspendingtextPanel' ).css( 'display', 'none' );
					}
				}
			);

			jQuery( '#newsman_api' ).click(
				function () {
					if (jQuery( '#newsman_api' ).is( ':checked' )) {
						jQuery( '.newsman_apiPanel' ).css( 'display', 'table-row' );
					} else {
						jQuery( '.newsman_apiPanel' ).css( 'display', 'none' );
					}
				}
			);

			jQuery( '#newsman_senduserip' ).click(
				function () {
					if (jQuery( '#newsman_senduserip' ).is( ':checked' )) {
						jQuery( '#newsman_serverip' ).closest( 'tr' ).css( 'display', 'none' );
					} else {
						jQuery( '#newsman_serverip' ).closest( 'tr' ).css( 'display', 'table-row' );
					}
				}
			);

			jQuery( '#newsman_developeractiveuserip' ).click(
				function () {
					if (jQuery( '#newsman_developeractiveuserip' ).is( ':checked' )) {
						jQuery( '#newsman_developeruserip' ).closest( 'tr' ).css( 'display', 'table-row' );
					} else {
						jQuery( '#newsman_developeruserip' ).closest( 'tr' ).css( 'display', 'none' );
					}
				}
			);

		}
	);
} (jQuery));
