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

	var ICON = {
		pencil: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2271b1" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
		link: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>',
		ul: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>',
		ol: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 6h10M10 12h10M10 18h10"/><path d="M4 4h1v4M3.5 8h2.5M3.5 14.5c.5-1 2.5-1 2.5.3 0 1-2.5 1.7-2.5 3.2h2.5"/></svg>',
		image: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>',
		focus: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>',
		close: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		globe: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#996800" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>',
		check: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#68de7c" stroke-width="2.6" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>'
	};

	// Alleen vaste HTML; alles wat van de site komt gaat via textContent of value.
	var TEMPLATE =
		'<link rel="stylesheet" href="">' +
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

		[ 'bar', 'tools', 'chip', 'warn', 'imgtools', 'pop', 'toast', 'linkform', 'results' ].forEach( function ( name ) {
			$[ name ] = shadow.querySelector( '.' + name );
		} );
		$.statusText = shadow.querySelector( '.status-text' );
		$.cancel = shadow.querySelector( '[data-act="cancel"]' );
		$.publish = shadow.querySelector( '[data-act="publish"]' );
		$.linkInput = $.linkform.querySelector( 'input' );
		$.popTitle = shadow.getElementById( 'hm-te-title' );
		$.popUrl = shadow.getElementById( 'hm-te-url' );
		$.popTarget = $.pop.querySelector( 'input[type="checkbox"]' );
		shadow.querySelector( '.title' ).textContent = cfg.title || '';
		shadow.querySelector( '[data-act="focus"]' ).hidden = ! cfg.focus;

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
			var selection = document.getSelection();
			savedRange = selection.rangeCount ? selection.getRangeAt( 0 ).cloneRange() : null;
			$.linkform.hidden = false;
			$.linkInput.value = '';
			$.linkInput.focus();
			return;
		}
		document.execCommand( button.dataset.cmd, false, null );
		record( current.tools );
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
			var selection = document.getSelection();
			selection.removeAllRanges();
			selection.addRange( savedRange );
		}
		if ( url ) {
			document.execCommand( 'createLink', false, url );
		} else {
			document.execCommand( 'unlink', false, null );
		}
		record( el );
	}

	// ---------------------------------------------------------------- Afbeeldingen

	function openMedia( el, focusOnly ) {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		var id = parseInt( el.dataset.hmValue, 10 ) || 0;
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
				return; // Het focuspunt slaat het mediavenster zelf op.
			}
			var att = frame.state().get( 'selection' ).first().toJSON();
			setImage( el, att );
		} );
		frame.open();
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
		if ( 'Escape' !== event.key ) {
			return;
		}
		if ( ! $.pop.hidden ) {
			closePop();
		} else if ( ! $.linkform.hidden ) {
			$.linkform.hidden = true;
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
