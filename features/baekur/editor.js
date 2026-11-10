/* Editor placeholders for the book catalogue blocks. Uses WordPress's own globals, no build step. */
( function ( wp, radir ) {
	var el = wp.element.createElement;
	function placeholder( title, text ) {
		return function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps( {
					style: { padding: '24px', border: '1px dashed currentColor', borderRadius: '4px' },
				} ),
				el( 'strong', null, title ),
				el( 'p', { style: { margin: '8px 0 0' } }, text )
			);
		};
	}
	var save = function () {
		return null;
	};
	wp.blocks.registerBlockType( 'vasaskrimsli/verslun', {
		title: 'Verslun: allar bækur',
		icon: 'book',
		category: 'widgets',
		edit: placeholder( 'Verslun: allar bækur', 'Allir vöruflokkar birtast hér, með forsíðum, sýnishornum og tenglum í vefverslunina.' ),
		save: save,
	} );
	Object.keys( radir || {} ).forEach( function ( id ) {
		wp.blocks.registerBlockType( 'vasaskrimsli/baekur-' + id, {
			title: radir[ id ],
			icon: 'book-alt',
			category: 'widgets',
			edit: placeholder( radir[ id ], 'Bækurnar í þessum flokki birtast hér, með forsíðum, sýnishornum og tenglum í vefverslunina.' ),
			save: save,
		} );
	} );
} )( window.wp, window.vsBaekurRadir );
