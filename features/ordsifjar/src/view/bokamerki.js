/**
 * The collection's bookmarks: A4 landscape sheets of five, 52 x 190 mm each with cut marks, drawn on a canvas
 * (300 dpi) and written by pdf.js. Loaded only when a visitor downloads (dynamic import in Safn.jsx).
 *
 * Bookmark, top to bottom: a hole for a ribbon, the picture in a round window, the number, the Icelandic name
 * down the length between stitched lines, and a foot with the types. The background is a gradient in the colours
 * of the monster's types (a form's own types when it has them) with each type's motif scattered over it.
 * All drawing is in millimetres.
 */

import { htmlToText } from '../../../../shared/js/search.js';
import { BLACK, TYPE, WHITE, css, fetchJson, loadImage, mix, rgb, toJpeg } from './kort.js';
import { makePdf } from './pdf.js';
import { iHring, midjaOf, national } from './safn.js';

export const PER_SHEET = 5;
const A4 = { width: 841.89, height: 595.28 }; // Landscape, in PDF points.
const SHEET_W = 297;
const SHEET_H = 210;
const DPMM = 300 / 25.4;
const B_W = 52;
const B_H = 190;
const GAP = 3;

/** The types' names as the printed books have them. */
const TEGUND = {
	Normal: 'Algengur', Fire: 'Eldur', Water: 'Vatn', Electric: 'Rafmagn', Grass: 'Gras', Ice: 'Frost',
	Fighting: 'Áflog', Poison: 'Eitur', Ground: 'Jörð', Flying: 'Flýgur', Psychic: 'Sálrænn', Bug: 'Padda',
	Rock: 'Grjót', Ghost: 'Draugur', Dragon: 'Drekakyn', Dark: 'Sorti', Steel: 'Stál', Fairy: 'Álfakyn',
};

/** A star of `points` tips around the middle of the 24 unit box. */
function star( points, outer, inner ) {
	let d = '';
	for ( let i = 0; i < points * 2; i++ ) {
		const r = i % 2 ? inner : outer;
		const a = ( i * Math.PI ) / points;
		d += `${ i ? 'L' : 'M' }${ ( 12 + r * Math.cos( a ) ).toFixed( 2 ) } ${ ( 12 + r * Math.sin( a ) ).toFixed( 2 ) }`;
	}
	return `${ d }z`;
}

/** Three crossing bars. */
function flake() {
	let d = '';
	for ( let k = 0; k < 3; k++ ) {
		const c = Math.cos( ( k * Math.PI ) / 3 );
		const s = Math.sin( ( k * Math.PI ) / 3 );
		d += [ [ -1.1, -10.5 ], [ 1.1, -10.5 ], [ 1.1, 10.5 ], [ -1.1, 10.5 ] ]
			.map( ( [ x, y ], i ) => `${ i ? 'L' : 'M' }${ ( 12 + x * c - y * s ).toFixed( 2 ) } ${ ( 12 + x * s + y * c ).toFixed( 2 ) }` )
			.join( '' ) + 'z';
	}
	return d;
}

/** One motif per type, as SVG paths in a 24 unit box, filled with the even-odd rule. */
const MOTIF = {
	Normal: 'M12 2l3 7 7 .6-5.3 4.6 1.6 7L12 17l-6.3 3.8 1.6-7L2 9.6 9 9z',
	Fire: 'M12 2c1 4 6 6 6 12a6 6 0 0 1-12 0c0-3 2-4 2-7 2 1 3 2 3 4 1-3 1-6 1-9z',
	Water: 'M12 2c4 6 7 9 7 13a7 7 0 0 1-14 0c0-4 3-7 7-13z',
	Electric: 'M14 2 5 14h6l-2 8 10-13h-6z',
	Grass: 'M4 20C4 9 11 3 21 3c0 11-6 18-15 18l-2 1z',
	Ice: flake(),
	Fighting: star( 8, 11, 5.5 ),
	Poison: 'M8 12a5.5 5.5 0 1 0 .01 0zM16.5 4a3.5 3.5 0 1 0 .01 0zM18 15a2.5 2.5 0 1 0 .01 0z',
	Ground: 'M2 20 9 7l4 6 3-4 6 11z',
	Flying: 'M3 20C5 10 12 4 22 3c-1 4-3 6-5 7 1 0 2 0 3-1-2 4-5 6-8 6 1 0 2 0 3-.5C12 18 8 19 5 18l-2 2z',
	Psychic: 'M2 12C6 5.5 18 5.5 22 12 18 18.5 6 18.5 2 12zM12 8.5a3.5 3.5 0 1 0 .01 0z',
	Bug: 'M12 2l8.7 5v10L12 22l-8.7-5V7zM12 7l-4.3 2.5v5L12 17l4.3-2.5v-5z',
	Rock: 'M7 4l9-1 6 8-3 9-11 2-6-7z',
	Ghost: 'M12 2a8 8 0 0 0-8 8v12l3-2 2.5 2 2.5-2 2.5 2 2.5-2 3 2V10a8 8 0 0 0-8-8zM9 8a1.8 1.8 0 1 0 .01 0zM15 8a1.8 1.8 0 1 0 .01 0z',
	Dragon: 'M12 2c6 3 9 8 9 14-3-1-5-1-6 1l-3 5-3-5c-1-2-3-2-6-1 0-6 3-11 9-14z',
	Dark: 'M16 2a10 10 0 1 0 6 16A9 9 0 0 1 16 2z',
	Steel: 'M7 3h10l5 9-5 9H7l-5-9zM12 8a4 4 0 1 0 .01 0z',
	Fairy: 'M12 1l2.5 8.5L23 12l-8.5 2.5L12 23l-2.5-8.5L1 12l8.5-2.5z',
};

