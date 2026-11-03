import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { MAX, add, addForm, iHring, midjaOf, tiles, boldSelf, itemsOf, load, parseRich, plainLine, remove, save, sections, variant } from '../../features/ordsifjar/src/view/safn.js';
import { jpegSize, makePdf } from '../../features/ordsifjar/src/view/pdf.js';
import { PER_SHEET, columns, nameLines, sheets } from '../../features/ordsifjar/src/view/bokamerki.js';

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
	const sand = sections( '<strong>Sandur:</strong> jarðefni.<br><em>Dýrafræði: snjáldurmús.</em><br><strong>Fjölskylda:</strong> Sandsnjáldur → Svöðusandur (#28).', 'Sand—snjáldur' );
	assert.deepEqual( sand.frodleikur.map( plainLine ), [ 'Dýrafræði: snjáldurmús.' ] );
	assert.deepEqual( sand.fjolskylda.map( plainLine ), [ 'Fjölskylda: Sandsnjáldur → Svöðusandur (#28).' ] );
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

test( 'the Mega and Gígamax views show one tile per matching form, and a click adds only that form', () => {
	const charizard = {
		numer: '6',
		nafn: 'Bálormur',
		formir: [
			{ id: 'charizard-mega-x', heiti: 'Mega Bálormur X', enska: 'Mega Charizard X', mynd: 'x.webp' },
			{ id: 'charizard-mega-y', heiti: 'Mega Bálormur Y', enska: 'Mega Charizard Y', mynd: 'y.webp' },
			{ id: 'charizard-gigantamax', heiti: 'Gígamax Bálormur', enska: 'Gigantamax Charizard', mynd: 'g.webp' },
		],
	};
	const raichu = { numer: '26', formir: [ { id: 'raichu-alolan', heiti: 'Rottóttur Alóla', enska: 'Alolan Raichu' } ] };
	const entries = [ charizard, raichu, { numer: '1' } ];
	assert.equal( tiles( entries, '' ), entries );
	assert.deepEqual( tiles( entries, 'mega' ).map( ( t ) => [ t.form, t.nafn, t.mynd, t.base.numer ] ), [
		[ 'charizard-mega-x', 'Mega Bálormur X', 'x.webp', '6' ],
		[ 'charizard-mega-y', 'Mega Bálormur Y', 'y.webp', '6' ],
	] );
	assert.deepEqual( tiles( entries, 'gigamax' ).map( ( t ) => t.form ), [ 'charizard-gigantamax' ] );

	let r = addForm( [ '1', '6' ], 6, 'charizard-mega-y' );
	assert.deepEqual( r.list, [ '1', '6', '6:charizard-mega-y' ] );
	r = addForm( r.list, 6, 'charizard-mega-y' );
	assert.equal( r.all, true );
	assert.deepEqual( r.list, [ '1', '6', '6:charizard-mega-y' ] );
} );

test( 'bookmarks: five to a sheet, inside the page and not overlapping', () => {
	assert.deepEqual( sheets( [ 1, 2, 3, 4, 5, 6, 7 ] ), [ [ 1, 2, 3, 4, 5 ], [ 6, 7 ] ] );
	assert.deepEqual( sheets( [] ), [] );
	const xs = columns();
	assert.equal( xs.length, PER_SHEET );
	assert.ok( xs[ 0 ] > 0 && xs[ PER_SHEET - 1 ] + 52 < 297 );
	xs.slice( 1 ).forEach( ( x, i ) => assert.ok( x >= xs[ i ] + 52 ) );
} );

test( 'bookmarks: the name fits down the length, on two lines only when one would be too small', () => {
	const measure = ( t ) => t.length * 0.55;
	const fits = ( r ) => r.lines.every( ( l ) => measure( l ) * r.size <= 93 + 1e-9 );

	const short = nameLines( 'Púpisti', 93, measure );
	assert.deepEqual( short, { lines: [ 'Púpisti' ], size: 21 } );

	const medium = nameLines( 'Galdursforseti', 93, measure );
	assert.equal( medium.lines.length, 1 );
	assert.ok( medium.size < 21 && fits( medium ) );

	// The longest name there is: split where the two lines are most even.
	const long = nameLines( 'Gígamax Hólmbersi hraðhöggsstíll', 93, measure );
	assert.deepEqual( long.lines, [ 'Gígamax Hólmbersi', 'hraðhöggsstíll' ] );
	assert.ok( fits( long ) );

	// A word that can not be split still fits.
	const word = 'x'.repeat( 40 );
	const unsplit = nameLines( word, 93, measure );
	assert.deepEqual( unsplit.lines, [ word ] );
	assert.ok( fits( unsplit ) );
} );

test( 'a picture in a circle: its midpoint in the middle, its farthest point inside the rim', () => {
	// #11 leans left and down in its file.
	const midja = [ 0.4362, 0.6666, 0.4738 ];
	const at = iHring( midja, 256, 256, 100, 200, 50 );
	assert.ok( Math.abs( at.x + midja[ 0 ] * at.w - 100 ) < 1e-9 );
	assert.ok( Math.abs( at.y + midja[ 1 ] * at.h - 200 ) < 1e-9 );
	const reach = midja[ 2 ] * Math.max( at.w, at.h );
	assert.ok( reach < 50 && reach > 45 );

	// Without a midpoint the file stays centred.
	const plain = iHring( undefined, 256, 256, 100, 200, 50 );
	assert.equal( plain.x + plain.w / 2, 100 );
	assert.equal( plain.y + plain.h / 2, 200 );
} );

test( 'midjur.json has every large and form sprite, found from the URL the page uses', () => {
	const midjur = JSON.parse( readFileSync( new URL( '../../features/ordsifjar/data/midjur.json', import.meta.url ), 'utf8' ) );
	for ( const sub of [ 'large', 'formir' ] ) {
		const dir = new URL( `../../features/ordsifjar/assets/sprites/${ sub }/`, import.meta.url );
		for ( const f of readdirSync( dir ).filter( ( x ) => x.endsWith( '.webp' ) ) ) {
			const row = midjaOf( midjur, `https://example.is/wp-content/plugins/v/features/ordsifjar/assets/sprites/${ sub }/${ f }` );
			assert.ok( row?.length === 3 && row.every( ( v ) => v > 0 && v < 1 ), `${ sub }/${ f }` );
		}
	}
	assert.equal( midjaOf( midjur, '' ), undefined );
} );
