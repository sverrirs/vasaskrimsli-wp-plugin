import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { htmlToText } from '../../../../shared/js/search.js';

const LARGE = 256;
const GAP = 12;

/**
 * Thumbnail in the first column. Hover, keyboard focus or a tap shows a larger image in a
 * tooltip card next to it; leaving, Escape, scrolling or a tap elsewhere hides it.
 */
export function Thumb( { entry, onShow, onHide } ) {
	const name = htmlToText( entry.enska ) || htmlToText( entry.nafn );
	if ( ! entry.mynd ) {
		return null;
	}
	const show = ( ev ) => onShow( entry, ev.currentTarget.getBoundingClientRect() );
	return (
		<button
			type="button"
			className="vs-of__thumb"
			aria-label={ `Stærri mynd: ${ name }` }
			onMouseEnter={ show }
			onMouseLeave={ onHide }
			onFocus={ show }
			onBlur={ onHide }
			onClick={ show }
		>
			<img src={ entry.mynd } width="48" height="48" alt="" loading="lazy" decoding="async" />
		</button>
	);
}

/** The single floating card, rendered into <body> so table overflow never clips it. */
export function SpriteTooltip( { tip, onHide } ) {
	const [ , force ] = useState( 0 );

	useEffect( () => {
		if ( ! tip ) {
			return undefined;
		}
		const hide = () => onHide();
		const key = ( e ) => e.key === 'Escape' && onHide();
		const resize = () => force( ( n ) => n + 1 );
		window.addEventListener( 'scroll', hide, { passive: true, capture: true } );
		window.addEventListener( 'keydown', key );
		window.addEventListener( 'resize', resize );
		return () => {
			window.removeEventListener( 'scroll', hide, { capture: true } );
			window.removeEventListener( 'keydown', key );
			window.removeEventListener( 'resize', resize );
		};
	}, [ tip, onHide ] );

	if ( ! tip ) {
		return null;
	}
	const { entry, rect } = tip;
	const width = LARGE + 32;
	const height = LARGE + 76;
	// Right of the thumbnail, or left of it when there is no room; vertically centred, kept on screen.
	let left = rect.right + GAP;
	if ( left + width > window.innerWidth - 8 ) {
		left = Math.max( 8, rect.left - GAP - width );
	}
	const top = Math.min( Math.max( 8, rect.top + rect.height / 2 - height / 2 ), window.innerHeight - height - 8 );
	const enska = htmlToText( entry.enska );
	const nafn = htmlToText( entry.nafn );

	return createPortal(
		<div className="vs-of-tip" role="tooltip" style={ { left, top, width } }>
			<img src={ entry.mynd_stor || entry.mynd } width={ LARGE } height={ LARGE } alt="" decoding="async" />
			<p className="vs-of-tip__name">
				<span className="vs-of-tip__num">#{ entry.numer }</span> { nafn && nafn !== '–' ? nafn : enska }
			</p>
			{ nafn && nafn !== '–' && enska && <p className="vs-of-tip__en">{ enska }</p> }
		</div>,
		document.body
	);
}
