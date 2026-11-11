import { useCallback, useEffect, useMemo, useState } from 'react';
import { atridi, fjoldi, synilegar, urHash, verd } from './leit.js';
import Skodari from './Skodari.jsx';

/**
 * One series' list: a filter (when some are on sale and some coming) and a grid of covers, or of lots for a series
 * sold in lots. A click opens the viewer; the address then ends in #bok=<id>, so it can be shared.
 */
export default function Baekur( { data } ) {
	const items = useMemo( () => atridi( data.rod ), [ data.rod ] );
	const [ opin, setOpin ] = useState( () => urHash( window.location.hash, items ) );
	const [ stada, setStada ] = useState( '' );

	useEffect( () => {
		const onHash = () => setOpin( urHash( window.location.hash, items ) );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [ items ] );

	const open = useCallback( ( id ) => {
		setOpin( id );
		const url = new URL( window.location.href );
		url.hash = id ? `bok=${ id }` : '';
		window.history.replaceState( null, '', url.hash ? url : url.pathname + url.search );
	}, [] );

	const count = useMemo( () => fjoldi( items ), [ items ] );
	const shown = useMemo( () => synilegar( items, stada ), [ items, stada ] );

	return (
		<div className="vs-bk">
			{ count.faanleg > 0 && count.vaentanleg > 0 && (
				<div className="vs-bk__syn" role="group" aria-label="Sýna">
					{ [
						[ '', data.ord.oll, count.allar ],
						[ 'faanleg', data.ord.faanlegar, count.faanleg ],
						[ 'vaentanleg', data.ord.vaentanlegar, count.vaentanleg ],
					].map( ( [ key, label, n ] ) => (
						<button key={ key } type="button" aria-pressed={ stada === key } onClick={ () => setStada( key ) }>
							{ label } <span>{ n }</span>
						</button>
					) ) }
				</div>
			) }
			<ul className="vs-bk__grid">
				{ shown.map( ( b ) => (
					<li key={ b.id } className={ `vs-bk__bok is-${ b.stada }${ b.kapur ? ' is-pakki' : '' }` }>
						<button type="button" onClick={ () => open( b.id ) }>
							<span className="vs-bk__kapa">
								{ b.kapur ? (
									b.kapur
										.slice()
										.reverse()
										.map( ( k ) => <img key={ k } src={ data.grunnur + k } alt="" loading="lazy" decoding="async" /> )
								) : (
									<img src={ data.grunnur + b.kapa } alt="" loading="lazy" decoding="async" />
								) }
							</span>
							<span className="vs-bk__texti">
								<strong>{ b.heiti }</strong>
								{ b.efni && <span className="vs-bk__efni">{ b.efni }</span> }
								<Stada bok={ b } data={ data } />
							</span>
						</button>
					</li>
				) ) }
			</ul>
			{ opin && <Skodari key={ opin } items={ items } id={ opin } data={ data } onOpen={ open } /> }
		</div>
	);
}

export function Stada( { bok, data } ) {
	const price = bok.stada === 'faanleg' ? verd( data.vorur[ bok.vara ] ) : '';
	return (
		<span className={ `vs-bk__stada is-${ bok.stada }` }>
			{ data.ord[ bok.stada ] }
			{ price && `, ${ price }` }
		</span>
	);
}
