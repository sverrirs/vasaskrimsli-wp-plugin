import { useCallback, useDeferredValue, useEffect, useMemo, useState } from 'react';
import { fold, htmlToText, matches } from '../../../../shared/js/search.js';
import { SpriteTooltip, Thumb } from './Sprite.jsx';
import { Grid } from './Skra.jsx';
import Safn from './Safn.jsx';
import { load, save, toggle } from './safn.js';

const storage = () => {
	try {
		return window.localStorage;
	} catch {
		return null;
	}
};

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

/**
 * @param {Object} props
 * @param {Object} props.data Entries and generation headings from the server.
 * @param {string} props.syn  'tafla' (Orðsifjafræði table with search and filters) or 'skra'
 *                             (Skrímslaskrá: only the image grid, no search or filters).
 */
export default function Ordsifjar( { data, syn = 'tafla' } ) {
	const skra = syn === 'skra';
	// The grid has no search or filters, so it ignores ?leit= etc. and never writes them.
	const initial = useMemo( () => ( skra ? { q: '', gen: '', flokkur: '' } : readUrl() ), [ skra ] );
	const [ q, setQ ] = useState( initial.q );
	const [ gen, setGen ] = useState( initial.gen );
	const [ flokkur, setFlokkur ] = useState( initial.flokkur );
	const query = useDeferredValue( q );
	const [ tip, setTip ] = useState( null );
	const showTip = useCallback( ( entry, rect ) => setTip( { entry, rect } ), [] );
	const hideTip = useCallback( () => setTip( null ), [] );
	// The visitor's collection (Skrímslaskrá only): Númer in the order they were added.
	const [ safn, setSafn ] = useState( () => ( skra ? load( storage() ) : [] ) );
	const [ full, setFull ] = useState( 0 );
	const pick = useCallback( ( numer ) => {
		setSafn( ( list ) => {
			const r = toggle( list, numer );
			if ( r.full ) {
				setFull( Date.now() );
			}
			return r.list;
		} );
	}, [] );
	useEffect( () => {
		if ( skra ) {
			save( storage(), safn );
		}
	}, [ skra, safn ] );

	useEffect( () => {
		if ( ! skra ) {
			writeUrl( { q, gen, flokkur } );
		}
	}, [ skra, q, gen, flokkur ] );

	const entries = useMemo(
		() =>
			data.entries.map( ( e ) => ( {
				...e,
				hay: fold( [ e.numer, e.nafn, e.uppruni, e.thyding, e.enska, e.athugasemdir, e.serstakt, ...( e.flokkar ?? [] ) ].map( htmlToText ).join( ' ' ) ),
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
		<div className={ 'vs-of' + ( skra ? ' is-skra' : '' ) }>
			{ ! skra && (
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
			) }

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
						{ rows.length > 0 && skra && <Grid rows={ rows } onShow={ showTip } onHide={ hideTip } chosen={ safn } onPick={ pick } /> }
						{ rows.length > 0 && ! skra && (
							<figure className="wp-block-table vs-of__table">
								<table>
									<thead>
										<tr>
											<th scope="col" className="vs-of__who">Nafn</th>
											<th scope="col" className="vs-of__origin">Upprunaorð</th>
											<th scope="col">Þýðing</th>
											<th scope="col" className="vs-of__en">Enska</th>
										</tr>
									</thead>
									<tbody>
										{ rows.map( ( e, i ) => (
											<tr key={ `${ e.kynslod }-${ e.numer }-${ i }` }>
												<td className="vs-of__who">
													<Thumb entry={ e } onShow={ showTip } onHide={ hideTip } />
													<span className="vs-of__num">#{ e.numer }</span>
													<Html className="vs-of__name" html={ e.nafn } />
													{ e.serstakt && <span className="vs-of-mark">{ e.serstakt }</span> }
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
			<SpriteTooltip tip={ tip } onHide={ hideTip } detail={ skra } />
			{ skra && (
				<Safn
					chosen={ safn.map( ( n ) => entries.find( ( e ) => String( e.numer ) === n ) ).filter( Boolean ) }
					full={ full }
					onRemove={ pick }
					onClear={ () => setSafn( [] ) }
					throunUrl={ data.throun }
					all={ entries }
				/>
			) }
		</div>
	);
}
