import { useEffect, useRef, useState } from 'react';
import { htmlToText } from '../../../../shared/js/search.js';
import { MAX } from './safn.js';

/** How long the second click on „Hreinsa safnið“ is waited for. */
const CONFIRM_MS = 4000;

/**
 * The collection panel on the left edge of the Skrímslaskrá page. Collapsed to a tab until the mouse
 * is over it or it has keyboard focus; a tap on the tab opens and closes it on touch screens.
 */
export default function Safn( { chosen, note, onRemove, onClear, throunUrl, all } ) {
	const [ open, setOpen ] = useState( false );
	const [ busy, setBusy ] = useState( null ); // null, or the number of cards drawn so far.
	const [ error, setError ] = useState( '' );
	const [ shownNote, setShownNote ] = useState( '' );
	const [ confirming, setConfirming ] = useState( false );
	const busyRef = useRef( false );
	const root = useRef( null );

	// Show the panel for a moment when a click added nothing: it is full, or the monster and all its forms are in it.
	useEffect( () => {
		if ( ! note ) {
			return undefined;
		}
		setOpen( true );
		setShownNote( note.why );
		const t = setTimeout( () => {
			setOpen( false );
			setShownNote( '' );
		}, 3500 );
		return () => clearTimeout( t );
	}, [ note ] );

	// The first click on „Hreinsa safnið“ only asks; the bar in the button runs out and a second click before it does clears.
	useEffect( () => {
		if ( ! confirming ) {
			return undefined;
		}
		const t = setTimeout( () => setConfirming( false ), CONFIRM_MS );
		return () => clearTimeout( t );
	}, [ confirming ] );

	const clear = () => {
		if ( ! confirming ) {
			setConfirming( true );
			return;
		}
		setConfirming( false );
		onClear();
	};

	const download = async () => {
		if ( busyRef.current || ! chosen.length ) {
			return;
		}
		busyRef.current = true;
		setBusy( 0 );
		setError( '' );
		try {
			const { buildPdf } = await import( './kort.js' );
			const fam = getComputedStyle( root.current ).fontFamily || 'sans-serif';
			const blob = await buildPdf( chosen, all, throunUrl, fam, setBusy );
			const url = URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.href = url;
			a.download = 'vasaskrimsli-safn.pdf';
			document.body.append( a );
			a.click();
			a.remove();
			setTimeout( () => URL.revokeObjectURL( url ), 60000 );
		} catch {
			setError( 'Ekki tókst að búa til skjalið. Reyndu aftur.' );
		} finally {
			busyRef.current = false;
			setBusy( null );
		}
	};

	const n = chosen.length;
	return (
		<aside ref={ root } className={ 'vs-safn' + ( open ? ' is-open' : '' ) } aria-label="Safnið mitt">
			<button type="button" className="vs-safn__tab" aria-expanded={ open } onClick={ () => setOpen( ! open ) }>
				<span className="vs-safn__tab-label">Safnið mitt</span>
				<span className="vs-safn__count">{ n }</span>
			</button>
			<div className="vs-safn__body">
				<p className="vs-safn__title">
					Safnið mitt <span>{ n } af { MAX }</span>
				</p>
				{ n === 0 && <p className="vs-safn__hint">Smelltu á skrímsli til að setja það í safnið. Hafi það önnur form bætir hver smellur næsta formi við. Taktu skrímsli úr safninu með × hér.</p> }
				{ shownNote && (
					<p className="vs-safn__full" role="status">
						{ shownNote === 'full'
							? 'Safnið er fullt. Taktu skrímsli úr því til að bæta öðru við.'
							: 'Skrímslið og öll form þess eru þegar í safninu.' }
					</p>
				) }
				{ n > 0 && (
					<ol className="vs-safn__list">
						{ chosen.map( ( e ) => {
							const nafn = htmlToText( e.nafn );
							const name = nafn && nafn !== '–' ? nafn : htmlToText( e.enska );
							return (
								<li key={ e.item }>
									{ e.mynd ? <img src={ e.mynd } width="40" height="40" alt="" /> : <span className="vs-safn__noimg" /> }
									<span className="vs-safn__name">
										<span>#{ e.numer }</span> { name }
									</span>
									<button type="button" className="vs-safn__remove" aria-label={ `Taka ${ name } úr safninu` } onClick={ () => onRemove( e.item ) }>
										×
									</button>
								</li>
							);
						} ) }
					</ol>
				) }
				{ error && (
					<p className="vs-safn__full" role="alert">
						{ error }
					</p>
				) }
				<div className="vs-safn__actions">
					<button type="button" className="vs-safn__pdf" disabled={ ! n || busy !== null } aria-busy={ busy !== null } onClick={ download }>
						{ busy !== null ? `Bý til skjal… ${ busy }/${ n }` : 'Sækja PDF' }
					</button>
					<button type="button" className={ 'vs-safn__clear' + ( confirming ? ' is-confirming' : '' ) } disabled={ ! n || busy !== null } onClick={ clear }>
						<span aria-live="polite">{ confirming ? 'Smelltu aftur' : 'Hreinsa safnið' }</span>
						{ confirming && <span className="vs-safn__clear-bar" style={ { animationDuration: `${ CONFIRM_MS }ms` } } aria-hidden="true" /> }
					</button>
				</div>
			</div>
		</aside>
	);
}
