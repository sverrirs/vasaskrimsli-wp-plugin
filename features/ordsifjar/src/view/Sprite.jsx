import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { htmlToText } from '../../../../shared/js/search.js';

const LARGE = 256;
const GAP = 12;
const FORMS = 300; // Width of the forms card.

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

/**
 * The single floating card, rendered into <body> so table overflow never clips it.
 * With `detail` (Skrímslaskrá grid) it also shows Upprunaorð and Þýðing, which the grid has no room for.
 */
export function SpriteTooltip( { tip, onHide, detail = false } ) {
	const [ , force ] = useState( 0 );
	const card = useRef( null );
	const [ height, setHeight ] = useState( LARGE + 76 );
	const formsCard = useRef( null );
	const [ formsHeight, setFormsHeight ] = useState( 200 );

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

	// Measure the real card so a long Þýðing still keeps it on screen.
	useLayoutEffect( () => {
		if ( card.current && card.current.offsetHeight !== height ) {
			setHeight( card.current.offsetHeight );
		}
		if ( formsCard.current && formsCard.current.offsetHeight !== formsHeight ) {
			setFormsHeight( formsCard.current.offsetHeight );
		}
	} );

	if ( ! tip ) {
		return null;
	}
	const { entry, rect } = tip;
	const width = detail ? 340 : LARGE + 32;
	// Right of the anchor, or left of it when there is no room; vertically centred, kept on screen.
	let left = rect.right + GAP;
	if ( left + width > window.innerWidth - 8 ) {
		left = Math.max( 8, rect.left - GAP - width );
	}
	const top = Math.min( Math.max( 8, rect.top + rect.height / 2 - height / 2 ), Math.max( 8, window.innerHeight - height - 8 ) );
	const enska = htmlToText( entry.enska );
	const nafn = htmlToText( entry.nafn );
	const has = ( html ) => html && htmlToText( html ) !== '–';
	const formir = entry.formir ?? [];
	// The forms card goes on the far side of the entry card, or on the other side of the anchor when there is no room.
	let formsLeft = left > rect.left ? left + width + 8 : left - 8 - FORMS;
	if ( formsLeft + FORMS > window.innerWidth - 8 ) {
		formsLeft = Math.min( rect.left, left ) - GAP - FORMS;
	}
	if ( formsLeft < 8 ) {
		formsLeft = Math.max( 8, Math.min( window.innerWidth - FORMS - 8, left ) );
	}
	const formsTop = formsLeft === left || ( formsLeft < left + width && formsLeft + FORMS > left )
		? Math.min( top + height + 8, Math.max( 8, window.innerHeight - formsHeight - 8 ) ) // Overlaps: below instead.
		: Math.min( Math.max( 8, top ), Math.max( 8, window.innerHeight - formsHeight - 8 ) );

	return createPortal(
		<>
		<div ref={ card } className={ 'vs-of-tip' + ( detail ? ' is-detail' : '' ) } role="tooltip" style={ { left, top, width } }>
			<img src={ entry.mynd_stor || entry.mynd } width={ LARGE } height={ LARGE } alt="" decoding="async" />
			<p className="vs-of-tip__name">
				<span className="vs-of-tip__num">#{ entry.numer }</span> { nafn && nafn !== '–' ? nafn : enska }
			</p>
			{ nafn && nafn !== '–' && enska && <p className="vs-of-tip__en">{ enska }</p> }
			{ entry.serstakt && <p><span className="vs-of-mark">{ entry.serstakt }</span></p> }
			{ detail && has( entry.uppruni ) && (
				<p className="vs-of-tip__origin" dangerouslySetInnerHTML={ { __html: entry.uppruni } } />
			) }
			{ detail && has( entry.thyding ) && (
				<div className="vs-of-tip__text" dangerouslySetInnerHTML={ { __html: entry.thyding } } />
			) }
		</div>
		{ formir.length > 0 && (
			<div ref={ formsCard } className="vs-of-tip vs-of-forms" role="tooltip" style={ { left: formsLeft, top: formsTop, width: FORMS } }>
				<p className="vs-of-forms__title">Önnur form</p>
				<ul>
					{ formir.map( ( f ) => (
						<li key={ f.heiti }>
							{ f.mynd ? <img src={ f.mynd } width="96" height="96" alt="" decoding="async" /> : <span className="vs-of-forms__noimg" /> }
							<span>{ f.heiti }</span>
						</li>
					) ) }
				</ul>
			</div>
		) }
		</>,
		document.body
	);
}
