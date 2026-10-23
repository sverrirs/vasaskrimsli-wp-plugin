/* Editor placeholder for the Orðsifjafræði block. Uses WordPress's own globals, no build step. */
( function ( wp ) {
	var el = wp.element.createElement;
	wp.blocks.registerBlockType( 'vasaskrimsli/ordsifjar', {
		edit: function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps( {
					style: { padding: '24px', border: '1px dashed currentColor', borderRadius: '4px' },
				} ),
				el( 'strong', null, 'Orðsifjafræði' ),
				el( 'p', { style: { margin: '8px 0 0' } }, 'Taflan með leit og síum birtist hér á síðunni. Færslum er breytt undir Orðsifjar í stjórnborðinu.' )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