/**
 * Splits the list into sheets.
 * @return {Object[][]} At most PER_SHEET items on each.
 */
export function sheets( items, per = PER_SHEET ) {
	const out = [];
	for ( let i = 0; i < items.length; i += per ) {
		out.push( items.slice( i, i + per ) );
	}
	return out;
}

/** The left edge of each bookmark on a sheet, in mm, the full row centred. */
export const columns = () => Array.from( { length: PER_SHEET }, ( _, i ) => ( SHEET_W - PER_SHEET * B_W - ( PER_SHEET - 1 ) * GAP ) / 2 + i * ( B_W + GAP ) );

/**
 * The name's lines and size so it fits down the bookmark: one line when that stays large enough, otherwise the
 * split into two that allows the largest letters. A name that can not be split gets as small as it must.
 * @param {string}   name
 * @param {number}   length  The room, in mm.
 * @param {Function} measure Width of a text at size 1.
 * @return {{ lines: string[], size: number }}
 */
export function nameLines( name, length, measure, { max = 21, twoMax = 13, least = 11 } = {} ) {
	const one = Math.min( max, length / measure( name ) );
	const words = name.split( ' ' );
	if ( one >= least || words.length < 2 ) {
		return { lines: [ name ], size: one };
	}
	let best = null;
	for ( let i = 1; i < words.length; i++ ) {
		const lines = [ words.slice( 0, i ).join( ' ' ), words.slice( i ).join( ' ' ) ];
		const size = Math.min( twoMax, ...lines.map( ( l ) => length / measure( l ) ) );
		if ( ! best || size > best.size ) {
			best = { lines, size };
		}
	}
	return best;
}

function motif( ctx, type, x, y, size, turn = 0 ) {
	ctx.save();
	ctx.translate( x, y );
	ctx.rotate( turn );
	ctx.scale( size / 24, size / 24 );
	ctx.translate( -12, -12 );
	ctx.fill( new Path2D( MOTIF[ type ] ?? MOTIF.Normal ), 'evenodd' );
	ctx.restore();
}

function palette( types ) {
	const a = rgb( TYPE[ types[ 0 ] ] ?? '#9aa7b8' );
	const b = TYPE[ types[ 1 ] ] ? rgb( TYPE[ types[ 1 ] ] ) : mix( a, BLACK, 0.25 );
	return { a, top: mix( a, WHITE, 0.6 ), bottom: mix( b, WHITE, 0.08 ), dark: mix( a, BLACK, 0.42 ), soft: mix( a, WHITE, 0.85 ) };
}

