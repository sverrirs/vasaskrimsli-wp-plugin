import { useCallback, useEffect, useMemo, useState } from 'react';
import { fjoldi, synilegar, urHash, verd } from './leit.js';
import Skodari from './Skodari.jsx';

/**
 * The catalogue: links between the series (on the Verslun page), then each series with its text, filters and a grid
 * of covers. A click on a cover opens the book in the viewer; the address then ends in #bok=<id>, so it can be shared.
 */
export default function Baekur( { data } ) {
	const [ opin, setOpin ] = useState( () => urHash( window.location.hash, data.radir ) );

	useEffect( () => {
		const onHash = () => setOpin( urHash( window.location.hash, data.radir ) );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [ data.radir ] );

	const open = useCallback( ( rod, bok ) => {
		setOpin( bok ? { rod, bok } : null );
		const url = new URL( window.location.href );
		url.hash = bok ? `bok=${ bok }` : '';
		window.history.replaceState( null, '', url.hash ? url : url.pathname + url.search );
	}, [] );

	const rod = opin && data.radir.find( ( r ) => r.id === opin.rod );

	return (
		<div className="vs-bk">
			{ data.allar && data.radir.length > 1 && (
				<nav className="vs-bk__nav" aria-label="Vöruflokkar">
					<ul>
						{ data.radir.map( ( r ) => (
							<li key={ r.id }>
								<a href={ `#${ r.akkeri }` }>
									{ r.heiti } <span>{ r.baekur.length }</span>
								</a>
							</li>
						) ) }
					</ul>
					{ data.verslun?.pontun && (
						<p className="vs-bk__pontun">
							Ef þú pantar fyrir skóla, bókasafn, frístund eða álíka starfsemi og vilt fá reikning má fylla út{ ' ' }
							<a href={ data.verslun.pontun }>pöntunarform</a>
							{ data.verslun.magnafslattur && (
								<>
									{ ' ' }og skoða <a href={ data.verslun.magnafslattur }>magnafslætti</a>
								</>
							) }
							.
						</p>
					) }
				</nav>
			) }
			{ data.radir.map( ( r ) => (
				<Rod key={ r.id } rod={ r } data={ data } onOpen={ open } />
			) ) }
			{ rod && <Skodari key={ opin.bok } rod={ rod } bokId={ opin.bok } data={ data } onOpen={ open } /> }
		</div>
	);
}

function Rod( { rod, data, onOpen } ) {
	const [ stada, setStada ] = useState( '' );
	const [ leit, setLeit ] = useState( '' );
	const count = useMemo( () => fjoldi( rod.baekur ), [ rod.baekur ] );
	const shown = useMemo( () => synilegar( rod.baekur, { stada, leit } ), [ rod.baekur, stada, leit ] );
	const filters = count.faanleg > 0 && count.vaentanleg > 0;

	return (
		<section className="vs-bk__rod" id={ rod.akkeri }>
			<h2>{ rod.heiti }</h2>
			{ rod.lysing?.map( ( p ) => <p key={ p }>{ p }</p> ) }
			{ rod.atridi?.length > 0 && (
				<ul className="vs-bk__atridi">
					{ rod.atridi.map( ( a ) => <li key={ a }>{ a }</li> ) }
				</ul>
			) }
			{ ( filters || rod.baekur.length > 8 ) && (
				<div className="vs-bk__tools">
					{ filters && (
						<div className="vs-bk__syn" role="group" aria-label="Sýna">
							{ [
								[ '', 'Allar', count.allar ],
								[ 'faanleg', 'Fáanlegar', count.faanleg ],
								[ 'vaentanleg', 'Væntanlegar', count.vaentanleg ],
							].map( ( [ key, label, n ] ) => (
								<button key={ key } type="button" aria-pressed={ stada === key } onClick={ () => setStada( key ) }>
									{ label } <span>{ n }</span>
								</button>
							) ) }
						</div>
					) }
					{ rod.baekur.length > 8 && (
						<label className="vs-bk__leit">
							<span className="screen-reader-text">Leita í { rod.heiti }</span>
							<input type="search" value={ leit } placeholder="Leita að skrímsli eða bók" onChange={ ( e ) => setLeit( e.target.value ) } />
						</label>
					) }
				</div>
			) }
			{ shown.length ? (
				<ul className="vs-bk__grid">
					{ shown.map( ( b ) => (
						<li key={ b.id } className={ `vs-bk__bok is-${ b.stada }` }>
							<button type="button" onClick={ () => onOpen( rod.id, b.id ) }>
								<span className="vs-bk__kapa">
									<img src={ data.grunnur + b.kapa } alt="" loading="lazy" decoding="async" />
								</span>
								<strong>{ b.heiti }</strong>
								{ b.efni && <span className="vs-bk__efni">{ b.efni }</span> }
								<Stada bok={ b } data={ data } />
							</button>
						</li>
					) ) }
				</ul>
			) : (
				<p className="vs-bk__ekkert">Engin bók fannst.</p>
			) }
		</section>
	);
}

export function Stada( { bok, data } ) {
	const price = bok.stada === 'faanleg' ? verd( data.vorur[ bok.vara ] ) : '';
	return (
		<span className={ `vs-bk__stada is-${ bok.stada }` }>
			{ data.stada[ bok.stada ] }
			{ price && `, ${ price }` }
		</span>
	);
}
