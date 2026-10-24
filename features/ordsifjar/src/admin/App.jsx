import { useCallback, useDeferredValue, useEffect, useMemo, useState } from 'react';
import { api, config } from './api.js';
import { FlokkarCell, RichCell, TextCell } from './Cells.jsx';
import { HistoryDialog, ImportDialog, KynslodirDialog } from './Dialogs.jsx';
import { fold, htmlToText, matches } from '../../../../shared/js/search.js';

const COPY_FIELDS = [ 'numer', 'kynslod', 'nafn', 'uppruni', 'thyding', 'enska', 'flokkar', 'athugasemdir' ];

export default function App() {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ q, setQ ] = useState( '' );
	const [ gen, setGen ] = useState( '' );
	const [ status, setStatus ] = useState( {} ); // id -> { state: 'saving'|'saved'|'error', message }
	const [ dialog, setDialog ] = useState( null ); // { type, entry? }
	const [ notice, setNotice ] = useState( null );
	const [ fresh, setFresh ] = useState( null ); // id of a just-created row, to scroll to
	const query = useDeferredValue( q );

	const load = useCallback( () => {
		api( '' ).then( setData, ( e ) => setLoadError( e.message ) );
	}, [] );
	useEffect( load, [ load ] );

	useEffect( () => {
		if ( fresh ) {
			document.getElementById( `vs-oa-row-${ fresh }` )?.scrollIntoView( { block: 'center' } );
		}
	}, [ fresh ] );

	const replace = ( entry ) =>
		setData( ( d ) => ( { ...d, entries: d.entries.map( ( e ) => ( e.id === entry.id ? entry : e ) ), flokkar: mergeFlokkar( d.flokkar, entry.flokkar ) } ) );

	const save = async ( id, fields ) => {
		setStatus( ( s ) => ( { ...s, [ id ]: { state: 'saving' } } ) );
		try {
			replace( await api( `/${ id }`, { method: 'PATCH', body: fields } ) );
			setStatus( ( s ) => ( { ...s, [ id ]: { state: 'saved' } } ) );
		} catch ( e ) {
			setStatus( ( s ) => ( { ...s, [ id ]: { state: 'error', message: e.message } } ) );
		}
	};

	const create = async ( fields ) => {
		try {
			const entry = await api( '', { method: 'POST', body: fields } );
			setData( ( d ) => ( { ...d, entries: [ ...d.entries, entry ], flokkar: mergeFlokkar( d.flokkar, entry.flokkar ) } ) );
			setQ( '' );
			setGen( String( entry.kynslod ) );
			setFresh( entry.id );
		} catch ( e ) {
			setNotice( { type: 'error', text: e.message } );
		}
	};

	const trash = async ( entry ) => {
		if ( ! window.confirm( `Færa „${ htmlToText( entry.nafn ) }“ í ruslafötuna? Hægt er að endurheimta færsluna þaðan.` ) ) {
			return;
		}
		try {
			await api( `/${ entry.id }`, { method: 'DELETE' } );
			setData( ( d ) => ( { ...d, entries: d.entries.filter( ( e ) => e.id !== entry.id ) } ) );
			setNotice( { type: 'success', text: `„${ htmlToText( entry.nafn ) }“ var færð í ruslafötuna.`, link: config.trashUrl, linkText: 'Opna ruslafötu' } );
		} catch ( e ) {
			setNotice( { type: 'error', text: e.message } );
		}
	};

	const indexed = useMemo(
		() =>
			( data?.entries ?? [] ).map( ( e ) => ( {
				e,
				hay: fold( [ e.numer, e.nafn, e.uppruni, e.thyding, e.enska, e.athugasemdir, ...e.flokkar ].map( htmlToText ).join( ' ' ) ),
			} ) ),
		[ data ]
	);

	if ( loadError ) {
		return <div className="notice notice-error"><p>Gat ekki hlaðið gögnum: { loadError }</p></div>;
	}
	if ( ! data ) {
		return <p>Hleð…</p>;
	}

	const gens = [ ...new Set( [ ...data.kynslodir.map( ( k ) => k.kynslod ), ...data.entries.map( ( e ) => e.kynslod ) ] ) ].sort( ( a, b ) => a - b );
	const genLabel = ( n ) => htmlToText( data.kynslodir.find( ( k ) => k.kynslod === n )?.heiti_html ?? '' ) || `${ n }. kynslóð`;
	const rows = indexed.filter( ( { e, hay } ) => ( ! gen || String( e.kynslod ) === gen ) && matches( hay, query ) ).map( ( r ) => r.e );

	return (
		<div className="vs-oa">
			<div className="vs-oa-head">
				<h1 className="wp-heading-inline">Orðsifjar</h1>
				<button type="button" className="page-title-action" onClick={ () => create( { nafn: 'Nýtt nafn', kynslod: Number( gen ) || gens[ gens.length - 1 ] || 1, numer: '' } ) }>
					Ný færsla
				</button>
				<span className="vs-oa-spacer" />
				<a className="button" href={ config.csv.html }>Sækja CSV með sniði</a>
				<a className="button" href={ config.csv.plain }>Sækja CSV án sniðs</a>
				<button type="button" className="button" onClick={ () => setDialog( { type: 'import' } ) }>Flytja inn CSV</button>
				<button type="button" className="button" onClick={ () => setDialog( { type: 'kynslodir' } ) }>Kynslóðir</button>
				<a className="button" href={ config.flokkarUrl }>Flokkar</a>
			</div>

			{ notice && (
				<div className={ `notice notice-${ notice.type } is-dismissible inline` }>
					<p>
						{ notice.text } { notice.link && <a href={ notice.link }>{ notice.linkText }</a> }
					</p>
					<button type="button" className="notice-dismiss" onClick={ () => setNotice( null ) }>
						<span className="screen-reader-text">Loka tilkynningu</span>
					</button>
				</div>
			) }

			<div className="vs-oa-filters">
				<label>
					<span className="screen-reader-text">Leita</span>
					<input type="search" className="vs-oa-search" placeholder="Leita í öllum reitum" value={ q } onChange={ ( e ) => setQ( e.target.value ) } />
				</label>
				<label>
					<span className="screen-reader-text">Kynslóð</span>
					<select value={ gen } onChange={ ( e ) => setGen( e.target.value ) }>
						<option value="">Allar kynslóðir</option>
						{ gens.map( ( n ) => (
							<option key={ n } value={ n }>{ genLabel( n ) }</option>
						) ) }
					</select>
				</label>
				<span className="vs-oa-count">{ rows.length } af { data.entries.length } færslum</span>
				<span className="description">Smelltu á reit til að breyta honum. Vistast þegar þú ferð úr reitnum; Esc hættir við.</span>
			</div>

			<div className="vs-oa-scroll">
				<table className="widefat striped vs-oa-grid">
					<thead>
						<tr>
							<th className="col-numer">Númer</th>
							<th className="col-kyn">Kyn.</th>
							<th className="col-nafn">Nafn</th>
							<th className="col-uppruni">Upprunaorð</th>
							<th className="col-thyding">Þýðing</th>
							<th className="col-enska">Enska</th>
							<th className="col-flokkar">Flokkar</th>
							<th className="col-athugasemdir">Athugasemdir</th>
							<th className="col-actions"><span className="screen-reader-text">Aðgerðir</span></th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( e ) => {
							const name = htmlToText( e.nafn );
							const set = ( field ) => ( value ) => save( e.id, { [ field ]: value } );
							const st = status[ e.id ];
							return (
								<tr key={ e.id } id={ `vs-oa-row-${ e.id }` } className={ fresh === e.id ? 'is-fresh' : '' }>
									<td><TextCell value={ e.numer } label={ `Númer: ${ name }` } onSave={ set( 'numer' ) } /></td>
									<td><TextCell value={ e.kynslod } type="number" label={ `Kynslóð: ${ name }` } onSave={ set( 'kynslod' ) } /></td>
									<td><RichCell html={ e.nafn } label={ `Nafn: ${ name }` } onSave={ set( 'nafn' ) } /></td>
									<td><RichCell html={ e.uppruni } label={ `Upprunaorð: ${ name }` } onSave={ set( 'uppruni' ) } /></td>
									<td><RichCell html={ e.thyding } label={ `Þýðing: ${ name }` } onSave={ set( 'thyding' ) } /></td>
									<td><RichCell html={ e.enska } label={ `Enska: ${ name }` } onSave={ set( 'enska' ) } /></td>
									<td><FlokkarCell value={ e.flokkar } all={ data.flokkar } label={ `Flokkar: ${ name }` } onSave={ set( 'flokkar' ) } /></td>
									<td><RichCell html={ e.athugasemdir } label={ `Athugasemdir: ${ name }` } onSave={ set( 'athugasemdir' ) } /></td>
									<td className="col-actions">
										<span className={ `vs-oa-status is-${ st?.state ?? 'idle' }` } role="status" title={ st?.message }>
											{ st?.state === 'saving' && 'Vistar…' }
											{ st?.state === 'saved' && 'Vistað' }
											{ st?.state === 'error' && `Villa: ${ st.message }` }
										</span>
										<button type="button" className="button-link" onClick={ () => setDialog( { type: 'history', entry: e } ) }>Saga</button>
										<button type="button" className="button-link" onClick={ () => create( Object.fromEntries( COPY_FIELDS.map( ( f ) => [ f, e[ f ] ] ) ) ) }>Afrita</button>
										<button type="button" className="button-link is-destructive" onClick={ () => trash( e ) }>Eyða</button>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</div>

			{ dialog?.type === 'history' && <HistoryDialog key={ dialog.entry.id } entry={ dialog.entry } onClose={ () => setDialog( null ) } onRestored={ replace } /> }
			{ dialog?.type === 'import' && <ImportDialog backups={ data.afrit } onClose={ () => setDialog( null ) } onImported={ load } /> }
			{ dialog?.type === 'kynslodir' && (
				<KynslodirDialog kynslodir={ data.kynslodir } onClose={ () => setDialog( null ) } onSaved={ ( k ) => setData( ( d ) => ( { ...d, kynslodir: k } ) ) } />
			) }
		</div>
	);
}

function mergeFlokkar( all, added ) {
	return [ ...new Set( [ ...all, ...added ] ) ].sort( ( a, b ) => a.localeCompare( b, 'is' ) );
}
