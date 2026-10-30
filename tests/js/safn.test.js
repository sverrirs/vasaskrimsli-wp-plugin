import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { MAX, load, parseRich, plainLine, save, sections, toggle } from '../../features/ordsifjar/src/view/safn.js';
import { jpegSize, makePdf } from '../../features/ordsifjar/src/view/pdf.js';

test( 'toggle adds, removes and stops at MAX', () => {
	let list = [];
	for ( let n = 1; n <= MAX; n++ ) {
		const r = toggle( list, n );
		assert.equal( r.full, false );
		list = r.list;
	}
	assert.equal( list.length, MAX );
	const over = toggle( list, 999 );
	assert.equal( over.full, true );
	assert.deepEqual( over.list, list );
	const removed = toggle( list, 5 );
	assert.equal( removed.list.length, MAX - 1 );
	assert.ok( ! removed.list.includes( '5' ) );
	assert.deepEqual( toggle( removed.list, '351a' ).list.at( -1 ), '351a' );
} );

test( 'load survives broken or blocked storage and save round-trips', () => {
	const store = new Map();
	const storage = { getItem: ( k ) => store.get( k ) ?? null, setItem: ( k, v ) => store.set( k, v ) };
	save( storage, [ '1', '905' ] );
	assert.deepEqual( load( storage ), [ '1', '905' ] );
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
