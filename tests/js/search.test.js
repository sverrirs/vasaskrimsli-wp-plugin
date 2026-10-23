import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fold, htmlToText, matches } from '../../shared/js/search.js';

test( 'fold removes Icelandic accents and special letters', () => {
	assert.equal( fold( 'Þýðing Orðsifjafræði ÆFI Ölur' ), 'thyding ordsifjafraedi aefi olur' );
} );

test( 'htmlToText strips tags and decodes entities', () => {
	assert.equal( htmlToText( '<strong>Lauk</strong>&#8211;<a href="x">eðla</a><br>&amp; „hali“' ), 'Lauk–eðla & „hali“' );
} );

test( 'matches needs every word, in any order, accent-insensitive', () => {
	const hay = fold( htmlToText( 'Netlungur <strong>-ungur:</strong> afkvæmi Ivysaur' ) );
	assert.ok( matches( hay, 'ungur ivy' ) );
	assert.ok( matches( hay, 'AFKVAEMI' ) );
	assert.ok( ! matches( hay, 'ungur bulbasaur' ) );
	assert.ok( matches( hay, '   ' ), 'empty query matches everything' );
} );