/** Draws one bookmark with its top left corner at x, y. */
function drawBookmark( ctx, { numer, name, types, picture, midja }, x, y, fam ) {
	const font = ( size, weight = 700 ) => `${ weight } ${ size }px ${ fam }`;
	const colors = palette( types );
	const mid = B_W / 2;
	ctx.save();
	ctx.translate( x, y );
	ctx.beginPath();
	ctx.rect( 0, 0, B_W, B_H );
	ctx.clip();

	const bg = ctx.createLinearGradient( 0, 0, 0, B_H );
	bg.addColorStop( 0, css( colors.top ) );
	bg.addColorStop( 0.55, css( mix( colors.top, colors.bottom, 0.6 ) ) );
	bg.addColorStop( 1, css( colors.bottom ) );
	ctx.fillStyle = bg;
	ctx.fillRect( 0, 0, B_W, B_H );

	// The motifs, placed the same way every time for the same monster.
	let seed = national( numer ) || 1;
	const next = () => ( seed = ( seed * 16807 ) % 2147483647 ) / 2147483647;
	for ( let i = 0; i < 5; i++ ) {
		next();
	}
	ctx.fillStyle = css( WHITE, 0.2 );
	for ( let row = 0; row < 17; row++ ) {
		for ( let col = 0; col < 5; col++ ) {
			const type = types.length ? types[ ( row + col ) % types.length ] : '';
			motif( ctx, type, ( col + ( row % 2 ? 0 : 0.5 ) ) * 12.5, row * 12 + 2, 6 + next() * 3, ( next() - 0.5 ) * 1.1 );
		}
	}

	// Picture window.
	const cy = 37;
	const R = 23.5;
	ctx.fillStyle = css( WHITE, 0.92 );
	ctx.beginPath();
	ctx.arc( mid, cy, R, 0, Math.PI * 2 );
	ctx.fill();
	ctx.strokeStyle = css( colors.dark );
	ctx.lineWidth = 0.8;
	ctx.stroke();
	ctx.setLineDash( [ 0.1, 1.5 ] );
	ctx.lineCap = 'round';
	ctx.lineWidth = 0.6;
	ctx.strokeStyle = css( colors.a );
	ctx.beginPath();
	ctx.arc( mid, cy, R - 1.6, 0, Math.PI * 2 );
	ctx.stroke();
	ctx.setLineDash( [] );
	const glow = ctx.createRadialGradient( mid, cy, 2, mid, cy, R - 2 );
	glow.addColorStop( 0, css( colors.soft ) );
	glow.addColorStop( 1, css( colors.soft, 0 ) );
	ctx.fillStyle = glow;
	ctx.beginPath();
	ctx.arc( mid, cy, R - 2, 0, Math.PI * 2 );
	ctx.fill();
	if ( picture ) {
		ctx.imageSmoothingQuality = 'high';
		const at = iHring( midja, picture.naturalWidth, picture.naturalHeight, mid, cy, R - 1.6 );
		ctx.drawImage( picture, at.x, at.y, at.w, at.h );
	}

	// Hole for a ribbon.
	ctx.fillStyle = '#fff';
	ctx.strokeStyle = css( colors.dark );
	ctx.lineWidth = 0.35;
	ctx.setLineDash( [ 0.7, 0.6 ] );
	ctx.beginPath();
	ctx.arc( mid, 6.5, 2.2, 0, Math.PI * 2 );
	ctx.fill();
	ctx.stroke();
	ctx.setLineDash( [] );

	// Number.
	const num = `#${ numer }`;
	ctx.font = font( 6.2 );
	const nw = ctx.measureText( num ).width + 7;
	ctx.fillStyle = css( colors.dark );
	ctx.beginPath();
	ctx.roundRect( mid - nw / 2, 57.5, nw, 9, 4.5 );
	ctx.fill();
	ctx.fillStyle = '#fff';
	ctx.textAlign = 'center';
	ctx.textBaseline = 'middle';
	ctx.fillText( num, mid, 62.3 );

	// Stitches down both sides, a motif at each end.
	const y0 = 71;
	const y1 = 166;
	ctx.strokeStyle = css( WHITE, 0.85 );
	ctx.lineWidth = 0.55;
	ctx.lineCap = 'butt';
	ctx.setLineDash( [ 2.2, 1.6 ] );
	for ( const sx of [ 5.5, B_W - 5.5 ] ) {
		ctx.beginPath();
		ctx.moveTo( sx, y0 + 5 );
		ctx.lineTo( sx, y1 - 5 );
		ctx.stroke();
	}
	ctx.setLineDash( [] );
	ctx.fillStyle = css( WHITE, 0.95 );
	for ( const sx of [ 5.5, B_W - 5.5 ] ) {
		motif( ctx, types[ 0 ], sx, y0, 5 );
		motif( ctx, types[ types.length - 1 ], sx, y1, 5 );
	}

	// The name, down the length.
	ctx.save();
	ctx.translate( mid, ( y0 + y1 ) / 2 );
	ctx.rotate( Math.PI / 2 );
	ctx.font = font( 100, 800 );
	const fit = nameLines( name, y1 - y0 - 2, ( t ) => ctx.measureText( t ).width / 100 );
	ctx.font = font( fit.size, 800 );
	ctx.lineJoin = 'round';
	fit.lines.forEach( ( line, i ) => {
		const ly = ( i - ( fit.lines.length - 1 ) / 2 ) * fit.size * 1.08 + fit.size * 0.03;
		ctx.strokeStyle = css( colors.dark );
		ctx.lineWidth = fit.size * 0.17;
		ctx.strokeText( line, 0, ly );
		ctx.fillStyle = '#fff';
		ctx.fillText( line, 0, ly );
	} );
	ctx.restore();

	// Foot.
	ctx.fillStyle = css( colors.dark );
	ctx.beginPath();
	ctx.moveTo( 0, 173 );
	ctx.quadraticCurveTo( B_W * 0.25, 169, B_W * 0.5, 173 );
	ctx.quadraticCurveTo( B_W * 0.75, 177, B_W, 173 );
	ctx.lineTo( B_W, B_H );
	ctx.lineTo( 0, B_H );
	ctx.fill();
	ctx.fillStyle = '#fff';
	ctx.font = font( 3.9, 600 );
	const known = types.filter( ( t ) => TEGUND[ t ] );
	const widths = known.map( ( t ) => ctx.measureText( TEGUND[ t ] ).width + 5.5 );
	let tx = mid - ( widths.reduce( ( a, b ) => a + b, 0 ) + ( known.length - 1 ) * 3 ) / 2;
	ctx.textAlign = 'left';
	known.forEach( ( t, i ) => {
		motif( ctx, t, tx + 2.3, 179.5, 4.6 );
		ctx.fillText( TEGUND[ t ], tx + 5.5, 179.7 );
		tx += widths[ i ] + 3;
	} );
	ctx.textAlign = 'center';
	ctx.font = font( 2.7, 400 );
	ctx.fillStyle = css( WHITE, 0.8 );
	ctx.fillText( 'vasaskrimsli.is', mid, 185.7 );
	ctx.restore();
}

