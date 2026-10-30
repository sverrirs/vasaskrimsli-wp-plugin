/**
 * The collection's PDF: one A6 card per monster, drawn on a canvas (300 dpi) and written by pdf.js.
 * Loaded only when a visitor downloads (dynamic import in Safn.jsx).
 *
 * Card, top to bottom: number and generation, the picture on a glow, Icelandic name, English name,
 * Upprunaorð, a panel with the name's parts and the fun facts from Þýðing, and the evolution line.
 * The background is a gradient in the colours of the monster's types (data/throun.json).
 */

import { htmlToText } from '../../../../shared/js/search.js';
import { makePdf } from './pdf.js';
import { national, parseRich, plainLine, sections } from './safn.js';

const W = 1240; // A6 at 300 dpi.
const H = 1748;
const M = 64;
const INK = '#1f1b24';

const TYPE = {
	Normal: '#a8a77a', Fire: '#ee8130', Water: '#6390f0', Electric: '#f7d02c', Grass: '#7ac74c', Ice: '#96d9d6',
	Fighting: '#c22e28', Poison: '#a33ea1', Ground: '#e2bf65', Flying: '#a98ff3', Psychic: '#f95587', Bug: '#a6b91a',
	Rock: '#b6a136', Ghost: '#735797', Dragon: '#6f35fc', Dark: '#705746', Steel: '#b7b7ce', Fairy: '#d685ad',
};

const rgb = ( hex ) => [ 1, 3, 5 ].map( ( i ) => parseInt( hex.slice( i, i + 2 ), 16 ) );
const css = ( c, a = 1 ) => `rgba(${ c.map( Math.round ).join( ',' ) },${ a })`;
/** t = 0 gives a, t = 1 gives b. */
const mix = ( a, b, t ) => a.map( ( v, i ) => v + ( b[ i ] - v ) * t );
const WHITE = [ 255, 255, 255 ];
const BLACK = [ 0, 0, 0 ];

function palette( types ) {
	const a = rgb( TYPE[ types?.[ 0 ] ] ?? '#9aa7b8' );
	const b = TYPE[ types?.[ 1 ] ] ? rgb( TYPE[ types[ 1 ] ] ) : mix( a, BLACK, 0.25 );
	return { top: mix( a, WHITE, 0.55 ), bottom: mix( b, WHITE, 0.12 ), accent: mix( a, BLACK, 0.35 ), soft: mix( a, WHITE, 0.82 ) };
}

function loadImage( src ) {
	return new Promise( ( resolve ) => {
		if ( ! src ) {
			resolve( null );
			return;
		}
		const img = new Image();
		img.crossOrigin = 'anonymous';
		img.onload = () => resolve( img );
		img.onerror = () => resolve( null );
		img.src = src;
	} );
}

function roundRect( ctx, x, y, w, h, r ) {
	ctx.beginPath();
	ctx.roundRect( x, y, w, h, r );
}

const fontOf = ( fam, size, { b = false, i = false } = {} ) => `${ i ? 'italic ' : '' }${ b ? 700 : 400 } ${ size }px ${ fam }`;

/** Rich runs wrapped to width: lines of { t, r, x }. */
function wrap( ctx, runs, width, size, fam ) {
	const lines = [ [] ];
	let x = 0;
	for ( const r of runs ) {
		ctx.font = fontOf( fam, size, r );
		for ( const tok of r.t.split( /(\s+)/ ) ) {
			if ( ! tok ) {
				continue;
			}
			if ( /^\s+$/.test( tok ) ) {
				if ( x > 0 ) {
					lines[ lines.length - 1 ].push( { t: ' ', r, x } );
					x += ctx.measureText( ' ' ).width;
				}
				continue;
			}
			const w = ctx.measureText( tok ).width;
			if ( x > 0 && x + w > width ) {
				const line = lines[ lines.length - 1 ];
				while ( line.length && line[ line.length - 1 ].t === ' ' ) {
					line.pop();
				}
				lines.push( [] );
				x = 0;
			}
			lines[ lines.length - 1 ].push( { t: tok, r, x } );
			x += w;
		}
	}
	return lines;
}

