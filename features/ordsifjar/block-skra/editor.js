/* Editor placeholder for the Skrímslaskrá block. Uses WordPress's own globals, no build step. */
( function ( wp ) {
	var el = wp.element.createElement;
	wp.blocks.registerBlockType( 'vasaskrimsli/skrimslaskra', {
		edit: function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps( {
					style: { padding: '24px', border: '1px dashed currentColor', borderRadius: '4px' },
				} ),
				el( 'strong', null, 'Skrímslaskrá' ),
				el( 'p', { style: { margin: '8px 0 0' } }, 'Myndayfirlit yfir öll skrímslin birtist hér á síðunni. Færslum er breytt undir Orðsifjar í stjórnborðinu.' )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
