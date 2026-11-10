/* Editor placeholders for the book catalogue blocks. Uses WordPress's own globals, no build step. */
( function ( wp, radir ) {
	var el = wp.element.createElement;
	Object.keys( radir || {} ).forEach( function ( id ) {
		wp.blocks.registerBlockType( 'vasaskrimsli/baekur-' + id, {
			title: radir[ id ],
			icon: 'book-alt',
			category: 'widgets',
			edit: function () {
				return el(
					'div',
					wp.blockEditor.useBlockProps( {
						style: { padding: '24px', border: '1px dashed currentColor', borderRadius: '4px' },
					} ),
					el( 'strong', null, radir[ id ] ),
					el( 'p', { style: { margin: '8px 0 0' } }, 'Bókalistinn birtist hér, með forsíðum, sýnishornum og kaupahnappi. Skrifaðu fyrirsögn og texta um bækurnar fyrir ofan.' )
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp, window.vsBaekurRadir );