function lineWidth( ctx, line, size, fam ) {
	if ( ! line.length ) {
		return 0;
	}
	const last = line[ line.length - 1 ];
	ctx.font = fontOf( fam, size, last.r );
	return last.x + ctx.measureText( last.t ).width;
}

function drawLine( ctx, line, x, y, size, fam ) {
	for ( const s of line ) {
		ctx.font = fontOf( fam, size, s.r );
		ctx.fillText( s.t, x + s.x, y );
	}
}

/** Largest size from `max` down that keeps a single line of text within width. */
function fitSize( ctx, text, width, max, min, fam, style ) {
	let size = max;
	for ( ; size > min; size -= 2 ) {
		ctx.font = fontOf( fam, size, style );
		if ( ctx.measureText( text ).width <= width ) {
			break;
		}
	}
	return size;
}

/** The text panel: name parts, then fun facts in a tinted box. Returns the height used at `size`. */
function textBlock( ctx, parts, width, size, fam, colors, draw, x0 = 0, y0 = 0 ) {
	const lh = size * 1.32;
	const gap = size * 0.55;
	let y = y0;
	for ( const line of parts.ordsifjar ) {
		for ( const l of wrap( ctx, line, width, size, fam ) ) {
			if ( draw ) {
				ctx.fillStyle = INK;
				drawLine( ctx, l, x0, y + size, size, fam );
			}
			y += lh;
		}
		y += gap * 0.4;
	}
	if ( parts.frodleikur.length ) {
		if ( parts.ordsifjar.length ) {
			y += gap;
		}
		const pad = size * 0.7;
		const head = size * 0.8;
		const lines = parts.frodleikur.map( ( line ) => wrap( ctx, line, width - pad * 2, size, fam ) );
		const h = pad * 2 + head * 1.5 + lines.reduce( ( a, ls ) => a + ls.length * lh + gap * 0.4, 0 );
		if ( draw ) {
			roundRect( ctx, x0 - pad * 0.3, y, width + pad * 0.6, h, size * 0.8 );
			ctx.fillStyle = css( colors.soft );
			ctx.fill();
			ctx.fillStyle = css( colors.accent );
			ctx.font = fontOf( fam, head, { b: true } );
			ctx.fillText( 'VISSIR ÞÚ?', x0 + pad * 0.7, y + pad + head );
		}
		let yy = y + pad + head * 1.5;
		for ( const ls of lines ) {
			for ( const l of ls ) {
				if ( draw ) {
					ctx.fillStyle = INK;
					drawLine( ctx, l, x0 + pad * 0.7, yy + size, size, fam );
				}
				yy += lh;
			}
			yy += gap * 0.4;
		}
		y += h;
	}
	return y - y0;
}

