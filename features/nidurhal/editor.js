/* Editor placeholder for the download counter block. Uses WordPress's own globals, no build step. */
( function ( wp ) {
	var el = wp.element.createElement;
	wp.blocks.registerBlockType( 'vasaskrimsli/nidurhal', {
		title: 'Námsefni: Niðurhalsteljari',
		icon: 'download',
		category: 'widgets',
		keywords: [ 'námsefni', 'niðurhal', 'zip', 'teljari' ],
		supports: { html: false, multiple: false },
		edit: function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps( {
					style: { padding: '16px', border: '1px dashed currentColor', borderRadius: '4px' },
				} ),
				el( 'strong', null, 'Námsefni: Niðurhalsteljari' ),
				el( 'p', { style: { margin: '8px 0 0' } }, 'Sést ekki á síðunni. Telur hve oft fólk smellir á tengla í ZIP-skrár á þessari síðu. Tölurnar eru undir Niðurhal í stjórnborðinu. Smellir þínir þegar þú ert innskráð(ur) eru ekki taldir.' )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
