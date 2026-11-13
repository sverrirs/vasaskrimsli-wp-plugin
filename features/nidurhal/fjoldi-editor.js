/* Editor for the block "Námsefni: Fjöldi niðurhala": choose the file, see the number as the page will. WordPress's own globals, no build step. */
( function ( wp ) {
	var el = wp.element.createElement;
	var skrar = window.vsNidurhalSkrar || [];
	wp.blocks.registerBlockType( 'vasaskrimsli/nidurhal-fjoldi', {
		title: 'Námsefni: Fjöldi niðurhala',
		icon: 'download',
		category: 'widgets',
		keywords: [ 'námsefni', 'niðurhal', 'fjöldi', 'teljari' ],
		supports: { html: false },
		attributes: { skra: { type: 'string', default: '' } },
		edit: function ( props ) {
			var skra = props.attributes.skra;
			var options = [ { label: 'Veldu skrá', value: '' } ].concat(
				skrar.map( function ( s ) {
					return { label: s.kort + ' (' + s.heiti + ')', value: s.kort };
				} )
			);
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el(
					wp.blockEditor.InspectorControls,
					null,
					el(
						wp.components.PanelBody,
						{ title: 'Skrá' },
						el( wp.components.SelectControl, {
							label: 'Skrá',
							value: skra,
							options: options,
							onChange: function ( v ) {
								props.setAttributes( { skra: v } );
							},
						} ),
						el( wp.components.TextControl, {
							label: 'Eða nafn hennar',
							help: 'Stutta nafnið (1, klubbur-og-safnmappa) eða skráarnafnið. Sýnir fjölda niðurhala alls.',
							value: skra,
							onChange: function ( v ) {
								props.setAttributes( { skra: v } );
							},
						} )
					)
				),
				skra
					? el( wp.serverSideRender, { block: 'vasaskrimsli/nidurhal-fjoldi', attributes: props.attributes } )
					: el( 'em', null, 'Fjöldi niðurhala: veldu skrá í stillingum blokkarinnar.' )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