/** Evolution line inside the box x, y, w, h. */
function drawEvolution( ctx, stages, current, x, y, w, h, fam, colors, images ) {
	const arrow = 56;
	// Stages with several branches (Eevee) get wider columns.
	const weights = stages.map( ( st ) => Math.ceil( Math.sqrt( st.length ) ) );
	const unit = ( w - arrow * ( stages.length - 1 ) ) / weights.reduce( ( a, b ) => a + b, 0 );
	let cx = x;
	stages.forEach( ( stage, si ) => {
		const colW = unit * weights[ si ];
		const cols = Math.ceil( Math.sqrt( stage.length * ( colW / h ) ) );
		const rows = Math.ceil( stage.length / cols );
		const label = Math.max( 22, Math.min( 30, h / rows / 6 ) );
		const cell = Math.min( colW / cols, h / rows );
		const img = Math.min( 170, cell - label * 1.5 );
		stage.forEach( ( n, k ) => {
			const c = k % cols;
			const r = Math.floor( k / cols );
			const rowCount = r === rows - 1 ? stage.length - r * cols : cols;
			const ix = cx + ( colW - rowCount * cell ) / 2 + c * cell + ( cell - img ) / 2;
			const iy = y + ( h - rows * cell ) / 2 + r * cell + ( cell - img - label * 1.3 ) / 2;
			ctx.save();
			ctx.beginPath();
			ctx.arc( ix + img / 2, iy + img / 2, img / 2, 0, Math.PI * 2 );
			ctx.fillStyle = n === current ? css( WHITE, 0.95 ) : css( WHITE, 0.55 );
			ctx.fill();
			if ( n === current ) {
				ctx.lineWidth = 6;
				ctx.strokeStyle = css( colors.accent );
				ctx.stroke();
			}
			ctx.restore();
			const pic = images.get( n );
			if ( pic ) {
				ctx.drawImage( pic, ix + img * 0.08, iy + img * 0.08, img * 0.84, img * 0.84 );
			}
			ctx.fillStyle = n === current ? css( colors.accent ) : INK;
			ctx.font = fontOf( fam, label, { b: n === current } );
			ctx.textAlign = 'center';
			ctx.fillText( `#${ String( n ).padStart( 3, '0' ) }`, ix + img / 2, iy + img + label * 1.15 );
			ctx.textAlign = 'left';
		} );
		cx += colW;
		if ( si < stages.length - 1 ) {
			ctx.fillStyle = css( colors.accent, 0.8 );
			ctx.beginPath();
			const ay = y + h / 2 - 10;
			ctx.moveTo( cx + 16, ay - 18 );
			ctx.lineTo( cx + arrow - 14, ay );
			ctx.lineTo( cx + 16, ay + 18 );
			ctx.closePath();
			ctx.fill();
			cx += arrow;
		}
	} );
}

/**
 * Draws one card.
 * @param {Object} entry  The entry as shown on the page.
 * @param {Object} info   Its row in throun.json: { t: types, k: stages }.
 * @param {Map} images    National number -> loaded picture (256 px).
 * @param {string} fam    CSS font family.
 */
