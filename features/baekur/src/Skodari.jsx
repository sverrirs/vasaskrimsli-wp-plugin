import { useEffect, useRef, useState } from 'react';
import { kr, myndir, skref } from './leit.js';
import { Stada } from './Baekur.jsx';
import Kaupahnappur from './Kaupahnappur.jsx';

/**
 * The viewer: a dialog over the page (not a modal one: Shopify's cart must be able to open above it) with a book's
 * cover and sample pages (arrow keys or the buttons page through them), its details (folded away under the pictures)
 * and where to buy it. For a lot
 * it lists the lot's books; choosing one shows that book. The previous and next item of the list are one click away.
 */
export default function Skodari( { items, id, data, onOpen } ) {
	const dialog = useRef( null );
	const i = items.findIndex( ( b ) => b.id === id );
	const item = items[ i ];
	const lot = item.baekur ?? null;
	const [ valin, setValin ] = useState( 0 );
	const [ at, setAt ] = useState( 0 );
	const bok = lot ? lot[ valin ] : item;
	const pics = myndir( bok );
	const vara = data.vorur[ item.vara ];
	const prev = items[ i - 1 ];
	const next = items[ i + 1 ];

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

	const choose = ( k ) => {
		setValin( k );
		setAt( 0 );
	};
	// In a lot the arrows go on through every book's pictures; for one book they go round its own.
	const step = ( by ) => {
		if ( ! lot ) {
			setAt( ( a ) => ( a + by + pics.length ) % pics.length );
			return;
		}
		const [ b, a ] = skref( lot.map( ( x ) => myndir( x ).length ), [ valin, at ], by );
		setValin( b );
		setAt( a );
	};
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
				onClose={ () => onOpen( null ) }
				onKeyDown={ onKey }
			>
				<div className="vs-bk-skodari__inner">
					<div className="vs-bk-skodari__svid">
						{ lot && (
							<ol className="vs-bk-skodari__baekur" aria-label="Bækurnar í pakkanum">
								{ lot.map( ( b, k ) => (
									<li key={ b.id }>
										<button type="button" aria-current={ k === valin } aria-label={ b.heiti } onClick={ () => choose( k ) }>
											<img src={ data.grunnur + b.kapa } alt="" loading="lazy" />
										</button>
									</li>
								) ) }
							</ol>
						) }
						<figure>
							<img src={ data.grunnur + pics[ at ].mynd } alt={ `${ bok.heiti }: ${ pics[ at ].heiti }` } />
							<figcaption>
								{ lot && `${ bok.heiti }: ` }
								{ pics[ at ].heiti } <span>({ at + 1 } af { pics.length })</span>
							</figcaption>
						</figure>
						{ ( lot || pics.length > 1 ) && (
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
						<details className="vs-bk-skodari__nanar">
							<summary>Nánar um { bok.heiti }</summary>
							{ lot && bok.efni && <p>{ bok.efni }</p> }
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
						</details>
					</div>
					<div className="vs-bk-skodari__upplysingar">
						<p className="vs-bk-skodari__rod">{ data.rod.heiti }</p>
						<h2 id="vs-bk-skodari-titill">{ item.heiti }</h2>
						{ ! lot && item.efni && <p>{ item.efni }</p> }
						<Stada bok={ item } data={ data } />
						{ lot && (
							<div className="vs-bk-skodari__innihald">
								<p>Í pakkanum eru { lot.length } bækur:</p>
								<ol>
									{ lot.map( ( b, k ) => (
										<li key={ b.id }>
											<button type="button" aria-current={ k === valin } onClick={ () => choose( k ) }>
												<strong>{ b.heiti }</strong>
												{ b.efni && <span>{ b.efni }</span> }
											</button>
										</li>
									) ) }
								</ol>
							</div>
						) }
						{ item.stada === 'faanleg' && vara ? (
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
								{ lot ? 'Þessi pakki er væntanlegur og fæst ekki enn.' : 'Þessi bók er væntanleg og fæst ekki enn.' }
								{ data.verslun?.pontun && (
									<>
										{ ' ' }Skólar og bókasöfn geta sent fyrirspurn með <a href={ data.verslun.pontun }>pöntunarforminu</a>.
									</>
								) }
							</p>
						) }
						<div className="vs-bk-skodari__flett">
							{ prev && (
								<button type="button" onClick={ () => onOpen( prev.id ) }>
									‹ { prev.heiti }
								</button>
							) }
							{ next && (
								<button type="button" onClick={ () => onOpen( next.id ) }>
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
