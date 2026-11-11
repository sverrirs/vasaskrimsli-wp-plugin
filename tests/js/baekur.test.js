import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { atridi, fjoldi, hnappastillingar, kr, myndir, skref, synilegar, urHash, verd } from '../../features/baekur/src/leit.js';

const BAEKUR = [
	{ id: 'lestrarbok-2', heiti: 'Lestrarbók 2', efni: 'Glóðmandra, Kameldljón, Bálormur', isbn: '978-9935-9817-1-4', stada: 'faanleg' },
	{ id: 'lestrarbok-11', heiti: 'Lestrarbók 11', efni: 'Dreksprek, Grandormur, Drekvaxinn', isbn: '978-9935-562-05-0', stada: 'vaentanleg' },
	{ id: 'lestrarbok-12', heiti: 'Lestrarbók 12', efni: 'Þrekangi, Aflgarpur, Fermundur', isbn: '', stada: 'vaentanleg' },
];

test( 'prices in Icelandic crowns, "frá" when the options differ', () => {
	assert.equal( kr( 12800 ), '12.800 kr.' );
	assert.equal( kr( 176000 ), '176.000 kr.' );
	assert.equal( kr( 900 ), '900 kr.' );
	assert.equal( verd( { afbrigdi: [ { heiti: '', verd: 6900 } ] } ), '6.900 kr.' );
	assert.equal( verd( { afbrigdi: [ { heiti: 'A3', verd: 3900 }, { heiti: '30 x 40 cm', verd: 2900 } ] } ), 'frá 2.900 kr.' );
	assert.equal( verd( undefined ), '' );
	assert.equal( verd( { afbrigdi: [] } ), '' );
} );

test( 'filters by status', () => {
	assert.deepEqual( fjoldi( BAEKUR ), { allar: 3, faanleg: 1, vaentanleg: 2 } );
	assert.deepEqual( synilegar( BAEKUR, 'vaentanleg' ).map( ( b ) => b.id ), [ 'lestrarbok-11', 'lestrarbok-12' ] );
	assert.deepEqual( synilegar( BAEKUR, 'faanleg' ).map( ( b ) => b.id ), [ 'lestrarbok-2' ] );
	assert.equal( synilegar( BAEKUR, '' ).length, 3 );
} );

test( 'a series sold in lots lists its lots, each with its books and the first three covers', () => {
	const baekur = Array.from( { length: 12 }, ( _, i ) => ( { id: `b${ i + 1 }`, kapa: `k${ i + 1 }.webp`, stada: 'vaentanleg' } ) );
	const lots = atridi( { baekur, pakkar: [ { id: 'p1', stada: 'faanleg', baekur: [ 'b1', 'b2', 'b3', 'b4' ] }, { id: 'p2', stada: 'vaentanleg', baekur: [ 'b11', 'b12', 'gone' ] } ] } );
	assert.deepEqual( lots.map( ( p ) => p.id ), [ 'p1', 'p2' ] );
	assert.deepEqual( lots[ 0 ].baekur.map( ( b ) => b.id ), [ 'b1', 'b2', 'b3', 'b4' ] );
	assert.deepEqual( [ lots[ 0 ].kapa, lots[ 0 ].kapur ], [ 'k1.webp', [ 'k1.webp', 'k2.webp', 'k3.webp' ] ] );
	assert.deepEqual( lots[ 1 ].baekur.map( ( b ) => b.id ), [ 'b11', 'b12' ] );
	assert.deepEqual( fjoldi( lots ), { allar: 2, faanleg: 1, vaentanleg: 1 } );
	// Without lots the list is the books.
	assert.equal( atridi( { baekur } ), baekur );
	assert.equal( atridi( { baekur, pakkar: [] } ), baekur );
} );

test( 'pictures in viewing order and the open book from the address', () => {
	assert.deepEqual( myndir( { kapa: 'p.webp', synishorn: [ { mynd: 'q.webp', sida: 0 } ] } ).map( ( m ) => m.heiti ), [ 'Mynd 1', 'Mynd 2' ] );
	assert.deepEqual( myndir( { kapa: 'a.webp', sidur: 12, synishorn: [ { mynd: 'b.webp', sida: 3 }, { mynd: 'c.webp', sida: 0 } ] } ), [
		{ mynd: 'a.webp', heiti: 'Forsíða' },
		{ mynd: 'b.webp', heiti: 'Bls. 3' },
		{ mynd: 'c.webp', heiti: 'Mynd 3' },
	] );
	assert.equal( urHash( '#bok=lestrarbok-11', BAEKUR ), 'lestrarbok-11' );
	assert.equal( urHash( '#bok=engin', BAEKUR ), null ); // Another block's book, or none.
	assert.equal( urHash( '#lestrarbaekur', BAEKUR ), null );
	assert.equal( urHash( '', BAEKUR ), null );
} );