export function drawCard( canvas, entry, info, images, fam ) {
	const ctx = canvas.getContext( '2d' );
	const colors = palette( info?.t );
	const num = national( entry.numer );

	const bg = ctx.createLinearGradient( 0, 0, W * 0.4, H );
	bg.addColorStop( 0, css( colors.top ) );
	bg.addColorStop( 1, css( colors.bottom ) );
	ctx.fillStyle = bg;
	ctx.fillRect( 0, 0, W, H );
	// Soft circles in the corners, a little like the book pages.
	for ( const [ cx, cy, r, a ] of [ [ W * 0.95, H * 0.06, 300, 0.22 ], [ W * 0.02, H * 0.52, 240, 0.14 ], [ W * 0.9, H * 0.98, 360, 0.12 ] ] ) {
		ctx.beginPath();
		ctx.arc( cx, cy, r, 0, Math.PI * 2 );
		ctx.fillStyle = css( WHITE, a );
		ctx.fill();
	}

	// Number and generation.
	ctx.textBaseline = 'alphabetic';
	const numText = `#${ String( entry.numer ).padStart( 4, '0' ) }`;
	ctx.font = fontOf( fam, 46, { b: true } );
	const nw = ctx.measureText( numText ).width;
	roundRect( ctx, M, M, nw + 56, 76, 38 );
	ctx.fillStyle = css( WHITE, 0.85 );
	ctx.fill();
	ctx.fillStyle = css( colors.accent );
	ctx.fillText( numText, M + 28, M + 54 );
	const right = [ entry.kynslod ? `${ entry.kynslod }. kynslóð` : '', entry.serstakt || '' ].filter( Boolean ).join( ' · ' );
	if ( right ) {
		ctx.font = fontOf( fam, 34, { b: true } );
		ctx.fillStyle = css( rgb( INK ), 0.7 );
		ctx.textAlign = 'right';
		ctx.fillText( right.toUpperCase(), W - M, M + 52 );
		ctx.textAlign = 'left';
	}

	// Layout: the evolution line at the bottom, the text panel above it, and the picture as large as the
	// text allows (560 px down to 360 px); the text then gets the largest size that fits, 40 px down to 20 px.
	const nafn = htmlToText( entry.nafn );
	const enska = htmlToText( entry.enska );
	const title = nafn && nafn !== '–' ? nafn : enska;
	const uppruni = parseRich( entry.uppruni ).flat();
	const hasOrigin = uppruni.length > 0 && plainLine( uppruni ) !== '–';
	const parts = sections( entry.thyding, entry.uppruni );
	const hasText = parts.ordsifjar.length + parts.frodleikur.length > 0;
	const footer = 54;
	const stages = info?.k ?? [];
	const evoH = stages.length ? 250 : 96; // A short line saying it does not evolve.
	const evoY = H - M - footer - evoH;
	const bottom = evoY - 28;
	const pad = 40;
	const inner = W - M * 2 - pad * 2;
	const py = 160;
	const layout = ( S ) => {
		const nameY = py + S + 100;
		const enY = title !== enska && enska ? nameY + 56 : nameY;
		const originY = hasOrigin ? enY + 70 : enY;
		return { S, nameY, enY, originY, top: originY + 40 };
	};
	let L = layout( 360 );
	let ts = 20;
	if ( hasText ) {
		search: for ( const minText of [ 34, 28, 20 ] ) {
			for ( let S = 560; S >= 360; S -= 40 ) {
				const l = layout( S );
				for ( let t = 40; t >= minText; t-- ) {
					if ( textBlock( ctx, parts, inner, t, fam, colors, false ) <= bottom - l.top - pad * 2 ) {
						L = l;
						ts = t;
						break search;
					}
				}
			}
		}
	} else {
		L = layout( 560 );
	}
	const { S } = L;

	// Picture on a white glow.
	const pic = images.get( num );
	const px = ( W - S ) / 2;
	const glow = ctx.createRadialGradient( W / 2, py + S / 2, 40, W / 2, py + S / 2, S * 0.62 );
	glow.addColorStop( 0, css( WHITE, 0.95 ) );
	glow.addColorStop( 0.6, css( WHITE, 0.45 ) );
	glow.addColorStop( 1, css( WHITE, 0 ) );
	ctx.fillStyle = glow;
	ctx.fillRect( px - 120, py - 100, S + 240, S + 200 );
	if ( pic ) {
		ctx.save();
		ctx.shadowColor = 'rgba(0,0,0,0.28)';
		ctx.shadowBlur = 36;
		ctx.shadowOffsetY = 18;
		ctx.imageSmoothingQuality = 'high';
		ctx.drawImage( pic, px, py, S, S );
		ctx.restore();
	}

	// Names.
	const size = fitSize( ctx, title, W - M * 2, 112, 56, fam, { b: true } );
	ctx.font = fontOf( fam, size, { b: true } );
	ctx.fillStyle = INK;
	ctx.textAlign = 'center';
	ctx.fillText( title, W / 2, L.nameY );
	if ( L.enY !== L.nameY ) {
		ctx.font = fontOf( fam, 40 );
		ctx.fillStyle = css( rgb( INK ), 0.62 );
		ctx.fillText( enska, W / 2, L.enY );
	}
	ctx.textAlign = 'left';
	if ( hasOrigin ) {
		const us = fitSize( ctx, plainLine( uppruni ), W - M * 2, 52, 30, fam, { b: true } );
		const [ line ] = wrap( ctx, uppruni, 1e6, us, fam );
		ctx.fillStyle = css( colors.accent );
		drawLine( ctx, line, ( W - lineWidth( ctx, line, us, fam ) ) / 2, L.originY, us, fam );
	}

	// Evolution line.
	roundRect( ctx, M, evoY, W - M * 2, evoH, 36 );
	ctx.fillStyle = css( WHITE, 0.6 );
	ctx.fill();
	ctx.font = fontOf( fam, 28, { b: true } );
	ctx.fillStyle = css( colors.accent );
	if ( stages.length ) {
		ctx.fillText( 'ÞRÓUN', M + 32, evoY + 46 );
		drawEvolution( ctx, stages, num, M + 24, evoY + 56, W - M * 2 - 48, evoH - 66, fam, colors, images );
	} else {
		ctx.fillText( 'ÞRÓUN', M + 32, evoY + 58 );
		ctx.font = fontOf( fam, 34 );
		ctx.fillStyle = INK;
		ctx.textAlign = 'center';
		ctx.fillText( 'Þróast ekki', W / 2, evoY + 60 );
		ctx.textAlign = 'left';
	}

	// Text panel: the name's parts, then the fun facts.
	if ( hasText ) {
		const used = textBlock( ctx, parts, inner, ts, fam, colors, false );
		roundRect( ctx, M, L.top, W - M * 2, Math.min( bottom - L.top, used + pad * 2 ), 36 );
		ctx.fillStyle = css( WHITE, 0.86 );
		ctx.fill();
		ctx.save();
		ctx.beginPath();
		ctx.rect( M, L.top, W - M * 2, bottom - L.top ); // Never over the evolution line, even at the smallest size.
		ctx.clip();
		textBlock( ctx, parts, inner, ts, fam, colors, true, M + pad, L.top + pad - ts * 0.15 );
		ctx.restore();
	}

	ctx.font = fontOf( fam, 28, { b: true } );
	ctx.fillStyle = css( rgb( INK ), 0.55 );
	ctx.textAlign = 'center';
	ctx.fillText( 'VASASKRÍMSLI Á ÍSLENSKU · vasaskrimsli.is', W / 2, H - M + 6 );
	ctx.textAlign = 'left';
}

