import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { MAX, add, boldSelf, itemsOf, load, parseRich, plainLine, remove, save, sections, variant } from '../../features/ordsifjar/src/view/safn.js';
import { jpegSize, makePdf } from '../../features/ordsifjar/src/view/pdf.js';

test( 'add stops at MAX and remove takes one item out', () => {
	let list = [];
	for ( let n = 1; n <= MAX; n++ ) {
		const r = add( list, n );
		assert.equal( r.full, false );
		list = r.list;
	}
	assert.equal( list.length, MAX );
	const over = add( list, 999 );
	assert.equal( over.full, true );
	assert.deepEqual( over.list, list );
	const removed = remove( list, '5' );
	assert.equal( removed.length, MAX - 1 );
	assert.ok( ! removed.includes( '5' ) );
	assert.deepEqual( add( removed, '351a' ).list.at( -1 ), '351a' );
} );

test( 'clicks add the monster and then each of its forms, next to each other, and never take out', () => {
	const forms = [ 'raichu-alolan', 'raichu-mega-x' ];
	const seen = [];
	let list = [ '1', '2' ];
	list = add( list, 1 ).list; // Already in, no forms: nothing changes.
	for ( let i = 0; i < 4; i++ ) {
		const r = add( list, 26, forms );
		list = r.list;
		seen.push( [ [ ...list ], r.all ] );
	}
	assert.deepEqual( seen, [
		[ [ '1', '2', '26' ], false ],
		[ [ '1', '2', '26', '26:raichu-alolan' ], false ],
		[ [ '1', '2', '26', '26:raichu-alolan', '26:raichu-mega-x' ], false ],
		[ [ '1', '2', '26', '26:raichu-alolan', '26:raichu-mega-x' ], true ],
	] );
	assert.equal( add( [ '1' ], 1 ).all, true );
	// Only Mega X: take the others out with ×. A click then adds the first one missing, after its siblings.
	list = remove( remove( list, '26' ), '26:raichu-alolan' );
	assert.deepEqual( list, [ '1', '2', '26:raichu-mega-x' ] );
	assert.deepEqual( add( [ '26:raichu-mega-x', '3' ], 26, forms ).list, [ '26:raichu-mega-x', '26', '3' ] );
	assert.deepEqual( itemsOf( list, 26 ), [ '26:raichu-mega-x' ] );
} );

test( 'variant shows the form in place of the monster', () => {
	const e = { numer: '26', nafn: 'Þrumumús', enska: 'Raichu', mynd: 'a.webp', formir: [ { id: 'raichu-alolan', heiti: 'Þrumumús Alóla', enska: 'Alolan Raichu', mynd: 'f.webp' } ] };
	assert.equal( variant( e, '' ), e );
	const v = variant( e, 'raichu-alolan' );
	assert.deepEqual( [ v.nafn, v.enska, v.mynd, v.mynd_stor, v.forma.nr, v.numer ], [ 'Þrumumús Alóla', 'Alolan Raichu', 'f.webp', 'f.webp', 1, '26' ] );
	assert.equal( variant( e, 'gone' ), undefined );
} );

test( 'load survives broken or blocked storage and save round-trips', () => {
	const store = new Map();
	const storage = { getItem: ( k ) => store.get( k ) ?? null, setItem: ( k, v ) => store.set( k, v ) };
	save( storage, [ '1', '905' ] );
	assert.deepEqual( load( storage ), [ '1', '905' ] );
	save( storage, [ '26:raichu-alolan', '26', '26:raichu-alolan', '1' ] ); // Forms are their own items; no repeats.
	assert.deepEqual( load( storage ), [ '26:raichu-alolan', '26', '1' ] );
	store.set( 'vasaskrimsli-safn', '{nope' );
	assert.deepEqual( load( storage ), [] );
	assert.deepEqual( load( { getItem: () => { throw new Error( 'blocked' ); } } ), [] );
	save( { setItem: () => { throw new Error( 'quota' ); } }, [ '1' ] ); // Must not throw.
} );

test( 'parseRich keeps bold, italic and lines, drops links and decodes entities', () => {
	const lines = parseRich( '<strong>Rok:</strong> ofsaveður &amp; <a href="x">vindur</a>.<br><em>Hildur</em>&nbsp;orrusta' );
	assert.equal( lines.length, 2 );
	assert.deepEqual( lines[ 0 ][ 0 ], { t: 'Rok:', b: true, i: false } );
	assert.equal( plainLine( lines[ 0 ] ), 'Rok: ofsaveður & vindur.' );
	assert.deepEqual( lines[ 1 ][ 0 ], { t: 'Hildur', b: false, i: true } );
} );

