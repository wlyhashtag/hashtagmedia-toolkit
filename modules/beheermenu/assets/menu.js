/**
 * Beheermenu: knop "Alles tonen" onderaan het menu (alleen beheerders).
 */
( function () {
	'use strict';

	var menu = document.getElementById( 'adminmenu' );
	var collapse = document.getElementById( 'collapse-menu' );
	var labels = window.hmToolkitMenu || { showAll: 'Alles tonen', showLess: 'Minder tonen' };
	var root = document.documentElement;
	var key = 'hmToolkitMenuShowAll';

	if ( ! menu || ! menu.querySelector( '.hm-menu-hidden' ) ) {
		return;
	}

	var item = document.createElement( 'li' );
	item.id = 'hm-menu-toggle';
	item.className = 'hm-menu-toggle';
	item.innerHTML = '<a href="#" class="menu-top" aria-expanded="false">' +
		'<div class="wp-menu-image dashicons-before" aria-hidden="true"><br></div>' +
		'<div class="wp-menu-name"></div></a>';

	var link = item.querySelector( 'a' );
	var name = item.querySelector( '.wp-menu-name' );

	function render() {
		var open = root.classList.contains( 'hm-menu-show-all' );
		name.textContent = open ? labels.showLess : labels.showAll;
		link.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}

	link.addEventListener( 'click', function ( event ) {
		event.preventDefault();
		var open = root.classList.toggle( 'hm-menu-show-all' );
		try {
			window.localStorage.setItem( key, open ? '1' : '0' );
		} catch ( e ) {}
		render();
	} );

	if ( collapse && collapse.parentNode === menu ) {
		menu.insertBefore( item, collapse );
	} else {
		menu.appendChild( item );
	}
	render();
}() );
