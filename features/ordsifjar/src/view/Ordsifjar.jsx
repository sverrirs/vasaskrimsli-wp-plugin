import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { fold, htmlToText, matches } from '../../../../shared/js/search.js';

const PARAMS = { q: 'leit', gen: 'kynslod', flokkur: 'flokkur' };

function readUrl() {
	const p = new URLSearchParams( window.location.search );
	return { q: p.get( PARAMS.q ) ?? '', gen: p.get( PARAMS.gen ) ?? '', flokkur: p.get( PARAMS.flokkur ) ?? '' };
}

function writeUrl( state ) {
	const url = new URL( window.location.href );
	for ( const [ key, param ] of Object.entries( PARAMS ) ) {
		if ( state[ key ] ) {
			url.searchParams.set( param, state[ key ] );
		} else {
			url.searchParams.delete( param );
		}
	}
	window.history.replaceState( null, '', url );
}

const Html = ( { as: Tag = 'span', html, ...rest } ) => <Tag { ...rest } dangerouslySetInnerHTML={ { __html: html } } />;

export default function Ordsifjar( { data } ) {
	const initial = useMemo( readUrl, [] );
	const [ q, setQ ] = useState( initial.q );
	const [ gen, setGen ] = useState( initial.gen );
	const [ flokkur, setFlokkur ] = useState( initial.flokkur );
	const query = useDeferredValue( q );

	useEffect( () => writeUrl( { q, gen, flokkur } ), [ q, gen, flokkur ] );

	const entries = useMemo(
		() =>
			data.entries.map( ( e ) => ( {
				...e,
				hay: fold( [ e.numer, e.nafn, e.uppruni, e.thyding, e.enska, e.athugasemdir, ...( e.flokkar ?? [] ) ].map( htmlToText ).join( ' ' ) ),
			} ) ),
		[ data ]
	);

	const generations = useMemo( () => {
		const known = new Map( data.kynslodir.map( ( k ) => [ k.kynslod, k ] ) );
		for ( const e of entries ) {
			if ( ! known.has( e.kynslod ) ) {
				known.set( e.kynslod, { kynslod: e.kynslod, akkeri: '', heiti_html: `${ e.kynslod }. kynslóð`, skyring_html: '' } );
			}
		}
		return [ ...known.values() ].sort( ( a, b ) => a.kynslod - b.kynslod );
	}, [ data, entries ] );

	const flokkar = useMemo( () => [ ...new Set( entries.flatMap( ( e ) => e.flokkar ?? [] ) ) ].sort( ( a, b ) => a.localeCompare( b, 'is' ) ), [ entries ] );

	const shown = entries.filter(
		( e ) => ( ! gen || String( e.kynslod ) === gen ) && ( ! flokkur || ( e.flokkar ?? [] ).includes( flokkur ) ) && matches( e.hay, query )
	);
	const filtering = Boolean( query.trim() || gen || flokkur );

	const reset = () => {
		setQ( '' );
		setGen( '' );
		setFlokkur( '' );
	};

	return (
		<div className="vs-of">
			<div className="vs-of__tools" role="search">
				<label className="vs-of__search">
					<span className="vs-of__label">Leita</span>
					<input
						type="search"
						value={ q }
						onChange={ ( ev ) => setQ( ev.target.value ) }
						placeholder="Nafn, orðhluti, skýring eða enskt heiti"
						autoComplete="off"
						spellCheck="false"
					/>
				</label>

				<div className="vs-of__filters">
					<div className="vs-of__gens" role="group" aria-label="Kynslóð">
						<button type="button" aria-pressed={ ! gen } onClick={ () => setGen( '' ) }>
							Allar
						</button>
						{ generations.map( ( k ) => (
							<button
								type="button"
								key={ k.kynslod }
								aria-pressed={ gen === String( k.kynslod ) }
								onClick={ () => setGen( gen === String( k.kynslod ) ? '' : String( k.kynslod ) ) }
							>
								{ htmlToText( k.heiti_html ).replace( /\s*kynslóð.*$/i, '' ) || `${ k.kynslod }.` }
							</button>
						) ) }
					</div>

					{ flokkar.length > 0 && (
						<label className="vs-of__flokkur">
							<span className="vs-of__label">Flokkur</span>
							<select value={ flokkur } onChange={ ( ev ) => setFlokkur( ev.target.value ) }>
								<option value="">Allir flokkar</option>
								{ flokkar.map( ( f ) => (
									<option key={ f } value={ f }>
										{ f }
									</option>
								) ) }
							</select>
						</label>
					) }
				</div>

				<p className="vs-of__count" aria-live="polite">
					{ filtering ? `${ shown.length } af ${ entries.length } nöfnum` : `${ entries.length } nöfn` }
					{ filtering && (
						<button type="button" className="vs-of__reset" onClick={ reset }>
							Hreinsa
						</button>
					) }
				</p>
			</div>

			{ shown.length === 0 && (
				<p className="vs-of__empty">
					Ekkert nafn fannst{ query.trim() ? <> fyrir „{ query.trim() }“</> : null }.{ ' ' }
					<button type="button" className="vs-of__reset" onClick={ reset }>
						Sýna öll nöfn
					</button>
				</p>
			) }

			{ generations.map( ( k ) => {
				const rows = shown.filter( ( e ) => e.kynslod === k.kynslod );
				if ( rows.length === 0 && filtering ) {
					return null;
				}
				return (
					<section className="vs-of__gen" key={ k.kynslod }>
						<Html as="h3" className="wp-block-heading" id={ k.akkeri || undefined } html={ k.heiti_html } />
						{ k.skyring_html && <Html as="p" html={ k.skyring_html } /> }
						{ rows.length > 0 && (
							<figure className="wp-block-table vs-of__table">
								<table>
									<thead>
										<tr>
											<th scope="col" className="vs-of__num">Númer</th>
											<th scope="col">Nafn</th>
											<th scope="col" className="vs-of__origin">Upprunaorð</th>
											<th scope="col">Þýðing</th>
											<th scope="col" className="vs-of__en">Enska</th>
										</tr>
									</thead>
									<tbody>
										{ rows.map( ( e, i ) => (
											<tr key={ `${ e.kynslod }-${ e.numer }-${ i }` }>
												<td data-label="Númer" className="vs-of__num">
													{ e.numer }
												</td>
												<td data-label="Nafn" className="vs-of__name">
													<Html html={ e.nafn } />
													{ e.flokkar?.length > 0 && (
														<span className="vs-of__tags">
															{ e.flokkar.map( ( f ) => (
																<button type="button" key={ f } onClick={ () => setFlokkur( f ) }>
																	{ f }
																</button>
															) ) }
														</span>
													) }
												</td>
												<td data-label="Upprunaorð" className="vs-of__origin">
													<Html html={ e.uppruni } />
												</td>
												<td data-label="Þýðing">
													<Html html={ e.thyding } />
													{ e.athugasemdir && <Html as="div" className="vs-of__note" html={ e.athugasemdir } /> }
												</td>
												<td data-label="Enska" className="vs-of__en">
													<Html html={ e.enska } />
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</figure>
						) }
					</section>
				);
			} ) }
		</div>
	);
}