test( 'sections split the name parts from the fun facts on real entries', () => {
	const rok = sections(
		'<strong>Rok:</strong> ofsaveður.<br><strong>Hildur:</strong> kvenmannsnafn.<br><strong>Goðafræði:</strong> Hildur er valkyrja.',
		'<strong>Rok</strong>—hildur'
	);
	assert.deepEqual( rok.ordsifjar.map( plainLine ), [ 'Rok: ofsaveður.', 'Hildur: kvenmannsnafn.' ] );
	assert.deepEqual( rok.frodleikur.map( plainLine ), [ 'Goðafræði: Hildur er valkyrja.' ] );
	// "Klaki:" explains "Klaka", "-ungur:" explains "ungur", unlabelled lines follow the one before.
	const klaki = sections( '<strong>Klaki:</strong> ís.<br>meira.<br><strong>Náttúra:</strong> fugl.', 'Klaka—gæs' );
	assert.equal( klaki.ordsifjar.length, 2 );
	assert.equal( klaki.frodleikur.length, 1 );
	assert.equal( sections( '<strong>-ungur:</strong> smátt', 'Netl–ungur' ).ordsifjar.length, 1 );
	const sae = sections( '<strong>Skrín:</strong> kista.<br><strong>Sær:</strong> sjór.<br><strong>Íslenska:</strong> helgir dómar.', 'Sæ—skrín' );
	assert.deepEqual( sae.ordsifjar.map( plainLine ), [ 'Skrín: kista.', 'Sær: sjór.' ] );
} );

/** Smallest valid-looking JPEG header: SOI, SOF0 with the size, EOI. */
const fakeJpeg = ( w, h ) => new Uint8Array( [ 0xff, 0xd8, 0xff, 0xc0, 0, 11, 8, h >> 8, h & 255, w >> 8, w & 255, 3, 0, 0, 0xff, 0xd9 ] );

test( 'makePdf writes one page per image with a correct cross-reference table', () => {
	const pdf = makePdf( [ fakeJpeg( 1240, 1748 ), fakeJpeg( 10, 20 ) ], { title: 'Safnið' } );
	const text = Buffer.from( pdf ).toString( 'latin1' );
	assert.ok( text.startsWith( '%PDF-1.4\n' ) );
	assert.match( text, /\/Count 2/ );
	assert.match( text, /\/Width 1240 \/Height 1748/ );
	assert.match( text, /\/MediaBox \[0 0 297\.64 419\.53\]/ );
	const xref = Number( text.match( /startxref\n(\d+)/ )[ 1 ] );
	assert.ok( text.startsWith( 'xref', xref ) );
	const entries = text.slice( xref ).match( /^\d{10} 00000 n $/gm );
	assert.equal( entries.length, 3 + 2 * 3 );
	entries.forEach( ( line, i ) => assert.ok( text.startsWith( `${ i + 1 } 0 obj`, Number( line.slice( 0, 10 ) ) ), `object ${ i + 1 }` ) );
} );

test( 'jpegSize reads a real JPEG and rejects other data', () => {
	const real = readFileSync( new URL( './fixtures/tiny.jpg', import.meta.url ) );
	assert.deepEqual( jpegSize( real ), { width: 3, height: 2 } );
	assert.throws( () => jpegSize( new Uint8Array( 20 ) ) );
} );

test( 'boldSelf bolds the monster\'s own name on the Fjölskylda line only', () => {
	const html = '<strong>Þrí:</strong> Þrír. Þríbý er hér.<br><strong>Fjölskylda:</strong> Þríbý → Býdrottning (#416).';
	assert.equal(
		boldSelf( html, 'Þríbý' ),
		'<strong>Þrí:</strong> Þrír. Þríbý er hér.<br><strong>Fjölskylda:</strong> <strong>Þríbý</strong> → Býdrottning (#416).'
	);
	// A whole word only: "Býdrottning" is not touched when the name is "Bý".
	assert.equal( boldSelf( '<strong>Fjölskylda:</strong> Þríbý → Býdrottning.', 'Bý' ), '<strong>Fjölskylda:</strong> Þríbý → Býdrottning.' );
	assert.equal( boldSelf( html, '' ), html );
	// The PDF card reads it as a bold run.
	const line = parseRich( boldSelf( html, 'Þríbý' ) )[ 1 ];
	assert.deepEqual( line.filter( ( r ) => r.b ).map( ( r ) => r.t.trim() ), [ 'Fjölskylda:', 'Þríbý' ] );
} );
