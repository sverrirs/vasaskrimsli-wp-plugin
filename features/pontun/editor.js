/* Editor placeholder for the order form block. Uses WordPress's own globals, no build step. */
( function ( wp ) {
	var el = wp.element.createElement;
	wp.blocks.registerBlockType( 'vasaskrimsli/pontun', {
		title: 'Verslun: Pöntunarform',
		icon: 'cart',
		category: 'widgets',
		keywords: [ 'verslun', 'pöntun', 'pantanir' ],
		supports: { html: false, multiple: false, align: [ 'wide', 'full' ] },
		attributes: { align: { type: 'string', default: 'wide' } },
		edit: function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps( {
					style: { padding: '24px', border: '1px dashed currentColor', borderRadius: '4px' },
				} ),
				el( 'strong', null, 'Verslun: Pöntunarform' ),
				el( 'p', { style: { margin: '8px 0 0' } }, 'Pöntunarformið birtist hér. Pantanir fá númer í réttri röð og sjást undir Pantanir í stjórnborðinu, þar sem viðtakendur og næsta pöntunarnúmer eru stillt.' )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