/** Cut marks above and below each side of a bookmark, in the margins. */
function drawCutMarks( ctx, x, y ) {
	ctx.strokeStyle = '#777';
	ctx.lineWidth = 0.15;
	for ( const mx of [ x, x + B_W ] ) {
		for ( const [ from, to ] of [ [ y - 1, y - 3.5 ], [ y + B_H + 1, y + B_H + 3.5 ] ] ) {
			ctx.beginPath();
			ctx.moveTo( mx, from );
			ctx.lineTo( mx, to );
			ctx.stroke();
		}
	}
}

/**
 * Builds the PDF of the chosen entries' bookmarks.
 * @param {Object[]} chosen    Entries in collection order.
 * @param {string}   throunUrl URL of throun.json.
 * @param {string}   midjurUrl URL of midjur.json.
 * @param {string}   fam       CSS font family of the page.
 * @param {Function} progress  Called with the number of bookmarks done.
 * @return {Promise<Blob>}
 */
export async function buildBookmarks( chosen, throunUrl, midjurUrl, fam, progress = () => {} ) {
	// Bookmarks without types or midpoints are still useful.
	const [ throun, midjur ] = await Promise.all( [ fetchJson( throunUrl ), fetchJson( midjurUrl ) ] );
	try {
		await Promise.all( [ 400, 600, 800 ].map( ( wgt ) => document.fonts?.load( `${ wgt } 40px ${ fam }`, 'ÞÆÐÖþæðö' ) ) );
	} catch {
		// Falls back to whatever font is available.
	}

	const canvas = document.createElement( 'canvas' );
	canvas.width = Math.round( SHEET_W * DPMM );
	canvas.height = Math.round( SHEET_H * DPMM );
	const ctx = canvas.getContext( '2d' );
	ctx.scale( canvas.width / SHEET_W, canvas.height / SHEET_H );
	const xs = columns();
	const y = ( SHEET_H - B_H ) / 2;
	const pages = [];
	let done = 0;
	for ( const sheet of sheets( chosen ) ) {
		ctx.fillStyle = '#fff';
		ctx.fillRect( 0, 0, SHEET_W, SHEET_H );
		for ( let i = 0; i < sheet.length; i++ ) {
			const e = sheet[ i ];
			const nafn = htmlToText( e.nafn );
			drawBookmark( ctx, {
				numer: e.numer,
				name: nafn && nafn !== '–' ? nafn : htmlToText( e.enska ),
				types: e.forma?.tegundir?.length ? e.forma.tegundir : throun[ national( e.numer ) ]?.t ?? [],
				picture: await loadImage( e.mynd_stor || e.mynd ),
				midja: midjaOf( midjur, e.mynd_stor || e.mynd ),
			}, xs[ i ], y, fam );
			drawCutMarks( ctx, xs[ i ], y );
			progress( ++done );
		}
		pages.push( await toJpeg( canvas ) );
	}
	return new Blob( [ makePdf( pages, { title: 'Vasaskrímsli: bókamerki', page: A4 } ) ], { type: 'application/pdf' } );
}