test( 'baekur.json: every book has a cover and samples that exist, and a known status and product', () => {
	const file = new URL( '../../features/baekur/data/baekur.json', import.meta.url );
	const data = JSON.parse( readFileSync( file, 'utf8' ) );
	const ids = new Set();
	for ( const r of data.radir ) {
		assert.ok( r.id && r.akkeri && r.heiti && r.baekur.length, r.id );
		for ( const b of r.baekur ) {
			assert.ok( ! ids.has( b.id ), `${ b.id } twice` );
			ids.add( b.id );
			assert.ok( [ 'faanleg', 'vaentanleg' ].includes( b.stada ), `${ b.id }: ${ b.stada }` );
			for ( const m of myndir( b ) ) {
				assert.ok( existsSync( new URL( `../../features/baekur/${ m.mynd }`, import.meta.url ) ), `${ b.id }: ${ m.mynd }` );
			}
			if ( b.stada === 'faanleg' ) {
				assert.ok( data.vorur[ b.vara ]?.slod, `${ b.id }: on sale without a product` );
			}
		}
		for ( const p of r.pakkar ?? [] ) {
			assert.ok( p.baekur.length && p.baekur.every( ( id ) => r.baekur.some( ( b ) => b.id === id ) ), `${ p.id }: books` );
			assert.ok( p.stada !== 'faanleg' || data.vorur[ p.vara ]?.slod, `${ p.id }: on sale without a product` );
		}
	}
} );

test( 'Buy Button settings: the owner\'s styles stay, the viewer hides picture and name and hears additions', () => {
	const owner = { product: { styles: { button: { 'background-color': '#2e5dd2' }, product: { 'margin-left': '20px' } }, text: { button: 'Bæta í körfu' } }, cart: { text: { title: 'Karfa' } } };
	let added = 0;
	const o = hnappastillingar( owner, () => added++ );
	assert.equal( o.product.styles.button[ 'background-color' ], '#2e5dd2' );
	assert.equal( o.product.text.button, 'Bæta í körfu' );
	assert.equal( o.cart.text.title, 'Karfa' );
	assert.deepEqual( [ o.product.contents.img, o.product.contents.title, o.product.contents.options, o.product.contents.price ], [ false, false, true, true ] );
	assert.equal( o.product.styles.product[ 'max-width' ], '100%' );
	o.product.events.addVariantToCart();
	assert.equal( added, 1 );
	assert.equal( owner.product.contents, undefined ); // The owner's settings are not changed.
	// "option": {} arrives from PHP as [] and breaks Shopify's script: empty lists are dropped, real ones kept.
	const fromPhp = hnappastillingar( { option: [], product: { googleFonts: [ 'Raleway' ], styles: { x: [] } } } );
	assert.equal( 'option' in fromPhp, false );
	assert.equal( 'x' in fromPhp.product.styles, false );
	assert.deepEqual( fromPhp.product.googleFonts, [ 'Raleway' ] );
} );

test( 'the arrows go through every book of a lot and round', () => {
	const counts = [ 4, 4, 2 ];
	assert.deepEqual( skref( counts, [ 0, 1 ], 1 ), [ 0, 2 ] );
	assert.deepEqual( skref( counts, [ 0, 3 ], 1 ), [ 1, 0 ] ); // Last page of a book: the next book's cover.
	assert.deepEqual( skref( counts, [ 1, 0 ], -1 ), [ 0, 3 ] ); // Cover: the previous book's last page.
	assert.deepEqual( skref( counts, [ 2, 1 ], 1 ), [ 0, 0 ] ); // The last book's last page: the first book's cover.
	assert.deepEqual( skref( counts, [ 0, 0 ], -1 ), [ 2, 1 ] );
	assert.deepEqual( skref( [ 1 ], [ 0, 0 ], 1 ), [ 0, 0 ] );
} );
