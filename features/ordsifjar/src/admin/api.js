const config = JSON.parse( document.getElementById( 'vs-ordsifjar-admin-config' )?.textContent || '{}' );

export { config };

/** Calls the plugin's REST API. Throws an Error with the server's Icelandic message on failure. */
export async function api( path = '', { method = 'GET', body, form } = {} ) {
	const res = await fetch( config.rest + path, {
		method,
		credentials: 'same-origin',
		headers: { 'X-WP-Nonce': config.nonce, ...( body ? { 'Content-Type': 'application/json' } : {} ) },
		body: form ?? ( body ? JSON.stringify( body ) : undefined ),
	} );
	let data = null;
	try {
		data = await res.json();
	} catch {
		// Non-JSON response (PHP fatal, proxy error page).
	}
	if ( ! res.ok ) {
		throw new Error( data?.message || `Villa frá vefþjóni (${ res.status }).` );
	}
	return data;
}
