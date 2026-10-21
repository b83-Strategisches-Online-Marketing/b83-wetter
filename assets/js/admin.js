/**
 * b83 Wetter – Backend
 * Farbwähler, Live-Vorschau, Ortssuche, Shortcodes kopieren.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.b83WetterAdmin || {};
	var i18n = cfg.i18n || {};

	/* ------------------------------------------------------------------
	 * Shortcodes kopieren
	 * ---------------------------------------------------------------- */

	$( document ).on( 'click', '.b83-wetter-copy', function () {
		var btn = this;
		var text = btn.getAttribute( 'data-copy' ) || '';
		var label = btn.textContent;
		var done = function () {
			btn.textContent = i18n.copied || 'Kopiert!';
			setTimeout( function () {
				btn.textContent = label;
			}, 1500 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
		} else {
			var tmp = document.createElement( 'textarea' );
			tmp.value = text;
			document.body.appendChild( tmp );
			tmp.select();
			document.execCommand( 'copy' );
			document.body.removeChild( tmp );
			done();
		}
	} );

	/* ------------------------------------------------------------------
	 * Ortssuche
	 * ---------------------------------------------------------------- */

	var geoBtn = document.getElementById( 'b83-wetter-geo-btn' );
	var geoQ = document.getElementById( 'b83-wetter-geo-q' );
	var geoMsg = document.querySelector( '.b83-wetter-geocode__msg' );

	function setMsg( text, ok ) {
		if ( ! geoMsg ) {
			return;
		}
		geoMsg.textContent = text;
		geoMsg.className = 'b83-wetter-geocode__msg ' + ( ok === true ? 'is-ok' : ok === false ? 'is-error' : '' );
	}

	function geocode() {
		var q = geoQ ? geoQ.value.trim() : '';
		if ( ! q ) {
			setMsg( i18n.empty, false );
			return;
		}
		setMsg( i18n.searching );
		geoBtn.disabled = true;
		$.post( cfg.ajaxUrl, { action: 'b83_wetter_geocode', _ajax_nonce: cfg.nonce, q: q } )
			.done( function ( res ) {
				if ( res && res.success && res.data ) {
					$( '#b83-wetter-lat' ).val( res.data.lat );
					$( '#b83-wetter-lng' ).val( res.data.lng );
					var name = $( '#b83-wetter-name' );
					if ( ! name.val() ) {
						name.val( res.data.name );
					}
					setMsg( i18n.found + ( res.data.display ? ' (' + res.data.display + ')' : '' ), true );
				} else {
					setMsg( i18n.notFound, false );
				}
			} )
			.fail( function () {
				setMsg( i18n.notFound, false );
			} )
			.always( function () {
				geoBtn.disabled = false;
			} );
	}

	if ( geoBtn ) {
		geoBtn.addEventListener( 'click', geocode );
		geoQ.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				geocode();
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Design: Farbwähler und Live-Vorschau
	 * ---------------------------------------------------------------- */

	var form = document.getElementById( 'b83-wetter-design-form' );
	if ( ! form ) {
		return;
	}
	var stage = form.querySelector( '.b83-wetter-preview__stage' );
	var timer = null;
	var request = null;

	function updatePreview() {
		clearTimeout( timer );
		timer = setTimeout( function () {
			if ( request ) {
				request.abort();
			}
			var data = $( form ).serializeArray().filter( function ( f ) {
				return f.name.indexOf( 'design[' ) === 0;
			} );
			data.push( { name: 'action', value: 'b83_wetter_preview' } );
			data.push( { name: '_ajax_nonce', value: cfg.nonce } );
			stage.classList.add( 'is-loading' );
			request = $.post( cfg.ajaxUrl, $.param( data ) )
				.done( function ( res ) {
					if ( res && res.success && res.data ) {
						stage.innerHTML = res.data.html;
					}
				} )
				.always( function () {
					stage.classList.remove( 'is-loading' );
					request = null;
				} );
		}, 250 );
	}

	$( form ).find( 'input.b83-wetter-color' ).each( function () {
		var input = this;
		$( input ).wpColorPicker( {
			change: function () {
				setTimeout( updatePreview, 0 );
			},
			clear: function () {
				setTimeout( updatePreview, 0 );
			}
		} );
	} );

	$( form ).on( 'change input', 'select, input[type=number], input[type=checkbox], input[type=text]:not(.b83-wetter-color)', updatePreview );

	// Hintergrund der Vorschau umschalten (z. B. für eine dunkle Kopfzeile).
	$( form ).on( 'click', '.b83-wetter-preview__bg button', function () {
		$( this ).addClass( 'is-active' ).siblings().removeClass( 'is-active' );
		stage.setAttribute( 'data-bg', this.getAttribute( 'data-bg' ) );
	} );
} )( jQuery );
