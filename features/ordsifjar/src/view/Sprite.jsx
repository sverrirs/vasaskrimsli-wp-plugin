import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { htmlToText } from '../../../../shared/js/search.js';

const LARGE = 256;
const GAP = 12;
const FORM = 212; // Width of one form card.
const FORM_GAP = 8;
const THUMB = 96; // Table picture, from the 256 px image so it stays sharp.

/**
 * Picture in the first column. Hover, keyboard focus or a tap shows a larger image in a
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
			<img src={ entry.mynd_stor || entry.mynd } width={ THUMB } height={ THUMB } alt="" loading="lazy" decoding="async" />
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
	const [ formsHeight, setFormsHeight ] = useState( 280 );
	// Form cards too tall for the window: smaller pictures for that entry, then scaled down to fit.
	const [ compactFor, setCompactFor ] = useState( null );
	const key = tip ? `${ tip.entry.kynslod }-${ tip.entry.numer }` : null;

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
		if ( formsCard.current && compactFor !== key && formsCard.current.offsetHeight > window.innerHeight - 16 ) {
			setCompactFor( key );
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
	// Every form as its own card, all at once, in the larger free area beside the entry card and the anchor;
	// below the entry card when neither side has room for one card.
	const mainLeft = Math.min( left, rect.left );
	const mainRight = Math.max( left + width, rect.right );
	const spaceLeft = mainLeft - GAP - 8;
	const spaceRight = window.innerWidth - 8 - ( mainRight + GAP );
	const fit = ( space ) => Math.max( 0, Math.min( formir.length, Math.floor( ( space + FORM_GAP ) / ( FORM + FORM_GAP ) ) ) );
	const cols = Math.max( fit( spaceLeft ), fit( spaceRight ) );
	// No room beside it (phones): smaller cards, two to a row, under the entry card.
	const small = Math.min( FORM, Math.floor( ( window.innerWidth - 16 - FORM_GAP ) / 2 ) );
	const formW = cols ? FORM : small;
	const formsWidth = cols ? cols * ( FORM + FORM_GAP ) - FORM_GAP : Math.min( formir.length, 2 ) * ( small + FORM_GAP ) - FORM_GAP;
	const compact = compactFor === key;
	const scale = Math.min( 1, ( window.innerHeight - 16 ) / formsHeight );
	const shown = formsHeight * scale;
	let formsLeft;
	let formsTop;
	let formsSide = '';
	if ( cols ) {
		formsSide = fit( spaceRight ) >= fit( spaceLeft ) ? '' : ' is-left'; // On the left, a short last row sits next to the tile.
		formsLeft = formsSide ? mainLeft - GAP - formsWidth : mainRight + GAP;
		formsTop = Math.min( Math.max( 8, rect.top + rect.height / 2 - shown / 2 ), Math.max( 8, window.innerHeight - shown - 8 ) );
	} else {
		formsLeft = Math.max( 8, Math.min( window.innerWidth - formsWidth - 8, left ) );
		formsTop = Math.max( 8, Math.min( top + height + 8, window.innerHeight - shown - 8 ) );
	}
	const name = nafn && nafn !== '–' ? nafn : enska;

	return createPortal(
		<>
		<div ref={ card } className={ 'vs-of-tip' } role="tooltip" style={ { left, top, width } }>
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
			<div ref={ formsCard } className={ 'vs-of-forms' + formsSide + ( compact ? ' is-compact' : '' ) } role="tooltip" style={ { left: formsLeft, top: formsTop, width: formsWidth, '--form-w': `${ formW }px`, transform: scale < 1 ? `scale(${ scale })` : undefined, transformOrigin: formsSide ? 'top right' : 'top left' } }>
				{ formir.map( ( f, i ) => (
					<div className="vs-of-forms__card" key={ f.heiti }>
						{ f.mynd ? <img src={ f.mynd } width="180" height="180" alt="" decoding="async" /> : <span className="vs-of-forms__noimg" /> }
						<p className="vs-of-tip__name">{ f.heiti }</p>
						<p className="vs-of-tip__en">
							Form { i + 1 } af { formir.length } · #{ entry.numer } { name }
						</p>
					</div>
				) ) }
			</div>
		) }
		</>,
		document.body
	);
}
