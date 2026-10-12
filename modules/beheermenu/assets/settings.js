/**
 * Beheermenu: slepen (ook met de pijltjestoetsen op de greep) en submenu's
 * op het instellingentabblad.
 */
( function ( $ ) {
	'use strict';

	var $list = $( '#hm-menu-list' );
	var live = document.getElementById( 'hm-menu-live' );
	var text = window.hmToolkitMenuSettings || { moved: '%s' };

	if ( ! $list.length ) {
		return;
	}

	function announce( $item ) {
		if ( live ) {
			live.textContent = text.moved.replace( '%s', $.trim( $item.find( '> .hm-menu-item__row .hm-menu-item__title' ).text() ) );
		}
	}

	$list.sortable( {
		items: '> .hm-menu-item',
		handle: '.hm-menu-item__handle, .hm-menu-item__title',
		// De greep is een knop; standaard slaat sortable knoppen over.
		cancel: 'input, textarea, select, option, .hm-menu-expand, .hm-toggle',
		placeholder: 'hm-menu-placeholder',
		axis: 'y',
		tolerance: 'pointer',
		update: function ( event, ui ) {
			announce( ui.item );
		}
	} );

	// Toetsenbord: pijltje omhoog/omlaag op de greep verplaatst het item.
	$list.on( 'keydown', '.hm-menu-item__handle', function ( event ) {
		var $handle = $( this );
		var $item = $handle.closest( '.hm-menu-item' );
		var $target;
		if ( 'ArrowUp' === event.key ) {
			$target = $item.prev( '.hm-menu-item' );
			if ( $target.length ) {
				$target.before( $item );
			}
		} else if ( 'ArrowDown' === event.key ) {
			$target = $item.next( '.hm-menu-item' );
			if ( $target.length ) {
				$target.after( $item );
			}
		} else {
			return;
		}
		event.preventDefault();
		$handle.trigger( 'focus' );
		announce( $item );
	} );

	$list.on( 'click', '.hm-menu-expand', function () {
		var $button = $( this );
		var sub = document.getElementById( $button.attr( 'aria-controls' ) );
		var open = 'true' === $button.attr( 'aria-expanded' );
		$button.attr( 'aria-expanded', open ? 'false' : 'true' );
		if ( sub ) {
			sub.hidden = open;
		}
	} );
}( jQuery ) );
