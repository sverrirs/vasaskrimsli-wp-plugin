import { useEffect, useState } from 'react';
import { htmlToText } from '../../../../shared/js/search.js';

/** Image sizes for the Skrímslaskrá grid. "src" picks the 96 px thumbnail or the 256 px image. */
export const SIZES = [
	{ key: 'litil', label: 'Lítil', px: 64, src: 'mynd' },
	{ key: 'midlungs', label: 'Miðlungs', px: 112, src: 'mynd_stor' },
	{ key: 'stor', label: 'Stór', px: 176, src: 'mynd_stor' },
];
const STORE = 'vs-skrimslaskra-staerd';

/** The visitor's chosen size, remembered in this browser only (falls back to Miðlungs). */
export function useSize() {
	const [ size, setSize ] = useState( () => {
		try {
			const saved = window.localStorage.getItem( STORE );
			return SIZES.some( ( s ) => s.key === saved ) ? saved : 'midlungs';
		} catch {
			return 'midlungs';
		}
	} );
	useEffect( () => {
		try {
			window.localStorage.setItem( STORE, size );
		} catch {
			// Private mode or blocked storage: the choice just is not remembered.
		}
	}, [ size ] );
	return [ size, setSize ];
}

export function SizePicker( { size, onChange } ) {
	return (
		<div className="vs-of__sizes" role="group" aria-label="Stærð mynda">
			<span className="vs-of__label">Stærð</span>
			<div className="vs-of__seg">
				{ SIZES.map( ( s ) => (
					<button type="button" key={ s.key } aria-pressed={ size === s.key } onClick={ () => onChange( s.key ) }>
						{ s.label }
					</button>
				) ) }
			</div>
		</div>
	);
}

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
export function Grid( { rows, size, onShow, onHide } ) {
	const s = SIZES.find( ( x ) => x.key === size ) ?? SIZES[ 1 ];
	return (
		<ul className={ `vs-of-grid is-${ s.key }` } style={ { '--tile-img': `${ s.px }px` } }>
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
							{ e[ s.src ] || e.mynd ? (
								<img src={ e[ s.src ] || e.mynd } width={ s.px } height={ s.px } alt="" loading="lazy" decoding="async" />
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
