/**
 * The visitor's collection on the Skrímslaskrá page (up to MAX cards: monsters and their forms, kept in
 * this browser only) and the text helpers for its PDF cards. No DOM here, so it is tested in node
 * (tests/js/safn.test.js).
 */

export const MAX = 50;
export const KEY = 'vasaskrimsli-safn';

/**
 * An item of the list is the monster's Númer ("26"), or Númer and the id of one of its forms
 * ("26:raichu-alolan"). A monster can be in the list several times, once as each of its forms.
 */
export const itemOf = ( numer, form = '' ) => ( form ? `${ numer }:${ form }` : String( numer ) );

/** "26:raichu-alolan" -> { numer: "26", form: "raichu-alolan" }; "26" -> { numer: "26", form: "" }. */
export function split( item ) {
	const i = item.indexOf( ':' );
	return i < 0 ? { numer: item, form: '' } : { numer: item.slice( 0, i ), form: item.slice( i + 1 ) };
}

/** The list's items for numer, in list order. */
export const itemsOf = ( list, numer ) => list.filter( ( x ) => split( x ).numer === String( numer ) );

/**
 * One click on a monster: adds the first of the monster itself and its forms (forms: their ids, in order)
 * that is not in the list yet, after the monster's other items. Clicks never take anything out.
 * @return {{ list: string[], full: boolean, all: boolean }} full: nothing added because the list already
 *         has MAX; all: nothing added because the monster and all its forms are in it.
 */
export function add( list, numer, forms = [] ) {
	const n = String( numer );
	const next = [ n, ...forms.map( ( f ) => itemOf( n, f ) ) ].find( ( x ) => ! list.includes( x ) );
	if ( next === undefined ) {
		return { list, full: false, all: true };
	}
	return insert( list, next );
}

/** One click on one form's tile (the Mega or Gígamax view): adds that form only, same results as add(). */
export function addForm( list, numer, form ) {
	const item = itemOf( numer, form );
	return list.includes( item ) ? { list, full: false, all: true } : insert( list, item );
}

function insert( list, item ) {
	if ( list.length >= MAX ) {
		return { list, full: true, all: false };
	}
	const n = split( item ).numer;
	const last = list.findLastIndex( ( x ) => split( x ).numer === n );
	const at = last < 0 ? list.length : last + 1;
	return { list: [ ...list.slice( 0, at ), item, ...list.slice( at ) ], full: false, all: false };
}

/** The views of the Skrímslaskrá grid: every monster, or only its Mega or Gígamax forms (by English name). */
export const SYNIR = {
	mega: { heiti: 'Mega', er: ( f ) => /^Mega\b/.test( f.enska ?? '' ) },
	gigamax: { heiti: 'Gígamax', er: ( f ) => /^(Gigantamax|Eternamax)\b/.test( f.enska ?? '' ) },
};

/** The tiles of one view: entries as they are, or one tile per matching form (variant() plus base and form). */
export function tiles( entries, syn ) {
	if ( ! SYNIR[ syn ] ) {
		return entries;
	}
	return entries.flatMap( ( e ) => ( e.formir ?? [] ).filter( SYNIR[ syn ].er ).map( ( f ) => ( { ...variant( e, f.id ), base: e, form: f.id } ) ) );
}

/** Takes one item out of the list. */
export const remove = ( list, item ) => list.filter( ( x ) => x !== item );

/**
 * The entry as the collection shows it: the chosen form's name and picture in place of the monster's.
 * Undefined when the form is no longer in the entry's forms.
 */
export function variant( entry, form ) {
	if ( ! form ) {
		return entry;
	}
	const i = ( entry.formir ?? [] ).findIndex( ( f ) => f.id === form );
	if ( i < 0 ) {
		return undefined;
	}
	const f = entry.formir[ i ];
	return { ...entry, nafn: f.heiti, enska: f.enska || entry.enska, mynd: f.mynd, mynd_stor: f.mynd, forma: { ...f, nr: i + 1, grunn: entry.nafn } };
}

