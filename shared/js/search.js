/**
 * Icelandic-aware search helpers shared by the plugin's apps.
 *
 * fold() lowercases and removes accents so "ord" finds "orð", "thyding" finds "þýðing"
 * and "aefi" finds "æfi". Both the query and the text are folded the same way.
 */

const SPECIAL = { ð: 'd', þ: 'th', æ: 'ae', ö: 'o', ø: 'o', œ: 'oe', ß: 'ss' };

export function fold( text ) {
	return String( text ?? '' )
		.toLowerCase()
		.replace( /[ðþæöøœß]/g, ( c ) => SPECIAL[ c ] )
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' );
}

/** Plain text of an HTML fragment (tags removed, entities decoded, <br> as a space). */
export function htmlToText( html ) {
	return String( html ?? '' )
		.replace( /<br\s*\/?>/gi, ' ' )
		.replace( /<[^>]+>/g, '' )
		.replace( /&(#\d+|#x[0-9a-f]+|[a-z]+);/gi, ( m, e ) => {
			const named = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };
			if ( e[ 0 ] === '#' ) {
				return String.fromCodePoint( e[ 1 ] === 'x' || e[ 1 ] === 'X' ? parseInt( e.slice( 2 ), 16 ) : parseInt( e.slice( 1 ), 10 ) );
			}
			return named[ e.toLowerCase() ] ?? m;
		} )
		.replace( /\s+/g, ' ' )
		.trim();
}

/** Every word of the query must appear somewhere in the folded haystack. */
export function matches( haystack, query ) {
	const words = fold( query ).split( /\s+/ ).filter( Boolean );
	return words.every( ( w ) => haystack.includes( w ) );
}
