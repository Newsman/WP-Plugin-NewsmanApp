/**
 * NewsMAN Subscribe Form block - front end script.
 *
 * Sends the form to admin-ajax.php and shows the result in the form. Without
 * JavaScript the form posts to admin-post.php and the page shows the result
 * after the redirect.
 *
 * @package NewsmanApp for WordPress
 */

( function () {
	'use strict';

	function showMessage( form, message, success ) {
		var box = form.querySelector( '.nzm-subscribe-form__message' );
		if ( ! box ) {
			return;
		}
		box.textContent = message;
		box.classList.toggle( 'is-success', success );
		box.classList.toggle( 'is-error', ! success );
		box.hidden = false;
	}

	function onSubmit( event ) {
		var form = event.currentTarget;
		var ajaxUrl = form.getAttribute( 'data-ajax-url' );
		if ( ! ajaxUrl || ! window.fetch || ! window.FormData ) {
			return;
		}
		event.preventDefault();

		if ( form.classList.contains( 'is-submitting' ) ) {
			return;
		}

		var button = form.querySelector( 'button[type="submit"]' );
		var errorMessage = form.getAttribute( 'data-error-message' ) || '';
		var successMessage = form.getAttribute( 'data-success-message' ) || '';

		form.classList.add( 'is-submitting' );
		if ( button ) {
			button.disabled = true;
		}

		window
			.fetch( ajaxUrl, {
				method: 'POST',
				body: new window.FormData( form ),
				credentials: 'same-origin',
			} )
			.then( function ( response ) {
				return response.json().catch( function () {
					return null;
				} );
			} )
			.then( function ( json ) {
				var success = !! ( json && json.success );
				var message = json && json.data && json.data.message ? json.data.message : errorMessage;
				if ( success && successMessage !== '' ) {
					message = successMessage;
				}
				showMessage( form, message, success );
				if ( success ) {
					form.reset();
				}
			} )
			.catch( function () {
				showMessage( form, errorMessage, false );
			} )
			.then( function () {
				form.classList.remove( 'is-submitting' );
				if ( button ) {
					button.disabled = false;
				}
			} );
	}

	function init() {
		var forms = document.querySelectorAll( '.nzm-subscribe-form__form' );
		for ( var i = 0; i < forms.length; i++ ) {
			forms[ i ].addEventListener( 'submit', onSubmit );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