/** The saved list, or [] when storage is blocked, empty or holds something else. */
export function load( storage ) {
	try {
		const v = JSON.parse( storage?.getItem( KEY ) ?? '[]' );
		if ( ! Array.isArray( v ) ) {
			return [];
		}
		return [ ...new Set( v.map( String ) ) ].slice( 0, MAX );
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
 * Upprunaorð: "Klaki:" for "Klaka—gæs"), the fun facts (Náttúra:, Goðafræði:, Íslenska: ...) and the
 * Fjölskylda: line. Unlabelled lines go with the section before them.
 */
export function sections( thydingHtml, upprunaHtml ) {
	const parts = plainLine( parseRich( upprunaHtml ).flat() )
		.split( /[\s—–\-·|/+]+/ )
		.map( fold )
		.filter( ( p ) => p.length >= 2 );
	const out = { ordsifjar: [], frodleikur: [], fjolskylda: [] };
	let last = 'ordsifjar';
	for ( const line of parseRich( thydingHtml ) ) {
		const m = plainLine( line ).match( /^([^:]{1,28}):/ );
		if ( m ) {
			const label = fold( m[ 1 ] );
			const explains = ( p ) => {
				const n = Math.min( 3, label.length, p.length ); // "Sær:" explains "Sæ".
				return n >= 2 && p.slice( 0, n ) === label.slice( 0, n );
			};
			if ( label === 'fjolskylda' ) {
				last = 'fjolskylda';
			} else {
				last = parts.some( explains ) ? 'ordsifjar' : 'frodleikur';
			}
		}
		out[ last ].push( line );
	}
	return out;
}

/**
 * Þýðing with the monster's own name in bold on the Fjölskylda line ("Þríbý → Býdrottning (#416)"),
 * so the reader sees where in the line it sits. Only text outside tags is touched.
 */
export function boldSelf( thydingHtml, nafn ) {
	const name = String( nafn ?? '' ).trim();
	if ( ! thydingHtml || ! name || name === '–' ) {
		return thydingHtml;
	}
	const esc = name.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	const word = new RegExp( `(?<!\\p{L})${ esc }(?!\\p{L})`, 'gu' );
	return String( thydingHtml )
		.split( /(<br\s*\/?>|<\/p>)/i )
		.map( ( line ) => ( /Fjölskylda\s*:/.test( line )
			? line.replace( /(Fjölskylda\s*:(?:\s*<\/(?:strong|b)>)?)([\s\S]*)/, ( m, head, rest ) => head + rest
				.split( /(<[^>]*>)/ )
				.map( ( tok ) => ( tok.startsWith( '<' ) ? tok : tok.replace( word, '<strong>$&</strong>' ) ) )
				.join( '' ) )
			: line ) )
		.join( '' );
}

/** The share of a circle's radius left free round its picture, the same as in the book. */
const HRING_BIL = 0.06;

/** A sprite's row in data/midjur.json, found from its URL: [ cx, cy, r ], or undefined. */
export const midjaOf = ( midjur, src ) => midjur?.[ String( src ?? '' ).match( /\/sprites\/(.+?)\.webp/ )?.[ 1 ] ];

/**
 * Where to draw a picture of w x h so it fills the circle: its own midpoint in the middle, its farthest point a
 * little inside the rim, as the book does (i_hring in vasaskrimsli-ordsifjaskra). Without a midpoint, the file centred.
 * @param {number[]|undefined} midja Its row in data/midjur.json.
 * @return {{ x: number, y: number, w: number, h: number }}
 */
export function iHring( midja, w, h, cx, cy, radius ) {
	const [ mx, my, r ] = midja ?? [ 0.5, 0.5, 0.5 ];
	const k = ( radius * ( 1 - HRING_BIL ) ) / ( r * Math.max( w, h ) );
	return { x: cx - mx * w * k, y: cy - my * h * k, w: w * k, h: h * k };
}

/** "905" -> 905, "351a" -> 351. */
export const national = ( numer ) => parseInt( String( numer ), 10 );
