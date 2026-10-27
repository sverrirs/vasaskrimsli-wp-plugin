import { htmlToText } from '../../../../shared/js/search.js';

/** Tile image size in the Skrímslaskrá grid (from the 256 px image, so sharp on high-density screens). */
const IMG = 112;

/** Arrow keys move between tiles: left/right in reading order, up/down to the nearest tile in the next row. */
function moveFocus( key, from ) {
	const tiles = [ ...document.querySelectorAll( '.vs-of-tile' ) ];
	const i = tiles.indexOf( from );
	let j = i;
	if ( key === 'ArrowRight' ) {
		j = i + 1;
	} else if ( key === 'ArrowLeft' ) {
		j = i - 1;
	} else {
		const r = from.getBoundingClientRect();
		const below = key === 'ArrowDown';
		const cands = tiles
			.map( ( t, k ) => ( { k, r: t.getBoundingClientRect() } ) )
			.filter( ( o ) => ( below ? o.r.top > r.top + 5 : o.r.top < r.top - 5 ) );
		if ( cands.length ) {
			const rowTop = below ? Math.min( ...cands.map( ( o ) => o.r.top ) ) : Math.max( ...cands.map( ( o ) => o.r.top ) );
			const row = cands.filter( ( o ) => Math.abs( o.r.top - rowTop ) < 5 );
			j = row.reduce( ( a, b ) => ( Math.abs( b.r.left - r.left ) < Math.abs( a.r.left - r.left ) ? b : a ) ).k;
		}
	}
	if ( tiles[ j ] && j !== i ) {
		tiles[ j ].focus( { preventScroll: true } );
		tiles[ j ].scrollIntoView( { block: 'nearest' } );
	}
}

/** One generation's tiles. Hover, focus or a tap shows the entry in the popup. */
export function Grid( { rows, onShow, onHide } ) {
	return (
		<ul className="vs-of-grid">
			{ rows.map( ( e, i ) => {
				const nafn = htmlToText( e.nafn );
				const enska = htmlToText( e.enska );
				const icelandic = nafn && nafn !== '–';
				const show = ( ev ) => onShow( e, ev.currentTarget.getBoundingClientRect() );
				return (
					<li key={ `${ e.kynslod }-${ e.numer }-${ i }` }>
						<button
							type="button"
							className="vs-of-tile"
							onMouseEnter={ show }
							onMouseLeave={ onHide }
							onFocus={ show }
							onBlur={ onHide }
							onClick={ show }
							onKeyDown={ ( ev ) => {
								if ( [ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown' ].includes( ev.key ) ) {
									ev.preventDefault();
									moveFocus( ev.key, ev.currentTarget );
								}
							} }
						>
							{ e.mynd_stor || e.mynd ? (
								<img src={ e.mynd_stor || e.mynd } width={ IMG } height={ IMG } alt="" loading="lazy" decoding="async" />
							) : (
								<span className="vs-of-tile__noimg" aria-hidden="true" />
							) }
							<span className="vs-of-tile__num">#{ e.numer }</span>
							<span className="vs-of-tile__name">{ icelandic ? nafn : enska }</span>
							{ icelandic && enska && <span className="vs-of-tile__en">{ enska }</span> }
						</button>
					</li>
				);
			} ) }
		</ul>
	);
}
