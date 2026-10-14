/**
 * Frontend-editor (module Frontend-editor).
 *
 * Velden met data-hm-field (gemaakt door hm_field()) worden in de bewerkmodus
 * bewerkbaar. Wijzigingen blijven in de browser tot Publiceren; dan gaan ze in
 * één verzoek naar de REST-route, die alles opnieuw controleert.
 *
 * De balk, werkbalken en vensters staan in een Shadow DOM (ui.css), zodat het
 * thema ze niet kan verstoren.
 */
( function () {
	'use strict';

	var cfg = window.hmToolkitEditor;
	if ( ! cfg || ! window.fetch || ! document.body.attachShadow ) {
		return;
	}

	var MIN_WIDTH = 783; // Zelfde grens als de mobiele adminbalk van WordPress.
	var root = document.documentElement;
	var changes = new Map(); // "post|veldsleutel" => { post, key, value }
	var shadow, $ = {};
	var anchors = new Map(); // zwevend element => veld waar het bij hoort
	var current = { tools: null, link: null };
	var savedRange = null;
	var publishing = false;
	var warned = new Set();
	var panel = { list: null, menuButton: null }; // Het zijpaneel: welke lijst is open.
	var drag = { row: null };

	var ICON = {
		pencil: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
		link: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>',
		ul: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>',
		ol: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 6h10M10 12h10M10 18h10"/><path d="M4 4h1v4M3.5 8h2.5M3.5 14.5c.5-1 2.5-1 2.5.3 0 1-2.5 1.7-2.5 3.2h2.5"/></svg>',
		image: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>',
		focus: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>',
		close: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		globe: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>',
		check: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>',
		dots: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>',
		up: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>',
		grip: '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>',
		plus: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>'
	};

	// Alleen vaste HTML; alles wat van de site komt gaat via textContent of value.
	var TEMPLATE =
		'<link rel="stylesheet" href="">' +
		'<aside class="layer card panel" hidden>' +
			'<header><div><div class="ptitle"></div><div class="psub"></div></div>' +
			'<button type="button" class="ico" data-act="panel-close" aria-label="Paneel sluiten">' + ICON.close + '</button></header>' +
			'<div class="plist"></div>' +
			'<footer><button type="button" class="b padd" data-act="panel-add">' + ICON.plus + 'Onderdeel toevoegen</button></footer>' +
			'<div class="menu" role="menu" hidden></div>' +
		'</aside>' +
		'<div class="layer card bar" role="toolbar" aria-label="Visueel bewerken">' + ICON.pencil +
			'<span class="title"></span>' +
			'<span class="status" aria-live="polite"><span class="dot"></span><span class="status-text"></span></span>' +
			'<button type="button" class="b" data-act="cancel"></button>' +
			'<button type="button" class="b bp" data-act="publish">Publiceren</button>' +
		'</div>' +
		'<div class="layer card tools" role="toolbar" aria-label="Opmaak" hidden>' +
			'<button type="button" class="tb" data-cmd="bold" aria-label="Vet"><b>B</b></button>' +
			'<button type="button" class="tb" data-cmd="italic" aria-label="Cursief"><i style="font-family:Georgia,serif;font-size:16px">I</i></button>' +
			'<button type="button" class="tb" data-cmd="link" aria-label="Link">' + ICON.link + '</button>' +
			'<span class="sep"></span>' +
			'<button type="button" class="tb" data-cmd="insertUnorderedList" aria-label="Opsomming">' + ICON.ul + '</button>' +
			'<button type="button" class="tb" data-cmd="insertOrderedList" aria-label="Genummerde lijst">' + ICON.ol + '</button>' +
			'<form class="linkform" hidden><input type="text" aria-label="Webadres of pagina, bijv. /contact" placeholder="/contact of https://…"><button type="submit" class="b bp" style="height:30px">OK</button></form>' +
		'</div>' +
		'<div class="layer chip" hidden></div>' +
		'<div class="layer card warn" role="status" hidden><span class="icon">' + ICON.globe + '</span><div><strong>Dit staat op elke pagina</strong><p>Na Publiceren is je wijziging overal op de site zichtbaar, niet alleen op deze pagina.</p></div></div>' +
		'<div class="layer card imgtools" hidden>' +
			'<button type="button" class="b" data-act="replace">' + ICON.image + 'Vervangen</button>' +
			'<button type="button" class="b" data-act="focus">' + ICON.focus + 'Focuspunt</button>' +
		'</div>' +
		'<div class="layer card pop" role="dialog" aria-label="Knop bewerken" hidden>' +
			'<header>Knop<button type="button" class="ico" data-act="close" aria-label="Sluiten">' + ICON.close + '</button></header>' +
			'<div class="body">' +
				'<div><label class="lbl" for="hm-te-title">Knoptekst</label><input type="text" id="hm-te-title"></div>' +
				'<div><label class="lbl" for="hm-te-url">Link</label><input type="text" id="hm-te-url" autocomplete="off">' +
				'<div class="results" hidden></div><p class="hint">Zoek een pagina, of plak een webadres.</p></div>' +
				'<label class="toggle"><span>Openen in een nieuw tabblad</span><input type="checkbox"></label>' +
			'</div>' +
			'<footer><button type="button" class="b" data-act="close">Annuleren</button><button type="button" class="b bp" data-act="apply">Toepassen</button></footer>' +
		'</div>' +
		'<div class="layer toast" role="status" hidden></div>';

	// ---------------------------------------------------------------- Start

	// De adminbalk komt pas na dit script in de pagina; daarom via document.
	document.addEventListener( 'click', function ( event ) {
		if ( event.target.closest && event.target.closest( '#wp-admin-bar-hm-toolkit-bewerken > .ab-item' ) ) {
			start( event );
		}
	} );

	function start( event ) {
		event.preventDefault();
		if ( window.innerWidth < MIN_WIDTH || root.classList.contains( 'hm-te-on' ) ) {
			return;
		}
		root.classList.add( 'hm-te-on' );
		build();
		document.querySelectorAll( '[data-hm-field]' ).forEach( setupField );
		( cfg.lists || [] ).forEach( setupList );
		document.addEventListener( 'click', blockNavigation, true );
		document.addEventListener( 'keydown', onKey );
		window.addEventListener( 'beforeunload', warnUnload );
		window.addEventListener( 'scroll', place, { passive: true } );
		window.addEventListener( 'resize', place );
		render();
	}

	function build() {
		var host = document.createElement( 'div' );
		host.id = 'hm-toolkit-editor';
		shadow = host.attachShadow( { mode: 'open' } );
		shadow.innerHTML = TEMPLATE;
		shadow.querySelector( 'link' ).href = cfg.ui;
		document.body.appendChild( host );

		[ 'bar', 'tools', 'chip', 'warn', 'imgtools', 'pop', 'toast', 'linkform', 'results', 'panel', 'plist', 'ptitle', 'psub', 'padd', 'menu' ].forEach( function ( name ) {
			$[ name ] = shadow.querySelector( '.' + name );
		} );
		$.panel.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button' );
			if ( button && 'panel-close' === button.dataset.act ) {
				closePanel();
			} else if ( button && 'panel-add' === button.dataset.act && panel.list ) {
				addRow( panel.list );
			} else if ( ! event.target.closest( '.menu, [aria-haspopup]' ) ) {
				closeMenu( true );
			}
		} );
		$.plist.addEventListener( 'scroll', place, { passive: true } );
		$.statusText = shadow.querySelector( '.status-text' );
		$.cancel = shadow.querySelector( '[data-act="cancel"]' );
		$.publish = shadow.querySelector( '[data-act="publish"]' );
		$.linkInput = $.linkform.querySelector( 'input' );
		$.popTitle = shadow.getElementById( 'hm-te-title' );
		$.popUrl = shadow.getElementById( 'hm-te-url' );
		$.popTarget = $.pop.querySelector( 'input[type="checkbox"]' );
		shadow.querySelector( '.title' ).textContent = cfg.title || '';
		shadow.querySelector( '[data-act="focus"]' ).hidden = ! cfg.focus;

		followFocus();
		$.cancel.addEventListener( 'click', cancel );
		$.publish.addEventListener( 'click', publish );

		// Werkbalk: mousedown niet doorgeven, dan blijft de selectie in de tekst staan.
		$.tools.addEventListener( 'mousedown', function ( event ) {
			if ( ! event.target.closest( 'input' ) ) {
				event.preventDefault();
			}
		} );
		$.tools.addEventListener( 'click', onToolClick );
		$.linkform.addEventListener( 'submit', onLinkSubmit );

		$.imgtools.addEventListener( 'mouseleave', function () {
			var el = anchors.get( $.imgtools );
			if ( el && ! el.matches( ':hover' ) && document.activeElement !== el ) {
				hide( $.imgtools );
			}
		} );
		$.imgtools.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button' );
			var el = anchors.get( $.imgtools );
			if ( button && el ) {
				openMedia( el, 'focus' === button.dataset.act );
			}
		} );

		$.pop.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button' );
			if ( ! button ) {
				return;
			}
			if ( 'close' === button.dataset.act ) {
				closePop();
			} else if ( 'apply' === button.dataset.act ) {
				applyPop();
			} else if ( button.classList.contains( 'res' ) ) {
				$.popUrl.value = button.dataset.url;
				$.results.hidden = true;
				$.popUrl.focus();
			}
		} );
		$.popUrl.addEventListener( 'input', debounce( search, 250 ) );
	}

	// ---------------------------------------------------------------- Velden

	function key( el ) {
		return el.dataset.hmPost + '|' + el.dataset.hmField;
	}

	function isOption( el ) {
		return 'option' === el.dataset.hmPost;
	}

	function value( el ) {
		switch ( el.dataset.hmType ) {
			case 'wysiwyg':
				return el.innerHTML;
			case 'image':
				return parseInt( el.dataset.hmValue, 10 ) || 0;
			case 'link':
				return {
					title: el.textContent,
					url: '#' === el.getAttribute( 'href' ) ? '' : ( el.getAttribute( 'href' ) || '' ),
					target: '_blank' === el.getAttribute( 'target' ) ? '_blank' : ''
				};
			default:
				return el.innerText;
		}
	}

	function setupField( el ) {
		var type = el.dataset.hmType;
		el.hmOriginal = JSON.stringify( value( el ) );

		if ( 'image' === type ) {
			el.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				openMedia( el, false );
			} );
			el.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key || ' ' === event.key ) {
					event.preventDefault();
					openMedia( el, false );
				}
			} );
			el.addEventListener( 'mouseenter', function () {
				show( $.imgtools, el );
			} );
			el.addEventListener( 'focus', function () {
				show( $.imgtools, el );
			} );
			el.addEventListener( 'mouseleave', function () {
				setTimeout( function () {
					if ( ! $.imgtools.matches( ':hover' ) && ! el.matches( ':hover' ) && document.activeElement !== el ) {
						hide( $.imgtools );
					}
				}, 200 );
			} );
		} else if ( 'link' === type ) {
			el.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				openPop( el );
			} );
		} else {
			if ( 'wysiwyg' === type ) {
				el.contentEditable = 'true';
			} else {
				try {
					el.contentEditable = 'plaintext-only';
				} catch ( e ) {
					el.contentEditable = 'true';
				}
			}
			el.addEventListener( 'input', function () {
				record( el );
			} );
			el.addEventListener( 'paste', pastePlain );
			if ( 'wysiwyg' === type ) {
				el.addEventListener( 'focus', function () {
					current.tools = el;
					$.linkform.hidden = true;
					show( $.tools, el );
				} );
				el.addEventListener( 'blur', function () {
					setTimeout( function () {
						// Blijven staan zolang je in de werkbalk zelf bezig bent (linkveld).
						if ( ! ( shadow.activeElement && $.tools.contains( shadow.activeElement ) ) ) {
							hide( $.tools );
						}
					}, 150 );
				} );
			} else if ( 'textarea' !== type ) {
				el.addEventListener( 'keydown', function ( event ) {
					if ( 'Enter' === event.key ) {
						event.preventDefault(); // Eén regel.
					}
				} );
			}
		}

		el.addEventListener( 'mouseenter', function () {
			show( $.chip, el );
		} );
		el.addEventListener( 'mouseleave', function () {
			if ( document.activeElement !== el ) {
				hide( $.chip );
			}
		} );
		el.addEventListener( 'focus', function () {
			show( $.chip, el );
			if ( isOption( el ) && ! warned.has( key( el ) ) ) {
				warned.add( key( el ) );
				show( $.warn, el );
				setTimeout( function () {
					hide( $.warn );
				}, 6000 );
			}
		} );
		el.addEventListener( 'blur', function () {
			hide( $.chip );
			hide( $.warn );
		} );
	}

	function pastePlain( event ) {
		event.preventDefault();
		var text = ( event.clipboardData || window.clipboardData ).getData( 'text/plain' );
		document.execCommand( 'insertText', false, text );
	}

	function record( el, newValue ) {
		var val = undefined === newValue ? value( el ) : newValue;
		var k = key( el );
		if ( JSON.stringify( val ) === el.hmOriginal ) {
			changes.delete( k );
			el.classList.remove( 'hm-te-changed' );
		} else {
			changes.set( k, { post: el.dataset.hmPost, key: el.dataset.hmField, value: val } );
			el.classList.add( 'hm-te-changed' );
		}
		render();
	}

	// ---------------------------------------------------------------- Opmaak

	function onToolClick( event ) {
		var button = event.target.closest( '.tb' );
		if ( ! button || ! current.tools ) {
			return;
		}
		if ( 'link' === button.dataset.cmd ) {
			var selection = getSel();
			savedRange = selection.rangeCount ? selection.getRangeAt( 0 ).cloneRange() : null;
			$.linkform.hidden = false;
			place(); // De werkbalk is nu breder: binnen het scherm houden.
			$.linkInput.value = '';
			$.linkInput.focus();
			return;
		}
		document.execCommand( button.dataset.cmd, false, null );
		edited( current.tools );
	}

	// Een veld in het zijpaneel meldt zelf wat er gebeurt (hmEdited); een veld op de pagina wordt direct vastgelegd.
	function edited( el ) {
		if ( el.hmEdited ) {
			el.hmEdited();
		} else {
			record( el );
		}
	}

	// Een selectie binnen het paneel (Shadow DOM) staat in de shadowroot, niet in het document.
	function getSel() {
		return shadow.getSelection ? shadow.getSelection() : document.getSelection();
	}

	function onLinkSubmit( event ) {
		event.preventDefault();
		var url = $.linkInput.value.trim();
		var el = current.tools;
		$.linkform.hidden = true;
		if ( ! el ) {
			return;
		}
		el.focus();
		if ( savedRange ) {
			var selection = getSel();
			selection.removeAllRanges();
			selection.addRange( savedRange );
		}
		if ( url ) {
			document.execCommand( 'createLink', false, url );
		} else {
			document.execCommand( 'unlink', false, null );
		}
		edited( el );
	}

	// ---------------------------------------------------------------- Afbeeldingen

	function openMedia( el, focusOnly ) {
		chooseImage( el.dataset.hmValue, focusOnly, function ( att ) {
			setImage( el, att );
		} );
	}

	// Mediavenster; onSelect krijgt de gekozen afbeelding (niet bij focusOnly).
	function chooseImage( currentId, focusOnly, onSelect ) {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		var id = parseInt( currentId, 10 ) || 0;
		var frame = wp.media( {
			title: focusOnly ? 'Focuspunt kiezen' : 'Afbeelding kiezen',
			button: { text: focusOnly ? 'Klaar' : 'Deze afbeelding gebruiken' },
			library: { type: 'image' },
			multiple: false
		} );
		frame.on( 'open', function () {
			frame.content.mode( 'browse' ); // Anders opent het soms op "Bestanden uploaden".
			if ( id ) {
				var attachment = wp.media.attachment( id );
				attachment.fetch();
				frame.state().get( 'selection' ).reset( [ attachment ] );
			}
		} );
		frame.on( 'select', function () {
			if ( focusOnly ) {
				return; // Het focuspunt komt via followFocus().
			}
			onSelect( frame.state().get( 'selection' ).first().toJSON() );
		} );
		frame.open();
	}

	// De focuspunt-kiezer (module Afbeeldingen) slaat in de bewerkmodus niet zelf
	// op, maar meldt elke klik. De foto's schuiven meteen mee; opslaan gebeurt
	// bij Publiceren, net als de rest.
	function followFocus() {
		document.addEventListener( 'hm-toolkit-focus', function ( event ) {
			var id = parseInt( event.detail && event.detail.id, 10 );
			var parts = String( event.detail && event.detail.value ).split( ',' );
			if ( ! id || 2 !== parts.length ) {
				return;
			}
			document.querySelectorAll( 'img[data-hm-value="' + id + '"]' ).forEach( function ( img ) {
				if ( img.style.objectFit ) {
					img.style.objectPosition = parseFloat( parts[ 0 ] ) + '% ' + parseFloat( parts[ 1 ] ) + '%';
				}
			} );
			changes.set( 'focus|' + id, { post: id, key: 'focus', value: event.detail.value } );
			render();
		} );
	}

	function setImage( el, att ) {
		var size = att.sizes && ( att.sizes.large || att.sizes.full );
		var url = size ? size.url : att.url;
		if ( 'IMG' === el.tagName ) {
			el.removeAttribute( 'srcset' );
			el.removeAttribute( 'sizes' );
			el.src = url;
			el.alt = att.alt || '';
		} else {
			el.style.backgroundImage = 'url("' + encodeURI( url ) + '")';
			el.style.backgroundSize = 'cover';
		}
		el.dataset.hmValue = String( att.id );
		record( el, att.id );
	}

	// ---------------------------------------------------------------- Knop (link)

	function openPop( el ) {
		var val = changes.has( key( el ) ) ? changes.get( key( el ) ).value : value( el );
		current.link = el;
		$.popTitle.value = val.title;
		$.popUrl.value = val.url;
		$.popTarget.checked = '_blank' === val.target;
		$.results.hidden = true;
		show( $.pop, el );
		$.popTitle.focus();
	}

	function closePop() {
		hide( $.pop );
		if ( current.link ) {
			current.link.focus();
		}
		current.link = null;
	}

	function applyPop() {
		var el = current.link;
		if ( ! el ) {
			return;
		}
		var val = {
			title: $.popTitle.value.trim(),
			url: $.popUrl.value.trim(),
			target: $.popTarget.checked ? '_blank' : ''
		};
		el.textContent = val.title;
		el.setAttribute( 'href', val.url || '#' );
		if ( val.target ) {
			el.setAttribute( 'target', '_blank' );
		} else {
			el.removeAttribute( 'target' );
		}
		record( el, val );
		closePop();
	}

	function search() {
		var q = $.popUrl.value.trim();
		if ( q.length < 2 || /^(https?:|\/|#|mailto:|tel:)/i.test( q ) ) {
			$.results.hidden = true;
			return;
		}
		fetch( cfg.search + '?per_page=6&type=post&search=' + encodeURIComponent( q ), {
			headers: { 'X-WP-Nonce': cfg.nonce },
			credentials: 'same-origin'
		} )
			.then( function ( response ) {
				return response.ok ? response.json() : [];
			} )
			.then( function ( items ) {
				$.results.textContent = '';
				items.forEach( function ( item ) {
					var button = document.createElement( 'button' );
					var name = document.createElement( 'span' );
					var path = document.createElement( 'span' );
					var url = relative( item.url );
					button.type = 'button';
					button.className = 'res';
					button.dataset.url = url;
					name.textContent = decode( item.title );
					path.className = 'path';
					path.textContent = url;
					button.append( name, path );
					$.results.appendChild( button );
				} );
				$.results.hidden = ! items.length;
			} )
			.catch( function () {
				$.results.hidden = true;
			} );
	}

	function relative( url ) {
		try {
			var parsed = new URL( url, window.location.href );
			return parsed.origin === window.location.origin ? parsed.pathname + parsed.search + parsed.hash : url;
		} catch ( e ) {
			return url;
		}
	}

	function decode( html ) {
		var doc = new DOMParser().parseFromString( String( html ), 'text/html' );
		return doc.documentElement.textContent;
	}

	// ---------------------------------------------------------------- Lijsten (zijpaneel)

	var rowId = 0;
	var TEXTY = [ 'text', 'textarea', 'email', 'url', 'wysiwyg' ];

	function h( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( undefined !== text ) {
			node.textContent = text;
		}
		return node;
	}

	function icon( name ) {
		var span = h( 'span', 'svg' );
		span.innerHTML = ICON[ name ]; // Vaste HTML uit ICON.
		return span;
	}

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	// Haalt scripts en gebeurtenis-attributen uit HTML dat in het paneel of op de pagina komt.
	function cleanHtml( html ) {
		var doc = new DOMParser().parseFromString( '<body>' + html, 'text/html' );
		doc.querySelectorAll( 'script, style, iframe, object, embed, link, meta, form' ).forEach( function ( node ) {
			node.remove();
		} );
		doc.body.querySelectorAll( '*' ).forEach( function ( node ) {
			Array.from( node.attributes ).forEach( function ( attr ) {
				if ( /^on/i.test( attr.name ) || /^\s*javascript:/i.test( attr.value ) ) {
					node.removeAttribute( attr.name );
				}
			} );
		} );
		return doc.body.innerHTML;
	}

	function plain( html ) {
		return new DOMParser().parseFromString( String( html ), 'text/html' ).documentElement.textContent.trim();
	}

	function setupList( desc ) {
		var el = document.querySelector( '[data-hm-list="' + desc.key + '"][data-hm-post="' + desc.post + '"]' );
		if ( ! el ) {
			return;
		}
		var byIndex = {};
		el.querySelectorAll( '[data-hm-row]' ).forEach( function ( rowEl ) {
			if ( rowEl.closest( '[data-hm-list]' ) === el ) {
				byIndex[ rowEl.dataset.hmRow ] = rowEl;
			}
		} );
		var list = { desc: desc, el: el, expanded: null, tail: null };
		list.rows = desc.rows.map( function ( row, i ) {
			return {
				id: ++rowId,
				from: i,
				layout: row.layout,
				values: clone( row.values ),
				images: clone( row.images ),
				touched: {},
				el: byIndex[ i ] || null
			};
		} );
		// Een merkteken achter de laatste rij, om rijen in volgorde terug te zetten.
		var last = list.rows.filter( function ( r ) {
			return r.el;
		} ).pop();
		if ( last ) {
			list.tail = document.createComment( 'hm' );
			last.el.after( list.tail );
		}
		list.original = JSON.stringify( listValue( list ) );

		el.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-hm-field]' ) ) {
				return;
			}
			var rowEl = event.target.closest( '[data-hm-row]' );
			var row = rowEl && list.rows.find( function ( r ) {
				return r.el === rowEl;
			} );
			openPanel( list, row || null );
		} );
		el.addEventListener( 'keydown', function ( event ) {
			if ( event.target === el && ( 'Enter' === event.key || ' ' === event.key ) ) {
				event.preventDefault();
				openPanel( list, null );
			}
		} );
		el.addEventListener( 'mouseenter', function () {
			show( $.chip, el );
		} );
		el.addEventListener( 'mouseleave', function () {
			hide( $.chip );
		} );
	}

	// Alleen wat de gebruiker aanraakte gaat mee; de server haalt de rest uit de oude rij. Een nieuwe rij gaat helemaal mee.
	function listValue( list ) {
		return {
			rows: list.rows.map( function ( r ) {
				var values = {};
				Object.keys( r.values ).forEach( function ( k ) {
					if ( null === r.from || r.touched[ k ] ) {
						values[ k ] = r.values[ k ];
					}
				} );
				return { from: r.from, layout: r.layout, values: values };
			} )
		};
	}

	function listChanged( list ) {
		var k = list.desc.post + '|' + list.desc.key;
		var val = listValue( list );
		if ( JSON.stringify( val ) === list.original ) {
			changes.delete( k );
			list.el.classList.remove( 'hm-te-changed' );
		} else {
			changes.set( k, { post: list.desc.post, key: list.desc.key, value: val } );
			list.el.classList.add( 'hm-te-changed' );
		}
		render();
	}

	function layoutFields( list, row ) {
		var layout = list.desc.layouts[ row.layout ];
		return layout ? layout.fields : [];
	}

	function rowTexts( list, row, i ) {
		var fields = layoutFields( list, row );
		var texts = fields.filter( function ( f ) {
			return TEXTY.indexOf( f.type ) > -1;
		} ).map( function ( f ) {
			return plain( row.values[ f.key ] );
		} ).filter( Boolean );
		var imageField = fields.find( function ( f ) {
			return 'image' === f.type && row.images[ f.key ];
		} );
		var layoutLabel = list.desc.flex && list.desc.layouts[ row.layout ] ? list.desc.layouts[ row.layout ].label : '';
		return {
			title: texts[ 0 ] || layoutLabel || 'Onderdeel ' + ( i + 1 ),
			sub: texts[ 1 ] || ( texts[ 0 ] && layoutLabel ) || ( row.from === null ? 'Nieuw, nog leeg' : 'Nog geen omschrijving' ),
			thumb: imageField ? row.images[ imageField.key ].url : ''
		};
	}

	function openPanel( list, row ) {
		if ( panel.list && panel.list !== list ) {
			closePanel();
		}
		panel.list = list;
		list.el.classList.add( 'hm-te-list-on' );
		if ( row ) {
			list.expanded = row;
		} else if ( ! list.expanded || list.rows.indexOf( list.expanded ) < 0 ) {
			list.expanded = list.desc.flex || 1 === list.rows.length ? list.rows[ 0 ] || null : null;
		}
		$.panel.hidden = false;
		shadow.host.classList.add( 'panel-on' );
		hide( $.chip );
		renderPanel();
		if ( row ) {
			var input = $.plist.querySelector( '.pbody input, .pbody textarea, .pbody [contenteditable]' );
			if ( input ) {
				input.focus( { preventScroll: true } );
			}
		}
	}

	function closePanel() {
		var list = panel.list;
		if ( ! list ) {
			return;
		}
		closeMenu();
		hide( $.tools );
		list.el.classList.remove( 'hm-te-list-on' );
		list.rows.forEach( function ( r ) {
			if ( r.el ) {
				r.el.classList.remove( 'hm-te-row-on', 'hm-te-row-hover' );
			}
		} );
		panel.list = null;
		$.panel.hidden = true;
		shadow.host.classList.remove( 'panel-on' );
	}

	function renderPanel() {
		var list = panel.list;
		var desc = list.desc;
		var n = list.rows.length;
		var sub = n + ( 1 === n ? ' onderdeel' : ' onderdelen' );
		if ( ! desc.flex && n > 1 ) {
			sub += ' · sleep om te ordenen';
		}
		if ( 'option' === desc.post ) {
			sub += ' · staat op elke pagina';
		}
		$.panel.setAttribute( 'aria-label', desc.label + ' bewerken' );
		$.ptitle.textContent = desc.label;
		$.psub.textContent = sub;
		$.padd.hidden = desc.flex;
		$.padd.disabled = desc.max > 0 && n >= desc.max;
		$.padd.title = $.padd.disabled ? 'Maximaal ' + desc.max + ' onderdelen' : '';

		$.plist.textContent = '';
		if ( ! n ) {
			$.plist.appendChild( h( 'p', 'pempty', 'Nog geen onderdelen.' ) );
		}
		list.rows.forEach( function ( row, i ) {
			$.plist.appendChild( buildCard( list, row, i ) );
			if ( row.el ) {
				row.el.classList.toggle( 'hm-te-row-on', row === list.expanded );
			}
		} );
	}

	function buildCard( list, row, i ) {
		var open = row === list.expanded;
		var card = h( 'div', 'pcard' + ( open ? ' open' : '' ) );
		var head = h( 'div', 'phead' );
		var main = h( 'button', 'pmain' );
		var thumb = h( 'span', 'pthumb' );
		var text = h( 'span', 'ptext' );
		var title = h( 'span', 'pt' );
		var sum = h( 'span', 'ps' );
		main.type = 'button';
		main.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		text.append( title, sum );
		main.append( thumb, text );
		head.appendChild( main );
		card.appendChild( head );

		var fill = function () {
			var t = rowTexts( list, row, list.rows.indexOf( row ) );
			title.textContent = t.title;
			sum.textContent = t.sub;
			thumb.style.backgroundImage = t.thumb ? 'url("' + encodeURI( t.thumb ) + '")' : '';
		};
		fill();

		main.addEventListener( 'click', function () {
			list.expanded = open ? null : row;
			if ( list.expanded && row.el ) {
				row.el.scrollIntoView( { block: 'center', behavior: 'smooth' } );
			}
			renderPanel();
		} );
		card.addEventListener( 'mouseenter', function () {
			if ( row.el ) {
				row.el.classList.add( 'hm-te-row-hover' );
			}
		} );
		card.addEventListener( 'mouseleave', function () {
			if ( row.el ) {
				row.el.classList.remove( 'hm-te-row-hover' );
			}
		} );

		if ( ! list.desc.flex ) {
			var more = h( 'button', 'ico' );
			more.type = 'button';
			more.setAttribute( 'aria-label', 'Meer acties' );
			more.setAttribute( 'aria-haspopup', 'menu' );
			more.appendChild( icon( 'dots' ) );
			more.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				openMenu( list, row, more );
			} );
			head.appendChild( more );
		}
		if ( open ) {
			var chevron = h( 'button', 'ico' );
			chevron.type = 'button';
			chevron.setAttribute( 'aria-label', 'Inklappen' );
			chevron.appendChild( icon( 'up' ) );
			chevron.addEventListener( 'click', function () {
				list.expanded = null;
				renderPanel();
			} );
			head.appendChild( chevron );
		} else if ( ! list.desc.flex ) {
			head.appendChild( dragHandle( list, card, row ) );
		}

		if ( open ) {
			var body = h( 'div', 'pbody' );
			layoutFields( list, row ).forEach( function ( f ) {
				body.appendChild( buildField( list, row, f, fill ) );
			} );
			if ( ! body.children.length ) {
				body.appendChild( h( 'p', 'hint', 'In dit onderdeel staat niets dat hier te bewerken is.' ) );
			}
			card.appendChild( body );
		}
		return card;
	}

	function touch( list, row, f, fill ) {
		row.touched[ f.key ] = true;
		fill();
		liveUpdate( row, f );
		listChanged( list );
	}

	function buildField( list, row, f, fill ) {
		var wrap = h( 'div', 'pfield' );
		var id = 'hm-f-' + row.id + '-' + f.key;
		var label = h( 'label', 'lbl', f.label );
		var control;
		label.htmlFor = id;
		wrap.appendChild( label );

		if ( 'image' === f.type ) {
			label.removeAttribute( 'for' );
			wrap.appendChild( buildImage( list, row, f, fill ) );
			return wrap;
		}
		if ( 'link' === f.type ) {
			label.removeAttribute( 'for' );
			wrap.appendChild( buildLink( list, row, f, fill ) );
			return wrap;
		}
		if ( 'wysiwyg' === f.type ) {
			control = h( 'div', 'inp rich' );
			control.contentEditable = 'true';
			control.setAttribute( 'role', 'textbox' );
			control.setAttribute( 'aria-multiline', 'true' );
			control.setAttribute( 'aria-labelledby', id + '-l' );
			label.id = id + '-l';
			label.removeAttribute( 'for' );
			control.innerHTML = cleanHtml( row.values[ f.key ] || '' ); // Opgeschoond hierboven.
			control.hmEdited = function () {
				row.values[ f.key ] = control.innerHTML;
				touch( list, row, f, fill );
			};
			control.addEventListener( 'input', control.hmEdited );
			control.addEventListener( 'paste', pastePlain );
			control.addEventListener( 'focus', function () {
				document.execCommand( 'defaultParagraphSeparator', false, 'p' );
				current.tools = control;
				$.linkform.hidden = true;
				show( $.tools, control );
			} );
			control.addEventListener( 'blur', function () {
				setTimeout( function () {
					if ( ! ( shadow.activeElement && ( $.tools.contains( shadow.activeElement ) || shadow.activeElement === control ) ) ) {
						hide( $.tools );
					}
				}, 150 );
			} );
		} else {
			control = h( 'textarea' === f.type ? 'textarea' : 'input', 'inp' );
			control.id = id;
			if ( 'textarea' === f.type ) {
				control.rows = 3;
			} else {
				control.type = 'email' === f.type ? 'email' : 'text';
			}
			control.value = row.values[ f.key ] || '';
			control.addEventListener( 'input', function () {
				row.values[ f.key ] = control.value;
				touch( list, row, f, fill );
			} );
		}
		wrap.appendChild( control );
		return wrap;
	}

	function buildImage( list, row, f, fill ) {
		var box = h( 'div', 'pimg' );
		var thumb = h( 'span', 'pimgthumb' );
		var name = h( 'span', 'pimgname' );
		var pick = h( 'button', 'b' );
		var clear = h( 'button', 'b' );
		pick.type = clear.type = 'button';
		clear.textContent = 'Wissen';
		var paint = function () {
			var img = row.images[ f.key ];
			thumb.style.backgroundImage = img && img.url ? 'url("' + encodeURI( img.url ) + '")' : '';
			name.textContent = img ? img.name : 'Geen afbeelding';
			pick.textContent = img ? 'Vervangen' : 'Kiezen';
			clear.hidden = ! img;
		};
		paint();
		pick.addEventListener( 'click', function () {
			chooseImage( row.values[ f.key ], false, function ( att ) {
				var small = att.sizes && att.sizes.thumbnail;
				var big = att.sizes && ( att.sizes.large || att.sizes.full );
				row.values[ f.key ] = att.id;
				row.images[ f.key ] = { url: small ? small.url : att.url, big: big ? big.url : att.url, name: att.filename, alt: att.alt || '' };
				paint();
				touch( list, row, f, fill );
			} );
		} );
		clear.addEventListener( 'click', function () {
			row.values[ f.key ] = 0;
			delete row.images[ f.key ];
			paint();
			touch( list, row, f, fill );
		} );
		box.append( thumb, name, pick, clear );
		return box;
	}

	function buildLink( list, row, f, fill ) {
		var box = h( 'div', 'plink' );
		var title = h( 'input', 'inp' );
		var url = h( 'input', 'inp' );
		var tgl = h( 'label', 'toggle' );
		var check = h( 'input' );
		var val = row.values[ f.key ] || { title: '', url: '', target: '' };
		title.type = url.type = 'text';
		title.setAttribute( 'aria-label', f.label + ': tekst' );
		url.setAttribute( 'aria-label', f.label + ': link' );
		url.placeholder = '/contact of https://…';
		title.placeholder = 'Tekst';
		check.type = 'checkbox';
		title.value = val.title;
		url.value = val.url;
		check.checked = '_blank' === val.target;
		tgl.append( h( 'span', null, 'Openen in een nieuw tabblad' ), check );
		var update = function () {
			row.values[ f.key ] = { title: title.value.trim(), url: url.value.trim(), target: check.checked ? '_blank' : '' };
			touch( list, row, f, fill );
		};
		[ title, url ].forEach( function ( input ) {
			input.addEventListener( 'input', update );
		} );
		check.addEventListener( 'change', update );
		box.append( title, url, tgl );
		return box;
	}

	// Tekst en afbeeldingen veranderen meteen op de pagina; een nieuwe rij heeft daar nog geen plek.
	function liveUpdate( row, f ) {
		var target = row.el && row.el.querySelector( '[data-hm-sub="' + f.key + '"]' );
		var val = row.values[ f.key ];
		if ( ! target ) {
			return;
		}
		if ( 'image' === f.type ) {
			var img = row.images[ f.key ];
			if ( 'IMG' === target.tagName && img ) {
				target.removeAttribute( 'srcset' );
				target.removeAttribute( 'sizes' );
				target.src = img.big || img.url;
				target.alt = img.alt || '';
			}
		} else if ( 'link' === f.type ) {
			target.textContent = val.title;
			target.setAttribute( 'href', val.url || '#' );
			if ( val.target ) {
				target.setAttribute( 'target', '_blank' );
			} else {
				target.removeAttribute( 'target' );
			}
		} else if ( 'wysiwyg' === f.type ) {
			target.innerHTML = cleanHtml( val ); // Opgeschoond hierboven.
		} else {
			target.textContent = val;
		}
	}

	// ---------------------------------------------------------------- Rijen: volgorde, dupliceren, verwijderen

	function syncDom( list ) {
		var parent = list.tail && list.tail.parentNode;
		if ( ! parent ) {
			return;
		}
		list.rows.forEach( function ( r ) {
			if ( r.el && r.el.parentNode === parent ) {
				parent.insertBefore( r.el, list.tail );
			}
		} );
	}

	function afterStructure( list ) {
		syncDom( list );
		listChanged( list );
		renderPanel();
	}

	function moveRow( list, from, to ) {
		if ( to < 0 || to >= list.rows.length || from === to ) {
			return;
		}
		list.rows.splice( to, 0, list.rows.splice( from, 1 )[ 0 ] );
		afterStructure( list );
	}

	function duplicateRow( list, i ) {
		var src = list.rows[ i ];
		if ( list.desc.max > 0 && list.rows.length >= list.desc.max ) {
			return;
		}
		var copy = {
			id: ++rowId,
			from: src.from,
			layout: src.layout,
			values: clone( src.values ),
			images: clone( src.images ),
			touched: clone( src.touched ),
			el: null
		};
		if ( src.el ) {
			copy.el = src.el.cloneNode( true );
			copy.el.classList.remove( 'hm-te-row-on', 'hm-te-row-hover' );
			src.el.after( copy.el );
		}
		list.rows.splice( i + 1, 0, copy );
		list.expanded = copy;
		afterStructure( list );
	}

	function removeRow( list, i ) {
		if ( list.desc.min > 0 && list.rows.length <= list.desc.min ) {
			return;
		}
		if ( ! window.confirm( 'Dit onderdeel verwijderen? Het verdwijnt pas echt na Publiceren.' ) ) {
			return;
		}
		var row = list.rows.splice( i, 1 )[ 0 ];
		if ( row.el ) {
			row.el.remove();
		}
		if ( list.expanded === row ) {
			list.expanded = null;
		}
		afterStructure( list );
	}

	function addRow( list ) {
		if ( list.desc.flex || ( list.desc.max > 0 && list.rows.length >= list.desc.max ) ) {
			return;
		}
		var row = { id: ++rowId, from: null, layout: '', values: {}, images: {}, touched: {}, el: null };
		layoutFields( list, row ).forEach( function ( f ) {
			row.values[ f.key ] = 'link' === f.type ? { title: '', url: '', target: '' } : ( 'image' === f.type ? 0 : '' );
		} );
		list.rows.push( row );
		list.expanded = row;
		afterStructure( list );
		toast( 'Het nieuwe onderdeel verschijnt op de pagina na Publiceren.', false );
	}

	function dragHandle( list, card, row ) {
		var grip = h( 'button', 'ico grip' );
		grip.type = 'button';
		grip.setAttribute( 'aria-label', 'Verslepen' );
		grip.appendChild( icon( 'grip' ) );
		grip.addEventListener( 'pointerdown', function () {
			card.draggable = true;
		} );
		card.addEventListener( 'dragstart', function ( event ) {
			drag.row = row;
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData( 'text/plain', String( row.id ) );
			card.classList.add( 'dragging' );
		} );
		card.addEventListener( 'dragend', function () {
			card.draggable = false;
			drag.row = null;
			$.plist.querySelectorAll( '.dragging, .drop-before, .drop-after' ).forEach( function ( node ) {
				node.classList.remove( 'dragging', 'drop-before', 'drop-after' );
			} );
		} );
		card.addEventListener( 'dragover', function ( event ) {
			if ( ! drag.row || drag.row === row ) {
				return;
			}
			event.preventDefault();
			var rect = card.getBoundingClientRect();
			var after = event.clientY > rect.top + rect.height / 2;
			card.classList.toggle( 'drop-after', after );
			card.classList.toggle( 'drop-before', ! after );
		} );
		card.addEventListener( 'dragleave', function () {
			card.classList.remove( 'drop-before', 'drop-after' );
		} );
		card.addEventListener( 'drop', function ( event ) {
			if ( ! drag.row || drag.row === row ) {
				return;
			}
			event.preventDefault();
			var rect = card.getBoundingClientRect();
			var from = list.rows.indexOf( drag.row );
			var to = list.rows.indexOf( row ) + ( event.clientY > rect.top + rect.height / 2 ? 1 : 0 );
			moveRow( list, from, to > from ? to - 1 : to );
		} );
		return grip;
	}

	function openMenu( list, row, button ) {
		var i = list.rows.indexOf( row );
		var items = [
			[ 'Dupliceren', ! ( list.desc.max > 0 && list.rows.length >= list.desc.max ), function () {
				duplicateRow( list, i );
			} ],
			[ 'Omhoog', i > 0, function () {
				moveRow( list, i, i - 1 );
			} ],
			[ 'Omlaag', i < list.rows.length - 1, function () {
				moveRow( list, i, i + 1 );
			} ],
			[ 'Verwijderen', ! ( list.desc.min > 0 && list.rows.length <= list.desc.min ), function () {
				removeRow( list, i );
			}, true ]
		];
		closeMenu();
		$.menu.textContent = '';
		$.menu.setAttribute( 'aria-label', 'Acties voor onderdeel ' + ( i + 1 ) );
		items.forEach( function ( item ) {
			var b = h( 'button', 'b' + ( item[ 3 ] ? ' danger' : '' ), item[ 0 ] );
			b.type = 'button';
			b.setAttribute( 'role', 'menuitem' );
			b.disabled = ! item[ 1 ];
			b.addEventListener( 'click', function () {
				closeMenu( true );
				item[ 2 ]();
			} );
			$.menu.appendChild( b );
		} );
		var p = $.panel.getBoundingClientRect();
		var r = button.getBoundingClientRect();
		$.menu.hidden = false;
		$.menu.style.top = Math.round( Math.min( r.bottom - p.top + 4, p.height - $.menu.offsetHeight - 8 ) ) + 'px';
		$.menu.style.right = Math.round( p.right - r.right ) + 'px';
		panel.menuButton = button;
		var first = $.menu.querySelector( 'button:not(:disabled)' );
		if ( first ) {
			first.focus();
		}
	}

	function closeMenu( skipFocus ) {
		if ( $.menu.hidden ) {
			return;
		}
		$.menu.hidden = true;
		if ( ! skipFocus && panel.menuButton && panel.menuButton.isConnected ) {
			panel.menuButton.focus();
		}
		panel.menuButton = null;
	}

	// ---------------------------------------------------------------- Balk

	function render() {
		var count = changes.size;
		var options = 0;
		changes.forEach( function ( change ) {
			if ( 'option' === change.post ) {
				options++;
			}
		} );
		var text = 0 === count ? 'Geen wijzigingen' : count + ( 1 === count ? ' wijziging' : ' wijzigingen' );
		if ( options ) {
			text += ', ' + options + ' op elke pagina';
		}
		$.statusText.textContent = text;
		$.bar.classList.toggle( 'dirty', count > 0 );
		$.cancel.textContent = count ? 'Annuleren' : 'Stoppen';
		$.publish.disabled = 0 === count || publishing;
	}

	function cancel() {
		if ( changes.size && ! window.confirm( 'Je wijzigingen weggooien? Ze zijn nog niet gepubliceerd.' ) ) {
			return;
		}
		changes.clear();
		window.location.reload();
	}

	function publish() {
		if ( ! changes.size || publishing ) {
			return;
		}
		publishing = true;
		$.publish.textContent = 'Publiceren…';
		render();

		fetch( cfg.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( { changes: Array.from( changes.values() ) } )
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					if ( ! response.ok ) {
						throw new Error( data && data.message ? data.message : 'Publiceren is niet gelukt.' );
					}
					return data;
				} );
			} )
			.then( function ( data ) {
				changes.clear();
				toast( data.cache ? 'Gepubliceerd. De cache van deze pagina is geleegd.' : 'Gepubliceerd.', false );
				setTimeout( function () {
					window.location.reload();
				}, 1200 );
			} )
			.catch( function ( error ) {
				publishing = false;
				$.publish.textContent = 'Publiceren';
				render();
				toast( error.message || 'Publiceren is niet gelukt.', true );
			} );
	}

	function toast( message, isError ) {
		$.toast.textContent = '';
		if ( ! isError ) {
			$.toast.insertAdjacentHTML( 'afterbegin', ICON.check );
		}
		$.toast.append( document.createTextNode( message ) );
		$.toast.classList.toggle( 'error', isError );
		$.toast.hidden = false;
		clearTimeout( toast.timer );
		toast.timer = setTimeout( function () {
			$.toast.hidden = true;
		}, isError ? 8000 : 4000 );
	}

	// ---------------------------------------------------------------- Zwevende lagen

	function show( layer, el ) {
		if ( layer === $.tools ) {
			hide( $.chip ); // De werkbalk staat al op die plek.
		}
		if ( layer === $.chip && anchors.get( $.tools ) === el ) {
			return;
		}
		if ( layer === $.chip ) {
			layer.textContent = el.dataset.hmLabel || '';
			if ( isOption( el ) ) {
				layer.textContent += ' · Elke pagina';
			}
			layer.classList.toggle( 'opt', isOption( el ) );
		}
		anchors.set( layer, el );
		layer.hidden = false;
		position( layer, el );
	}

	function hide( layer ) {
		layer.hidden = true;
		anchors.delete( layer );
	}

	function position( layer, el ) {
		var rect = el.getBoundingClientRect();
		var box = layer.getBoundingClientRect();
		var top;
		var left = rect.left;

		if ( layer === $.imgtools ) {
			top = rect.bottom - box.height - 12;
			left = rect.right - box.width - 12;
		} else if ( layer === $.pop ) {
			top = rect.bottom + 12;
			if ( top + box.height > window.innerHeight - 90 ) {
				top = Math.max( 12, rect.top - box.height - 12 );
			}
		} else if ( layer === $.warn ) {
			top = rect.top - box.height - 40;
			if ( top < 12 ) {
				top = rect.bottom + 16;
			}
		} else {
			// Werkbalk en label boven het veld.
			top = rect.top - box.height - ( layer === $.chip ? 8 : 12 );
			if ( top < 8 ) {
				top = rect.bottom + 8;
			}
		}

		left = Math.max( 8, Math.min( left, window.innerWidth - box.width - 8 ) );
		layer.style.top = Math.round( top ) + 'px';
		layer.style.left = Math.round( left ) + 'px';
	}

	function place() {
		anchors.forEach( function ( el, layer ) {
			position( layer, el );
		} );
	}

	// ---------------------------------------------------------------- Overig

	function onKey( event ) {
		if ( 'Escape' !== event.key || document.body.classList.contains( 'modal-open' ) ) {
			return;
		}
		if ( ! $.pop.hidden ) {
			closePop();
		} else if ( ! $.linkform.hidden ) {
			$.linkform.hidden = true;
		} else if ( ! $.menu.hidden ) {
			closeMenu();
		} else if ( panel.list ) {
			closePanel();
		}
	}

	// In de bewerkmodus brengen links je niet naar een andere pagina.
	function blockNavigation( event ) {
		var link = event.target.closest && event.target.closest( 'a' );
		if ( link && ! link.hasAttribute( 'data-hm-field' ) ) {
			event.preventDefault();
		}
	}

	function warnUnload( event ) {
		if ( changes.size && ! publishing ) {
			event.preventDefault();
			event.returnValue = '';
		}
	}

	function debounce( fn, wait ) {
		var timer;
		return function () {
			clearTimeout( timer );
			timer = setTimeout( fn, wait );
		};
	}
}() );