function toJpeg( canvas ) {
	return new Promise( ( resolve, reject ) => {
		canvas.toBlob(
			( blob ) => ( blob ? blob.arrayBuffer().then( ( b ) => resolve( new Uint8Array( b ) ), reject ) : reject( new Error( 'toBlob' ) ) ),
			'image/jpeg',
			0.9
		);
	} );
}

/**
 * Builds the PDF for the chosen entries.
 * @param {Object[]} chosen    Entries in collection order.
 * @param {Object[]} all       All entries on the page (for the evolution pictures).
 * @param {string}   throunUrl URL of throun.json.
 * @param {string}   fam       CSS font family of the page.
 * @param {Function} progress  Called with the number of cards done.
 * @return {Promise<Blob>}
 */
export async function buildPdf( chosen, all, throunUrl, fam, progress = () => {} ) {
	let throun = {};
	try {
		throun = await ( await fetch( throunUrl ) ).json();
	} catch {
		// Cards without types or evolutions are still useful.
	}
	const pictures = new Map();
	for ( const e of all ) {
		const n = national( e.numer );
		if ( ! pictures.has( n ) && ( e.mynd_stor || e.mynd ) ) {
			pictures.set( n, e.mynd_stor || e.mynd );
		}
	}
	try {
		await Promise.all( [ 400, 700 ].map( ( wgt ) => document.fonts?.load( `${ wgt } 40px ${ fam }`, 'ÞÆÐÖþæðö' ) ) );
	} catch {
		// Falls back to whatever font is available.
	}

	const canvas = document.createElement( 'canvas' );
	canvas.width = W;
	canvas.height = H;
	const pages = [];
	for ( const entry of chosen ) {
		const info = throun[ national( entry.numer ) ];
		const wanted = new Set( [ national( entry.numer ), ...( info?.k ?? [] ).flat() ] );
		const images = new Map();
		await Promise.all(
			[ ...wanted ].map( async ( n ) => {
				const img = await loadImage( pictures.get( n ) );
				if ( img ) {
					images.set( n, img );
				}
			} )
		);
		drawCard( canvas, entry, info, images, fam );
		pages.push( await toJpeg( canvas ) );
		progress( pages.length );
	}
	return new Blob( [ makePdf( pages, { title: 'Vasaskrímsli: safnið mitt' } ) ], { type: 'application/pdf' } );
}
