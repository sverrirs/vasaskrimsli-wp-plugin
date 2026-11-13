/*
 * Download counter (block "Námsefni: Niðurhalsteljari"): reports a person's click on a download link (/s/1) or a
 * link to a ZIP file on this site to the plugin, which counts it (includes/Rest.php). A click by the left or the middle button counts; a script's
 * click or a browser driven by automation does not. One report per file and page every five seconds at most.
 */
( function () {
	var c = window.vsNidurhal;
	if ( ! c || navigator.webdriver ) {
		return;
	}
	var sent = {};
	function report( e ) {
		if ( ! e.isTrusted || ( 'auxclick' === e.type && 1 !== e.button ) ) {
			return;
		}
		var a = e.target && e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if ( ! a ) {
			return;
		}
		var u;
		try {
			u = new URL( a.href, location.href );
		} catch ( x ) {
			return;
		}
		if ( u.hostname.replace( /^www\./, '' ) !== location.hostname.replace( /^www\./, '' ) || ! ( /\.zip$/i.test( u.pathname ) || /^\/s\/[^/]+\/?$/.test( u.pathname ) || u.searchParams.has( 'vs_saekja' ) ) ) {
			return;
		}
		var now = Date.now();
		if ( sent[ u.pathname ] && now - sent[ u.pathname ] < 5000 ) {
			return;
		}
		sent[ u.pathname ] = now;
		var body = JSON.stringify( { slod: u.href, t: c.t } );
		var ok = false;
		try {
			ok = navigator.sendBeacon && navigator.sendBeacon( c.slod, new Blob( [ body ], { type: 'application/json' } ) );
		} catch ( x ) {}
		if ( ! ok ) {
			fetch( c.slod, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'omit' } ).catch( function () {} );
		}
	}
	document.addEventListener( 'click', report, true );
	document.addEventListener( 'auxclick', report, true );
} )();
