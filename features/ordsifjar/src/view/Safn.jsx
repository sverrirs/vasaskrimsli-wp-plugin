import { useEffect, useRef, useState } from 'react';
import { htmlToText } from '../../../../shared/js/search.js';
import { MAX } from './safn.js';

/**
 * The collection panel on the left edge of the Skrímslaskrá page. Collapsed to a tab until the mouse
 * is over it or it has keyboard focus; a tap on the tab opens and closes it on touch screens.
 */
export default function Safn( { chosen, full, onRemove, onClear, throunUrl, all } ) {
	const [ open, setOpen ] = useState( false );
	const [ busy, setBusy ] = useState( null ); // null, or the number of cards drawn so far.
	const [ error, setError ] = useState( '' );
	const [ fullNote, setFullNote ] = useState( false );
	const busyRef = useRef( false );
	const root = useRef( null );

	// Show the panel for a moment when a click could not add (it is full).
	useEffect( () => {
		if ( ! full ) {
			return undefined;
		}
		setOpen( true );
		setFullNote( true );
		const t = setTimeout( () => {
			setOpen( false );
			setFullNote( false );
		}, 3500 );
		return () => clearTimeout( t );
	}, [ full ] );

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
				{ n === 0 && <p className="vs-safn__hint">Smelltu á skrímsli til að setja það í safnið. Smelltu aftur til að taka það úr því.</p> }
				{ fullNote && (
					<p className="vs-safn__full" role="status">
						Safnið er fullt. Taktu skrímsli úr því til að bæta öðru við.
					</p>
				) }
				{ n > 0 && (
					<ol className="vs-safn__list">
						{ chosen.map( ( e ) => {
							const nafn = htmlToText( e.nafn );
							const name = nafn && nafn !== '–' ? nafn : htmlToText( e.enska );
							return (
								<li key={ e.numer }>
									{ e.mynd ? <img src={ e.mynd } width="40" height="40" alt="" /> : <span className="vs-safn__noimg" /> }
									<span className="vs-safn__name">
										<span>#{ e.numer }</span> { name }
									</span>
									<button type="button" className="vs-safn__remove" aria-label={ `Taka ${ name } úr safninu` } onClick={ () => onRemove( e.numer ) }>
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
					<button type="button" className="vs-safn__clear" disabled={ ! n || busy !== null } onClick={ onClear }>
						Hreinsa safnið
					</button>
				</div>
			</div>
		</aside>
	);
}
