/**
 * The visitor's collection on the Skrímslaskrá page (up to MAX monsters, kept in this browser only) and the
 * text helpers for its PDF cards. No DOM here, so it is tested in node (tests/js/safn.test.js).
 */

export const MAX = 20;
export const KEY = 'vasaskrimsli-safn';

/**
 * Adds numer to the list, or removes it when it is already there.
 * @return {{ list: string[], full: boolean }} full: numer was not added because the list already has MAX.
 */
export function toggle( list, numer ) {
	const n = String( numer );
	if ( list.includes( n ) ) {
		return { list: list.filter( ( x ) => x !== n ), full: false };
	}
	if ( list.length >= MAX ) {
		return { list, full: true };
	}
	return { list: [ ...list, n ], full: false };
}

/** The saved list, or [] when storage is blocked, empty or holds something else. */
export function load( storage ) {
	try {
		const v = JSON.parse( storage?.getItem( KEY ) ?? '[]' );
		return Array.isArray( v ) ? [ ...new Set( v.map( String ) ) ].slice( 0, MAX ) : [];
	} catch {
		return [];
	}
}

export function save( storage, list ) {
	try {
		storage?.setItem( KEY, JSON.stringify( list ) );
	} catch {
		// Private window or blocked storage: the collection still works until the page is closed.
	}
}

const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', ndash: '–', mdash: '—', hellip: '…', bdquo: '„', ldquo: '“', rdquo: '”' };

export function decode( s ) {
	return s.replace( /&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, ( m, e ) => {
		if ( e[ 0 ] === '#' ) {
			const code = e[ 1 ] === 'x' || e[ 1 ] === 'X' ? parseInt( e.slice( 2 ), 16 ) : parseInt( e.slice( 1 ), 10 );
			return Number.isFinite( code ) ? String.fromCodePoint( code ) : m;
		}
		return ENTITIES[ e.toLowerCase() ] ?? m;
	} );
}

/**
 * Stored HTML (strong, em, br, a, sup, sub) as lines of styled runs: [[{ t, b, i }]].
 * <br> and block ends start a new line; links and other tags keep only their text.
 */
export function parseRich( html ) {
	const lines = [ [] ];
	let b = 0;
	let i = 0;
	for ( const tok of String( html ?? '' ).split( /(<[^>]*>)/ ) ) {
		if ( ! tok ) {
			continue;
		}
		const tag = tok.match( /^<\s*(\/)?\s*([a-z0-9]+)/i );
		if ( tag ) {
			const close = Boolean( tag[ 1 ] );
			const name = tag[ 2 ].toLowerCase();
			if ( name === 'strong' || name === 'b' ) {
				b = Math.max( 0, b + ( close ? -1 : 1 ) );
			} else if ( name === 'em' || name === 'i' ) {
				i = Math.max( 0, i + ( close ? -1 : 1 ) );
			} else if ( name === 'br' || ( close && /^(p|div|li)$/.test( name ) ) ) {
				lines.push( [] );
			}
			continue;
		}
		const t = decode( tok ).replace( /\s+/g, ' ' );
		if ( t ) {
			lines[ lines.length - 1 ].push( { t, b: b > 0, i: i > 0 } );
		}
	}
	return lines
		.map( ( runs ) => {
			// Trim the line's outer spaces.
			const out = runs.map( ( r ) => ( { ...r } ) );
			while ( out.length && ! out[ 0 ].t.trimStart() ) {
				out.shift();
			}
			while ( out.length && ! out[ out.length - 1 ].t.trimEnd() ) {
				out.pop();
			}
			if ( out.length ) {
				out[ 0 ].t = out[ 0 ].t.trimStart();
				out[ out.length - 1 ].t = out[ out.length - 1 ].t.trimEnd();
			}
			return out;
		} )
		.filter( ( runs ) => runs.length );
}

export const plainLine = ( runs ) => runs.map( ( r ) => r.t ).join( '' );

const fold = ( s ) => s.toLowerCase().normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).replace( /[^a-z0-9þðæø]/g, '' );

/**
 * Splits Þýðing into the lines that explain the name's parts (their label starts like a part of
 * Upprunaorð: "Klaki:" for "Klaka—gæs") and the fun facts (Náttúra:, Goðafræði:, Íslenska: ...).
 * Unlabelled lines go with the section before them.
 */
export function sections( thydingHtml, upprunaHtml ) {
	const parts = plainLine( parseRich( upprunaHtml ).flat() )
		.split( /[\s—–\-·|/+]+/ )
		.map( fold )
		.filter( ( p ) => p.length >= 2 );
	const out = { ordsifjar: [], frodleikur: [] };
	let last = 'ordsifjar';
	for ( const line of parseRich( thydingHtml ) ) {
		const m = plainLine( line ).match( /^([^:]{1,28}):/ );
		if ( m ) {
			const label = fold( m[ 1 ] );
			const explains = ( p ) => {
				const n = Math.min( 3, label.length, p.length ); // "Sær:" explains "Sæ".
				return n >= 2 && p.slice( 0, n ) === label.slice( 0, n );
			};
			last = parts.some( explains ) ? 'ordsifjar' : 'frodleikur';
		}
		out[ last ].push( line );
	}
	return out;
}

/** "905" -> 905, "351a" -> 351. */
export const national = ( numer ) => parseInt( String( numer ), 10 );
