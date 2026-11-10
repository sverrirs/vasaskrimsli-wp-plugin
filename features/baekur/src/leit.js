/**
 * The catalogue's pure helpers: prices, filters, search and the address of an open book. No DOM here, so they are
 * tested in node (tests/js/baekur.test.js).
 */

import { fold } from '../../../shared/js/search.js';

/** 12800 -> "12.800 kr." */
export const kr = ( verd ) => `${ String( Math.round( verd ) ).replace( /\B(?=(\d{3})+(?!\d))/g, '.' ) } kr.`;

/** "12.800 kr." for one price, "frá 2.900 kr." when the options differ, '' without a product or prices. */
export function verd( vara ) {
	const prices = ( vara?.afbrigdi ?? [] ).map( ( a ) => a.verd ).filter( ( v ) => v > 0 );
	if ( ! prices.length ) {
		return '';
	}
	const low = Math.min( ...prices );
	return ( Math.max( ...prices ) > low ? 'frá ' : '' ) + kr( low );
}

/** How many books of each status: { allar, faanleg, vaentanleg }. */
export function fjoldi( baekur ) {
	const out = { allar: baekur.length, faanleg: 0, vaentanleg: 0 };
	for ( const b of baekur ) {
		out[ b.stada ] = ( out[ b.stada ] ?? 0 ) + 1;
	}
	return out;
}

/**
 * The books to show: of one status ('' for all), and matching every word of the search in the name, the contents or
 * the ISBN, Icelandic-aware ("gloðmandra" and "glodmandra" both find Glóðmandra).
 */
export function synilegar( baekur, { stada = '', leit = '' } = {} ) {
	const words = fold( leit ).split( /\s+/ ).filter( Boolean );
	return baekur.filter( ( b ) => {
		if ( stada && b.stada !== stada ) {
			return false;
		}
		const text = fold( `${ b.heiti } ${ b.efni ?? '' } ${ b.isbn ?? '' }` );
		return words.every( ( w ) => text.includes( w ) );
	} );
}

/** A book's pictures in viewing order: the cover, then the sample pages. A poster has pictures, not pages. */
export function myndir( bok ) {
	return [ { mynd: bok.kapa, heiti: bok.sidur ? 'Forsíða' : 'Mynd 1' }, ...( bok.synishorn ?? [] ).map( ( s, i ) => ( { mynd: s.mynd, heiti: s.sida ? `Bls. ${ s.sida }` : `Mynd ${ i + 2 }` } ) ) ];
}

/** The open book in an address's hash ("#bok=lestrarbok-3"): { rod, bok } ids, or null. */
export function urHash( hash, radir ) {
	const id = decodeURIComponent( String( hash ?? '' ).match( /(?:^#|&)bok=([^&]+)/ )?.[ 1 ] ?? '' );
	for ( const r of radir ) {
		if ( r.baekur.some( ( b ) => b.id === id ) ) {
			return { rod: r.id, bok: id };
		}
	}
	return null;
}

/**
 * The settings without empty lists. PHP writes an empty JSON object ("option": {}) as an empty list, and Shopify's
 * script fails on a list where it expects settings; an empty one means nothing either way.
 */
function utanTomra( value ) {
	if ( Array.isArray( value ) || value === null || typeof value !== 'object' ) {
		return value;
	}
	return Object.fromEntries( Object.entries( value ).filter( ( [ , v ] ) => ! ( Array.isArray( v ) && ! v.length ) ).map( ( [ k, v ] ) => [ k, utanTomra( v ) ] ) );
}

/**
 * The shop's Buy Button settings for the book viewer: the owner's settings (as Háskólaprent supplies them, with the
 * owner's changes), with the product's own picture, name and description left out (the viewer shows those), the
 * product filling the panel's width, and onAdded called when something is put in the cart.
 */
export function hnappastillingar( options, onAdded ) {
	const o = utanTomra( structuredClone( options ?? {} ) );
	o.product = o.product ?? {};
	o.product.contents = { ...( o.product.contents ?? {} ), img: false, imgWithCarousel: false, title: false, description: false, options: true, price: true };
	o.product.styles = o.product.styles ?? {};
	o.product.styles.product = { ...( o.product.styles.product ?? {} ), 'max-width': '100%', 'margin-left': '0', 'margin-bottom': '0', '@media (min-width: 601px)': { 'max-width': '100%', 'margin-left': '0', 'margin-bottom': '0' } };
	o.product.events = { ...( o.product.events ?? {} ), addVariantToCart: () => onAdded?.() };
	return o;
}
