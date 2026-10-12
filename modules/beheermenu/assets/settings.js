/**
 * Beheermenu: slepen, pijltjes en submenu's op het instellingentabblad.
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
		placeholder: 'hm-menu-placeholder',
		axis: 'y',
		tolerance: 'pointer',
		update: function ( event, ui ) {
			announce( ui.item );
		}
	} );

	$list.on( 'click', '.hm-menu-move', function () {
		var $button = $( this );
		var $item = $button.closest( '.hm-menu-item' );
		if ( 'up' === $button.data( 'direction' ) ) {
			$item.prev( '.hm-menu-item' ).before( $item );
		} else {
			$item.next( '.hm-menu-item' ).after( $item );
		}
		$button.trigger( 'focus' );
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
