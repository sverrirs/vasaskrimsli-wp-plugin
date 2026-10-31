import { htmlToText } from '../../../../shared/js/search.js';
import { add, itemsOf, split } from './safn.js';

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

/**
 * One generation's tiles. Hover, focus or a tap shows the entry in the popup; a click (or Enter) also puts
 * it in the visitor's collection. Further clicks add its forms one by one (the popup marks the one added);
 * clicks never take anything out, the × in the collection panel does.
 */
export function Grid( { rows, onShow, onHide, chosen = [], onPick } ) {
	return (
		<ul className="vs-of-grid">
			{ rows.map( ( e, i ) => {
				const items = itemsOf( chosen, e.numer );
				const picked = items.length > 0;
				const total = 1 + ( e.formir?.length ?? 0 );
				const nafn = htmlToText( e.nafn );
				const enska = htmlToText( e.enska );
				const icelandic = nafn && nafn !== '–';
				// added: on a click, the form ('' the monster itself) the click puts in the collection.
				const show = ( ev, added ) => onShow( e, ev.currentTarget.getBoundingClientRect(), added );
				return (
					<li key={ `${ e.kynslod }-${ e.numer }-${ i }` }>
						<button
							type="button"
							className={ 'vs-of-tile' + ( picked ? ' is-picked' : '' ) }
							aria-pressed={ picked }
							onMouseEnter={ show }
							onMouseLeave={ onHide }
							onFocus={ show }
							onBlur={ onHide }
							onClick={ ( ev ) => {
								const added = add( chosen, e.numer, ( e.formir ?? [] ).map( ( f ) => f.id ) ).list.find( ( x ) => ! chosen.includes( x ) );
								show( ev, added === undefined ? undefined : split( added ).form );
								onPick?.( e );
							} }
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
							{ e.serstakt && <span className="vs-of-mark">{ e.serstakt }</span> }
							{ e.formir?.length > 0 && (
								<span className="vs-of-tile__forms" title={ `${ e.formir.length } önnur form` }>
									+{ e.formir.length } form
								</span>
							) }
							{ picked && (
								<span className={ 'vs-of-tile__picked' + ( total > 1 ? ' is-form' : '' ) } aria-hidden="true">
									{ total > 1 ? `✓ ${ items.length }/${ total }` : '✓' }
								</span>
							) }
						</button>
					</li>
				);
			} ) }
		</ul>
	);
}
