/* Editor side of the book catalogue blocks: a placeholder and the block's settings. WordPress's own globals, no build step. */
( function ( wp, radir ) {
	var el = wp.element.createElement;
	var be = wp.blockEditor;
	var c = wp.components;
	var STEFNA = { larett: 'Láréttar raðir', lodrett: 'Lóðréttur listi' };
	var STAERD = { litil: 'Lítil', midlungs: 'Miðlungs', stor: 'Stór' };
	var JOFNUN = { left: 'vinstri', center: 'miðju', right: 'hægri' };
	var options = function ( map ) {
		return Object.keys( map ).map( function ( k ) {
			return { value: k, label: map[ k ] };
		} );
	};

	Object.keys( radir || {} ).forEach( function ( id ) {
		var title = 'Verslun: ' + radir[ id ];
		wp.blocks.registerBlockType( 'vasaskrimsli/baekur-' + id, {
			title: title,
			icon: 'book-alt',
			category: 'widgets',
			keywords: [ 'verslun', 'bækur' ],
			attributes: {
				jofnun: { type: 'string', default: 'left' },
				stefna: { type: 'string', default: 'larett' },
				bil: { type: 'number', default: 24 },
				staerd: { type: 'string', default: 'litil' },
				align: { type: 'string', default: 'wide' },
			},
			supports: { html: false, align: [ 'wide', 'full' ] },
			edit: function ( props ) {
				var a = props.attributes;
				var set = props.setAttributes;
				return el(
					wp.element.Fragment,
					null,
					el(
						be.BlockControls,
						{ group: 'block' },
						el( be.AlignmentControl, {
							value: a.jofnun,
							onChange: function ( v ) {
								set( { jofnun: v || 'left' } );
							},
						} )
					),
					el(
						be.InspectorControls,
						null,
						el(
							c.PanelBody,
							{ title: 'Útlit listans' },
							el( c.SelectControl, {
								label: 'Stefna',
								value: a.stefna,
								options: options( STEFNA ),
								onChange: function ( v ) {
									set( { stefna: v } );
								},
							} ),
							el( c.SelectControl, {
								label: 'Stærð mynda og texta',
								value: a.staerd,
								options: options( STAERD ),
								onChange: function ( v ) {
									set( { staerd: v } );
								},
							} ),
							el( c.RangeControl, {
								label: 'Bil á milli (px)',
								value: a.bil,
								min: 0,
								max: 120,
								onChange: function ( v ) {
									set( { bil: typeof v === 'number' ? v : 24 } );
								},
							} )
						)
					),
					el(
						'div',
						be.useBlockProps( {
							style: { padding: '24px', border: '1px dashed currentColor', borderRadius: '4px', textAlign: a.jofnun },
						} ),
						el( 'strong', null, title ),
						el(
							'p',
							{ style: { margin: '8px 0 0' } },
							STEFNA[ a.stefna ] + ', ' + STAERD[ a.staerd ].toLowerCase() + ', ' + a.bil + ' px bil, jafnað til ' + JOFNUN[ a.jofnun ] + '. Skrifaðu fyrirsögn og texta um bækurnar fyrir ofan.'
						)
					)
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp, window.vsBaekurRadir );
