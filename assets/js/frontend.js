/**
 * b83 Wetter – lädt veraltete Widgets nach.
 *
 * Steht eine Seite länger im Seiten-Cache als das Aktualisierungsintervall,
 * holt sich das Widget das aktuelle HTML von der eigenen Website
 * (REST-Endpunkt). Besucher verbinden sich nie mit einem Wetterdienst.
 */
( function () {
	'use strict';

	var cfg = window.b83Wetter || {};
	if ( ! cfg.endpoint || ! window.fetch ) {
		return;
	}

	var pending = {};

	function refresh( el ) {
		var view = el.getAttribute( 'data-b83w-view' ) || 'kompakt';
		var args = el.getAttribute( 'data-b83w-args' ) || '';
		var key = view + '|' + args;
		var url = cfg.endpoint + ( cfg.endpoint.indexOf( '?' ) === -1 ? '?' : '&' ) +
			'view=' + encodeURIComponent( view ) + '&args=' + encodeURIComponent( args );

		if ( ! pending[ key ] ) {
			pending[ key ] = window.fetch( url, { credentials: 'omit' } ).then( function ( res ) {
				return res.ok ? res.json() : null;
			} );
		}

		pending[ key ].then( function ( data ) {
			if ( ! data || typeof data.html !== 'string' || data.html.indexOf( 'data-b83w-stand' ) === -1 ) {
				return;
			}
			var tpl = document.createElement( 'template' );
			tpl.innerHTML = data.html.trim();
			var fresh = tpl.content.firstElementChild;
			if ( ! fresh ) {
				return;
			}
			// Nur ersetzen, wenn die neuen Daten wirklich neuer sind.
			var oldStand = parseInt( el.getAttribute( 'data-b83w-stand' ), 10 ) || 0;
			var newStand = parseInt( fresh.getAttribute( 'data-b83w-stand' ), 10 ) || 0;
			if ( newStand > oldStand && el.parentNode ) {
				el.parentNode.replaceChild( fresh, el );
			}
		} ).catch( function () {} );
	}

	function check() {
		var now = Date.now() / 1000;
		var widgets = document.querySelectorAll( '[data-b83w-stand]' );
		Array.prototype.forEach.call( widgets, function ( el ) {
			var stand = parseInt( el.getAttribute( 'data-b83w-stand' ), 10 ) || 0;
			var age = parseInt( el.getAttribute( 'data-b83w-age' ), 10 ) || 4200;
			if ( stand > 0 && now - stand > age ) {
				refresh( el );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', check );
	} else {
		check();
	}
} )();
