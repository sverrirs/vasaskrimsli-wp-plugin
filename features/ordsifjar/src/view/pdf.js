/**
 * A minimal PDF writer: one JPEG per page, filling the page. Enough for the collection's cards
 * (drawn on a canvas by kort.js) without a PDF library. No DOM, tested in tests/js/pdf.test.js.
 */

/** A6 in PDF points (1/72 inch): 105 x 148 mm. */
export const A6 = { width: 297.64, height: 419.53 };

const enc = new TextEncoder();

/** A PDF text string in UTF-16BE, so the title keeps þ, ð and æ. */
function pdfString( s ) {
	let hex = 'FEFF';
	for ( let i = 0; i < s.length; i++ ) {
		hex += s.charCodeAt( i ).toString( 16 ).padStart( 4, '0' ).toUpperCase();
	}
	return `<${ hex }>`;
}

/** Width and height of a baseline or progressive JPEG, read from its SOF marker. */
export function jpegSize( bytes ) {
	let p = 2;
	while ( p + 9 < bytes.length ) {
		if ( bytes[ p ] !== 0xff ) {
			p++;
			continue;
		}
		const marker = bytes[ p + 1 ];
		const len = ( bytes[ p + 2 ] << 8 ) | bytes[ p + 3 ];
		if ( marker >= 0xc0 && marker <= 0xcf && marker !== 0xc4 && marker !== 0xc8 && marker !== 0xcc ) {
			return { height: ( bytes[ p + 5 ] << 8 ) | bytes[ p + 6 ], width: ( bytes[ p + 7 ] << 8 ) | bytes[ p + 8 ] };
		}
		p += 2 + len;
	}
	throw new Error( 'Not a JPEG' );
}

/**
 * @param {Uint8Array[]} jpegs One JPEG per page.
 * @param {{ title?: string, page?: { width: number, height: number } }} opts
 * @return {Uint8Array} The PDF file.
 */
export function makePdf( jpegs, { title = '', page = A6 } = {} ) {
	const chunks = [];
	const offsets = [];
	let length = 0;
	const push = ( part ) => {
		const bytes = typeof part === 'string' ? enc.encode( part ) : part;
		chunks.push( bytes );
		length += bytes.length;
	};
	const object = ( n, body, stream ) => {
		offsets[ n ] = length;
		push( `${ n } 0 obj\n${ body }\n` );
		if ( stream ) {
			push( 'stream\n' );
			push( stream );
			push( '\nendstream\n' );
		}
		push( 'endobj\n' );
	};

	const w = page.width.toFixed( 2 );
	const h = page.height.toFixed( 2 );
	// 1 catalog, 2 pages, 3 info, then 3 objects per page: page, content, image.
	const pageIds = jpegs.map( ( _, i ) => 4 + i * 3 );
	push( '%PDF-1.4\n' );
	push( new Uint8Array( [ 0x25, 0xe2, 0xe3, 0xcf, 0xd3, 0x0a ] ) ); // Binary comment: marks the file as binary.
	object( 1, '<< /Type /Catalog /Pages 2 0 R >>' );
	object( 2, `<< /Type /Pages /Kids [${ pageIds.map( ( id ) => `${ id } 0 R` ).join( ' ' ) }] /Count ${ jpegs.length } >>` );
	object( 3, `<< /Title ${ pdfString( title ) } /Producer (Vasaskrimsli) >>` );
	jpegs.forEach( ( jpeg, i ) => {
		const id = pageIds[ i ];
		const size = jpegSize( jpeg );
		const content = enc.encode( `q ${ w } 0 0 ${ h } 0 0 cm /Im0 Do Q` );
		object( id, `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${ w } ${ h }] /Resources << /XObject << /Im0 ${ id + 2 } 0 R >> >> /Contents ${ id + 1 } 0 R >>` );
		object( id + 1, `<< /Length ${ content.length } >>`, content );
		object(
			id + 2,
			`<< /Type /XObject /Subtype /Image /Width ${ size.width } /Height ${ size.height } /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${ jpeg.length } >>`,
			jpeg
		);
	} );

	const count = 4 + jpegs.length * 3;
	const xref = length;
	let table = `xref\n0 ${ count }\n0000000000 65535 f \n`;
	for ( let n = 1; n < count; n++ ) {
		table += `${ String( offsets[ n ] ).padStart( 10, '0' ) } 00000 n \n`;
	}
	push( table );
	push( `trailer\n<< /Size ${ count } /Root 1 0 R /Info 3 0 R >>\nstartxref\n${ xref }\n%%EOF\n` );

	const out = new Uint8Array( length );
	let p = 0;
	for ( const c of chunks ) {
		out.set( c, p );
		p += c.length;
	}
	return out;
}
