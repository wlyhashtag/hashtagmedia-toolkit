/**
 * Meldingenpaneel (module Opruimen). Geen jQuery nodig.
 */
( function () {
	'use strict';

	var node = document.getElementById( 'wp-admin-bar-hm-toolkit-notices' );
	var template = document.getElementById( 'hm-toolkit-notices-template' );
	var panel = document.getElementById( 'hm-toolkit-notices-panel' );

	if ( ! node || ! template || ! panel ) {
		return;
	}

	var link = node.querySelector( 'a' );
	var body = panel.querySelector( '.hm-toolkit-notices-panel__body' );
	var close = panel.querySelector( '.hm-toolkit-notices-panel__close' );
	var badge = node.querySelector( '.hm-toolkit-notices-count' );
	var filled = false;
	var ignore = [ 'SCRIPT', 'STYLE', 'LINK', 'TEMPLATE', 'NOSCRIPT' ];

	function countNotices() {
		return Array.prototype.filter.call( body.children, function ( el ) {
			return -1 === ignore.indexOf( el.tagName );
		} ).length;
	}

	link.setAttribute( 'role', 'button' );
	link.setAttribute( 'aria-expanded', 'false' );
	link.setAttribute( 'aria-controls', panel.id );

	function fill() {
		if ( filled ) {
			return;
		}
		filled = true;
		body.appendChild( document.importNode( template.content, true ) );

		// Laat WordPress de sluitknoppen van wegklikbare meldingen toevoegen.
		if ( window.jQuery ) {
			window.jQuery( document ).trigger( 'wp-notice-added' );
		}

		// Aantal bijwerken als een melding wordt weggeklikt.
		new MutationObserver( function () {
			var count = countNotices();
			if ( badge ) {
				badge.textContent = String( count );
			}
			if ( 0 === count ) {
				toggle( false );
				node.parentNode.removeChild( node );
			}
		} ).observe( body, { childList: true } );
	}

	function toggle( open ) {
		if ( open ) {
			fill();
		}
		panel.hidden = ! open;
		link.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			close.focus();
		}
	}

	link.addEventListener( 'click', function ( event ) {
		event.preventDefault();
		toggle( panel.hidden );
	} );

	close.addEventListener( 'click', function () {
		toggle( false );
		link.focus();
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! panel.hidden ) {
			toggle( false );
			link.focus();
		}
	} );
}() );
