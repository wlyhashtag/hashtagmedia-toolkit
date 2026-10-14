/**
 * Focuspunt-kiezer (module Afbeeldingen). Werkt met gedelegeerde events,
 * zodat hij ook werkt als het mediavenster de velden opnieuw tekent.
 */
( function ( $ ) {
	'use strict';

	var STEP = 5;

	function round( value ) {
		return Math.round( Math.max( 0, Math.min( 100, value ) ) * 10 ) / 10;
	}

	function set( $picker, x, y ) {
		x = round( x );
		y = round( y );
		$picker.find( '.hm-focus__dot' ).css( { left: x + '%', top: y + '%' } );
		$picker.find( '.hm-focus__value' ).text( x + '% ' + y + '%' );
		var $input = $picker.find( '.hm-focus__input' ).val( x + ',' + y );
		if ( document.documentElement.classList.contains( 'hm-te-on' ) ) {
			// Frontend-editor aan: niet meteen opslaan, de editor neemt het mee bij Publiceren.
			var id = ( /attachments\[(\d+)\]/.exec( $input.attr( 'name' ) || '' ) || [] )[ 1 ];
			document.dispatchEvent( new CustomEvent( 'hm-toolkit-focus', { detail: { id: id, value: x + ',' + y } } ) );
			return;
		}
		// "change" laat WordPress het veld opslaan (mediavenster) of meesturen (bewerkscherm).
		$input.trigger( 'change' );
	}

	function current( $picker ) {
		var parts = String( $picker.find( '.hm-focus__input' ).val() || '50,50' ).split( ',' );
		return [ parseFloat( parts[ 0 ] ) || 50, parseFloat( parts[ 1 ] ) || 50 ];
	}

	$( document ).on( 'click', '.hm-focus__frame', function ( event ) {
		var rect = this.getBoundingClientRect();
		if ( ! rect.width || ! rect.height ) {
			return;
		}
		set(
			$( this ).closest( '.hm-focus' ),
			( ( event.clientX - rect.left ) / rect.width ) * 100,
			( ( event.clientY - rect.top ) / rect.height ) * 100
		);
	} );

	$( document ).on( 'keydown', '.hm-focus__frame', function ( event ) {
		var keys = { ArrowLeft: [ -STEP, 0 ], ArrowRight: [ STEP, 0 ], ArrowUp: [ 0, -STEP ], ArrowDown: [ 0, STEP ] };
		if ( ! keys[ event.key ] ) {
			return;
		}
		event.preventDefault();
		var $picker = $( this ).closest( '.hm-focus' );
		var pos = current( $picker );
		set( $picker, pos[ 0 ] + keys[ event.key ][ 0 ], pos[ 1 ] + keys[ event.key ][ 1 ] );
	} );

	$( document ).on( 'click', '.hm-focus__reset', function () {
		set( $( this ).closest( '.hm-focus' ), 50, 50 );
	} );
}( jQuery ) );
