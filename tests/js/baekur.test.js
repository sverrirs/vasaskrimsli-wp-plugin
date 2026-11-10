import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { fjoldi, hnappastillingar, kr, myndir, synilegar, urHash, verd } from '../../features/baekur/src/leit.js';

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

test( 'filters by status and searches names, contents and ISBN, Icelandic-aware', () => {
	assert.deepEqual( fjoldi( BAEKUR ), { allar: 3, faanleg: 1, vaentanleg: 2 } );
	assert.deepEqual( synilegar( BAEKUR, { stada: 'vaentanleg' } ).map( ( b ) => b.id ), [ 'lestrarbok-11', 'lestrarbok-12' ] );
	assert.deepEqual( synilegar( BAEKUR, { leit: 'glodmandra' } ).map( ( b ) => b.id ), [ 'lestrarbok-2' ] );
	assert.deepEqual( synilegar( BAEKUR, { leit: 'threkangi' } ).map( ( b ) => b.id ), [ 'lestrarbok-12' ] );
	assert.deepEqual( synilegar( BAEKUR, { leit: '562-05' } ).map( ( b ) => b.id ), [ 'lestrarbok-11' ] );
	assert.deepEqual( synilegar( BAEKUR, { leit: 'lestrarbók dreksprek' } ).map( ( b ) => b.id ), [ 'lestrarbok-11' ] );
	assert.deepEqual( synilegar( BAEKUR, { stada: 'faanleg', leit: 'dreksprek' } ), [] );
	assert.equal( synilegar( BAEKUR, { leit: '  ' } ).length, 3 );
} );

test( 'pictures in viewing order and the open book from the address', () => {
	assert.deepEqual( myndir( { kapa: 'p.webp', synishorn: [ { mynd: 'q.webp', sida: 0 } ] } ).map( ( m ) => m.heiti ), [ 'Mynd 1', 'Mynd 2' ] );
	assert.deepEqual( myndir( { kapa: 'a.webp', sidur: 12, synishorn: [ { mynd: 'b.webp', sida: 3 }, { mynd: 'c.webp', sida: 0 } ] } ), [
		{ mynd: 'a.webp', heiti: 'Forsíða' },
		{ mynd: 'b.webp', heiti: 'Bls. 3' },
		{ mynd: 'c.webp', heiti: 'Mynd 3' },
	] );
	const radir = [ { id: 'lestrarbaekur', baekur: BAEKUR } ];
	assert.deepEqual( urHash( '#bok=lestrarbok-11', radir ), { rod: 'lestrarbaekur', bok: 'lestrarbok-11' } );
	assert.equal( urHash( '#bok=engin', radir ), null );
	assert.equal( urHash( '#lestrarbaekur', radir ), null );
	assert.equal( urHash( '', radir ), null );
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
