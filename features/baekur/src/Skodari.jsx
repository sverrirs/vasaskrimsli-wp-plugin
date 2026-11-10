import { useEffect, useRef, useState } from 'react';
import { kr, myndir } from './leit.js';
import { Stada } from './Baekur.jsx';
import Kaupahnappur from './Kaupahnappur.jsx';

/**
 * The book viewer: a dialog over the page (not a modal one: Shopify's cart must be able to open above it) with the cover and the sample pages (arrow keys or the buttons page through them),
 * the book's details and where to buy it. The previous and next book of the series are one click away.
 */
export default function Skodari( { rod, bokId, data, onOpen } ) {
	const dialog = useRef( null );
	const [ at, setAt ] = useState( 0 );
	const i = rod.baekur.findIndex( ( b ) => b.id === bokId );
	const bok = rod.baekur[ i ];
	const pics = myndir( bok );
	const vara = data.vorur[ bok.vara ];
	const prev = rod.baekur[ i - 1 ];
	const next = rod.baekur[ i + 1 ];

	useEffect( () => {
		const d = dialog.current;
		const before = document.activeElement;
		if ( d && ! d.open ) {
			d.show();
			d.focus();
		}
		document.documentElement.classList.add( 'vs-bk-opin' );
		return () => {
			document.documentElement.classList.remove( 'vs-bk-opin' );
			before?.focus?.( { preventScroll: true } );
		};
	}, [] );

	const step = ( by ) => setAt( ( a ) => ( a + by + pics.length ) % pics.length );
	const onKey = ( e ) => {
		if ( e.target.closest?.( 'a, button' ) && ( e.key === 'Enter' || e.key === ' ' ) ) {
			return;
		}
		if ( e.key === 'Escape' ) {
			dialog.current.close();
		} else if ( e.key === 'ArrowRight' ) {
			step( 1 );
		} else if ( e.key === 'ArrowLeft' ) {
			step( -1 );
		}
	};

	return (
		<>
		<div className="vs-bk-skodari__bak" aria-hidden="true" onClick={ () => dialog.current.close() } />
		{ /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */ }
		<dialog
			ref={ dialog }
			className="vs-bk-skodari"
			aria-modal="true"
			aria-labelledby="vs-bk-skodari-titill"
			tabIndex={ -1 }
			onClose={ () => onOpen( null, null ) }
			onKeyDown={ onKey }
		>
			<div className="vs-bk-skodari__inner">
				<div className="vs-bk-skodari__svid">
					<figure>
						<img src={ data.grunnur + pics[ at ].mynd } alt={ `${ bok.heiti }: ${ pics[ at ].heiti }` } />
						<figcaption>
							{ pics[ at ].heiti } <span>({ at + 1 } af { pics.length })</span>
						</figcaption>
					</figure>
					{ pics.length > 1 && (
						<>
							<button type="button" className="vs-bk-skodari__or is-fyrri" onClick={ () => step( -1 ) } aria-label="Fyrri mynd">
								‹
							</button>
							<button type="button" className="vs-bk-skodari__or is-naesta" onClick={ () => step( 1 ) } aria-label="Næsta mynd">
								›
							</button>
							<ol className="vs-bk-skodari__smamyndir">
								{ pics.map( ( p, k ) => (
									<li key={ p.mynd }>
										<button type="button" aria-current={ k === at } aria-label={ p.heiti } onClick={ () => setAt( k ) }>
											<img src={ data.grunnur + p.mynd } alt="" loading="lazy" />
										</button>
									</li>
								) ) }
							</ol>
						</>
					) }
				</div>
				<div className="vs-bk-skodari__upplysingar">
					<p className="vs-bk-skodari__rod">{ rod.heiti }</p>
					<h2 id="vs-bk-skodari-titill">{ bok.heiti }</h2>
					{ bok.efni && <p>{ bok.efni }</p> }
					<Stada bok={ bok } data={ data } />
					<dl>
						{ bok.sidur > 0 && (
							<>
								<dt>Blaðsíður</dt>
								<dd>{ bok.sidur }</dd>
							</>
						) }
						{ bok.snid && (
							<>
								<dt>Brot</dt>
								<dd>{ bok.snid }</dd>
							</>
						) }
						{ bok.isbn && (
							<>
								<dt>ISBN</dt>
								<dd>{ bok.isbn }</dd>
							</>
						) }
					</dl>
					{ bok.stada === 'faanleg' && vara ? (
						<div className="vs-bk-skodari__kaupa">
							<p>
								Fæst í vefverslun Háskólaprents: <strong>{ vara.heiti }</strong>
							</p>
							{ vara.afbrigdi.length > 1 && (
								<ul>
									{ vara.afbrigdi.map( ( a ) => (
										<li key={ a.heiti }>
											{ a.heiti } <span>{ kr( a.verd ) }</span>
										</li>
									) ) }
								</ul>
							) }
							<Kaupahnappur kaupahnappur={ data.verslun?.kaupahnappur } vara={ vara } onAdded={ () => dialog.current?.close() } />
						</div>
					) : (
						<p className="vs-bk-skodari__vaentanleg">
							Þessi bók er væntanleg og fæst ekki enn.
							{ data.verslun?.pontun && (
								<>
									{ ' ' }Skólar og bókasöfn geta sent fyrirspurn með <a href={ data.verslun.pontun }>pöntunarforminu</a>.
								</>
							) }
						</p>
					) }
					<div className="vs-bk-skodari__flett">
						{ prev && (
							<button type="button" onClick={ () => onOpen( rod.id, prev.id ) }>
								‹ { prev.heiti }
							</button>
						) }
						{ next && (
							<button type="button" onClick={ () => onOpen( rod.id, next.id ) }>
								{ next.heiti } ›
							</button>
						) }
					</div>
				</div>
				<button type="button" className="vs-bk-skodari__loka" aria-label="Loka" onClick={ () => dialog.current.close() }>
					×
				</button>
			</div>
		</dialog>
		</>
	);
}
