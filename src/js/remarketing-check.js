/**
 * Title: Newsman remarketing storefront check (NewsMAN admin page)
 *
 * Downloads the homepage, a product page and a category page with a one-time
 * token and looks for the NewsMAN remarketing scripts in their HTML. The pages
 * are not rendered, so their scripts do not run and nothing is sent to NewsMAN.
 *
 * @package NewsmanApp for WordPress
 */

( function () {
	'use strict';

	var config       = window.NEWSMAN_REMARKETING_CHECK;
	var PAGE_TIMEOUT = 20000;

	function format( text, value ) {
		return String( text ).replace( '%s', value );
	}

	function element( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text ) {
			node.textContent = text;
		}
		return node;
	}

	function statusLine( status, text ) {
		var icons = {
			good: 'dashicons-yes-alt',
			critical: 'dashicons-dismiss',
			info: 'dashicons-info'
		};
		var line  = element( 'span', 'nzm-health-status nzm-health-' + status );
		var icon  = element( 'span', 'dashicons ' + icons[ status ] );
		icon.setAttribute( 'aria-hidden', 'true' );
		line.appendChild( icon );
		line.appendChild( document.createTextNode( ' ' + text ) );
		return line;
	}

	function lookup( html, check ) {
		if ( 'text' === check.type ) {
			return '' !== check.pattern && -1 !== html.indexOf( check.pattern );
		}
		return new RegExp( check.pattern ).test( html );
	}

	function fetchPage( url ) {
		var controller = ( 'AbortController' in window ) ? new AbortController() : null;
		var timer      = controller ? setTimeout( controller.abort.bind( controller ), PAGE_TIMEOUT ) : null;
		var options    = {
			credentials: 'same-origin',
			cache: 'no-store',
			redirect: 'follow',
			headers: { Accept: 'text/html' }
		};
		if ( controller ) {
			options.signal = controller.signal;
		}
		return fetch( url, options ).then(
			function ( response ) {
				return response.text().then(
					function ( html ) {
						return { ok: response.ok, status: response.status, html: html };
					}
				);
			}
		).catch(
			function ( error ) {
				return { ok: false, status: 0, error: ( error && error.message ) ? error.message : String( error ) };
			}
		).then(
			function ( result ) {
				if ( timer ) {
					clearTimeout( timer );
				}
				return result;
			}
		);
	}

	function renderPage( container, page, result ) {
		var block    = element( 'div', 'nzm-rm-check-page nzm-rm-check-page-' + page.type );
		var header   = element( 'p', 'nzm-rm-check-page-title' );
		var list     = element( 'ul', 'nzm-rm-check-list' );
		var allFound = true;

		block.setAttribute( 'data-page', page.type );
		header.appendChild( element( 'strong', '', page.label ) );
		if ( page.link ) {
			var link    = element( 'a', 'nzm-rm-check-link', config.i18n.openPage );
			link.href   = page.link;
			link.target = '_blank';
			link.rel    = 'noopener noreferrer';
			header.appendChild( document.createTextNode( ' ' ) );
			header.appendChild( link );
		}
		block.appendChild( header );

		if ( page.skipped ) {
			var skipped = element( 'li' );
			skipped.setAttribute( 'data-skipped', '1' );
			skipped.appendChild( statusLine( 'info', config.i18n.skipped + ': ' + page.skipped ) );
			list.appendChild( skipped );
			block.appendChild( list );
			container.appendChild( block );
			return true;
		}

		if ( ! result.ok ) {
			var failed = element( 'li' );
			failed.setAttribute( 'data-error', '1' );
			failed.appendChild(
				statusLine(
					'critical',
					result.status ? format( config.i18n.httpError, result.status ) : format( config.i18n.loadError, result.error )
				)
			);
			list.appendChild( failed );
			block.appendChild( list );
			container.appendChild( block );
			return false;
		}

		page.checks.forEach(
			function ( check ) {
				var found = lookup( result.html, check );
				var item  = element( 'li' );
				item.setAttribute( 'data-check', check.key );
				item.setAttribute( 'data-found', found ? '1' : '0' );
				item.appendChild( statusLine( found ? 'good' : 'critical', check.label + ': ' + ( found ? config.i18n.found : config.i18n.notFound ) ) );
				list.appendChild( item );
				allFound = allFound && found;
			}
		);
		block.appendChild( list );
		container.appendChild( block );
		return allFound;
	}

	function run( button, results, spinner ) {
		button.disabled = true;
		spinner.classList.add( 'is-active' );
		results.textContent = '';
		var progress        = element( 'p', 'description', config.i18n.checking );
		results.appendChild( progress );

		var body = new URLSearchParams();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );

		fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then(
			function ( response ) {
				return response.json();
			}
		).then(
			function ( json ) {
				if ( ! json || ! json.success || ! json.data || ! Array.isArray( json.data.pages ) ) {
					throw new Error( 'start' );
				}
				var allFound = true;
				var chain    = Promise.resolve();
				json.data.pages.forEach(
					function ( page ) {
						chain = chain.then(
							function () {
								if ( page.skipped ) {
									return null;
								}
								return fetchPage( page.url );
							}
						).then(
							function ( result ) {
								allFound = renderPage( results, page, result ) && allFound;
							}
						);
					}
				);
				return chain.then(
					function () {
						return allFound;
					}
				);
			}
		).then(
			function ( allFound ) {
				progress.remove();
				var summary = element( 'p', 'nzm-rm-check-summary' );
				summary.setAttribute( 'data-all-found', allFound ? '1' : '0' );
				summary.appendChild( statusLine( allFound ? 'good' : 'critical', allFound ? config.i18n.allFound : config.i18n.someMissing ) );
				results.appendChild( summary );
			}
		).catch(
			function () {
				progress.remove();
				var failed = element( 'p', 'nzm-rm-check-summary' );
				failed.setAttribute( 'data-all-found', '0' );
				failed.appendChild( statusLine( 'critical', config.i18n.startError ) );
				results.appendChild( failed );
			}
		).then(
			function () {
				button.disabled = false;
				spinner.classList.remove( 'is-active' );
			}
		);
	}

	function init() {
		var button  = document.getElementById( 'nzm-remarketing-check' );
		var results = document.getElementById( 'nzm-remarketing-check-results' );
		var spinner = document.getElementById( 'nzm-remarketing-check-spinner' );
		if ( ! button || ! results || ! spinner || ! config ) {
			return;
		}
		button.addEventListener(
			'click',
			function () {
				run( button, results, spinner );
			}
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
