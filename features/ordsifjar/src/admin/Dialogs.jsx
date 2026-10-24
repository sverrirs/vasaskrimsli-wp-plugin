import { useEffect, useRef, useState } from 'react';
import { api, backupUrl } from './api.js';
import RichEditor from './RichEditor.jsx';
import { htmlToText } from '../../../../shared/js/search.js';

const LABELS = { numer: 'Númer', kynslod: 'Kynslóð', nafn: 'Nafn', uppruni: 'Upprunaorð', thyding: 'Þýðing', enska: 'Enska', flokkar: 'Flokkar', athugasemdir: 'Athugasemdir' };

/** Native <dialog>, opened as a modal on mount. */
function Dialog( { title, onClose, children, wide = false } ) {
	const ref = useRef( null );
	const closed = useRef( onClose );
	closed.current = onClose;
	useEffect( () => {
		// Closing goes through the app's state (onClose unmounts the dialog) instead of relying on the
		// dialog's own "close" event, which is not always delivered. Esc arrives as "cancel".
		const el = ref.current;
		const cancel = ( e ) => {
			e.preventDefault();
			closed.current();
		};
		el.addEventListener( 'cancel', cancel );
		el.showModal();
		return () => el.removeEventListener( 'cancel', cancel );
	}, [] );
	return (
		<dialog ref={ ref } className={ 'vs-oa-dialog' + ( wide ? ' is-wide' : '' ) } aria-label={ title }>
			<header className="vs-oa-dialog__head">
				<h2>{ title }</h2>
				<button type="button" className="button-link" onClick={ () => closed.current() }>
					Loka
				</button>
			</header>
			<div className="vs-oa-dialog__body">{ children }</div>
		</dialog>
	);
}

function show( field, value ) {
	if ( field === 'flokkar' ) {
		return ( value ?? [] ).join( '; ' ) || '(tómt)';
	}
	if ( field === 'numer' || field === 'kynslod' ) {
		return String( value ?? '' ) || '(tómt)';
	}
	return value ? <span dangerouslySetInnerHTML={ { __html: value } } /> : '(tómt)';
}

export function HistoryDialog( { entry, onClose, onRestored } ) {
	const [ revisions, setRevisions ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( 0 );

	useEffect( () => {
		api( `/${ entry.id }/saga` ).then( setRevisions, ( e ) => setError( e.message ) );
	}, [ entry.id ] );

	const restore = async ( rev ) => {
		if ( ! window.confirm( 'Endurheimta þessa útgáfu? Núverandi útgáfa verður áfram í sögunni.' ) ) {
			return;
		}
		setBusy( rev.id );
		try {
			onRestored( await api( `/${ entry.id }/saga/${ rev.id }`, { method: 'POST' } ) );
			onClose();
		} catch ( e ) {
			setError( e.message );
			setBusy( 0 );
		}
	};

	return (
		<Dialog title={ `Saga: ${ htmlToText( entry.nafn ) }` } onClose={ onClose } wide>
			{ error && <div className="notice notice-error inline"><p>{ error }</p></div> }
			{ ! revisions && ! error && <p>Hleð sögu…</p> }
			{ revisions?.length === 0 && <p>Engar eldri útgáfur eru til af þessari færslu.</p> }
			{ revisions?.map( ( rev, i ) => (
				<details className="vs-oa-rev" key={ rev.id } open={ i === 0 }>
					<summary>
						{ new Date( rev.date ).toLocaleString( 'is-IS' ) }
						{ rev.author && ` · ${ rev.author }` }
						{ i === 0 && ' · núverandi' }
					</summary>
					<dl>
						{ Object.entries( rev.fields ).map( ( [ f, v ] ) => (
							<div key={ f } className={ String( v ) !== String( entry[ f ] ?? '' ) ? 'is-different' : '' }>
								<dt>{ LABELS[ f ] }</dt>
								<dd>{ show( f, v ) }</dd>
							</div>
						) ) }
					</dl>
					{ i > 0 && (
						<button type="button" className="button" disabled={ busy === rev.id } onClick={ () => restore( rev ) }>
							{ busy === rev.id ? 'Endurheimti…' : 'Endurheimta þessa útgáfu' }
						</button>
					) }
				</details>
			) ) }
		</Dialog>
	);
}

export function ImportDialog( { backups, onClose, onImported } ) {
	const [ preview, setPreview ] = useState( null );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const upload = async ( file ) => {
		if ( ! file ) {
			return;
		}
		setError( '' );
		setPreview( null );
		setBusy( true );
		const form = new FormData();
		form.append( 'skra', file );
		try {
			setPreview( await api( '/innflutningur', { method: 'POST', form } ) );
		} catch ( e ) {
			setError( e.message );
		}
		setBusy( false );
	};

	const apply = async () => {
		setBusy( true );
		setError( '' );
		try {
			const r = await api( `/innflutningur/${ preview.token }`, { method: 'POST' } );
			setResult( r );
			setPreview( null );
			onImported();
		} catch ( e ) {
			setError( e.message );
		}
		setBusy( false );
	};

	const nothing = preview && preview.create.length === 0 && preview.update.length === 0;

	return (
		<Dialog title="Flytja inn CSV" onClose={ onClose } wide>
			{ ! result && (
				<>
					<p>
						Veldu CSV skrá, annaðhvort „með sniði“ eða „án sniðs“ útflutninginn. Fyrst sérðu forskoðun; engu er breytt fyrr en þú staðfestir.
						Línur með <code>id</code> uppfæra þá færslu, línur án <code>id</code> verða nýjar færslur. Færslum sem vantar í skrána er hvorki breytt né eytt.
					</p>
					<p>
						<input type="file" accept=".csv,text/csv" disabled={ busy } onChange={ ( e ) => upload( e.target.files?.[ 0 ] ) } />
					</p>
				</>
			) }
			{ busy && <p>Augnablik…</p> }
			{ error && <div className="notice notice-error inline"><p>{ error }</p></div> }

			{ preview && (
				<div className="vs-oa-preview">
					<ul className="vs-oa-stats">
						<li><strong>{ preview.create.length }</strong> nýjar</li>
						<li><strong>{ preview.update.length }</strong> breyttar</li>
						<li><strong>{ preview.unchanged }</strong> óbreyttar</li>
						<li><strong>{ preview.not_in_file }</strong> ekki í skránni (óbreyttar)</li>
					</ul>

					{ preview.errors.length > 0 && (
						<div className="notice notice-error inline">
							<p><strong>Laga þarf þessar villur í skránni áður en hægt er að flytja inn:</strong></p>
							<ul>{ preview.errors.map( ( e ) => <li key={ e }>{ e }</li> ) }</ul>
						</div>
					) }

					{ preview.update.some( ( u ) => u.formatting_lost.length ) && (
						<div className="notice notice-warning inline">
							<p>Sumir breyttir reitir voru með sniði (feitletrun, skáletrun eða tengla). Skráin er án sniðs, svo þeir reitir verða ósniðinn texti. Merktir hér að neðan.</p>
						</div>
					) }

					{ preview.create.length > 0 && (
						<>
							<h3>Nýjar færslur</h3>
							<ul>
								{ preview.create.map( ( c ) => (
									<li key={ c.line }>
										Lína { c.line }: <strong>{ htmlToText( c.fields.nafn ) }</strong> ({ c.fields.kynslod }. kynslóð)
										{ c.missing_id && ` · id ${ c.missing_id } fannst ekki og verður ný færsla` }
									</li>
								) ) }
							</ul>
						</>
					) }

					{ preview.update.length > 0 && (
						<>
							<h3>Breytingar</h3>
							<table className="widefat striped vs-oa-changes">
								<thead><tr><th>Lína</th><th>Reitur</th><th>Var</th><th>Verður</th></tr></thead>
								<tbody>
									{ preview.update.flatMap( ( u ) =>
										Object.entries( u.changes ).map( ( [ f, c ] ) => (
											<tr key={ `${ u.line }-${ f }` }>
												<td>{ u.line } (id { u.id })</td>
												<td>
													{ LABELS[ f ] }
													{ u.formatting_lost.includes( f ) && <span className="vs-oa-badge">snið tapast</span> }
												</td>
												<td>{ show( f, c.from ) }</td>
												<td>{ show( f, c.to ) }</td>
											</tr>
										) )
									) }
								</tbody>
							</table>
						</>
					) }

					<p className="vs-oa-actions">
						<button type="button" className="button button-primary" disabled={ busy || nothing || preview.errors.length > 0 } onClick={ apply }>
							Staðfesta innflutning
						</button>
						{ nothing && preview.errors.length === 0 && <span>Skráin inniheldur engar breytingar.</span> }
						<span className="description">Afrit af núverandi gögnum er vistað sjálfkrafa áður en innflutningur hefst.</span>
					</p>
				</div>
			) }

			{ result && (
				<div className="notice notice-success inline">
					<p>
						<strong>Innflutningi lokið:</strong> { result.created } nýjar, { result.updated } uppfærðar.
					</p>
					{ result.failed.length > 0 && <ul>{ result.failed.map( ( f ) => <li key={ f }>{ f }</li> ) }</ul> }
					<p>
						Afrit af gögnunum eins og þau voru fyrir innflutninginn:{ ' ' }
						<a href={ backupUrl( result.backup ) }>{ result.backup }</a>. Til að afturkalla, flyttu þá skrá inn.
					</p>
				</div>
			) }

			{ backups?.length > 0 && (
				<details className="vs-oa-backups">
					<summary>Eldri afrit ({ backups.length })</summary>
					<ul>
						{ backups.map( ( b ) => (
							<li key={ b }><a href={ backupUrl( b ) }>{ b }</a></li>
						) ) }
					</ul>
				</details>
			) }
		</Dialog>
	);
}

export function KynslodirDialog( { kynslodir, onClose, onSaved } ) {
	const [ rows, setRows ] = useState( () => kynslodir.map( ( k, i ) => ( { ...k, key: i } ) ) );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const set = ( key, field, value ) => setRows( ( rs ) => rs.map( ( r ) => ( r.key === key ? { ...r, [ field ]: value } : r ) ) );
	const add = () => {
		const next = Math.max( 0, ...rows.map( ( r ) => Number( r.kynslod ) || 0 ) ) + 1;
		setRows( ( rs ) => [ ...rs, { key: Date.now(), kynslod: next, akkeri: '', heiti_html: `${ next }. kynslóð`, skyring_html: '' } ] );
	};
	const save = async () => {
		setBusy( true );
		setError( '' );
		try {
			onSaved( await api( '/kynslodir', { method: 'POST', body: { kynslodir: rows } } ) );
			onClose();
		} catch ( e ) {
			setError( e.message );
			setBusy( false );
		}
	};

	return (
		<Dialog title="Kynslóðir" onClose={ onClose } wide>
			<p>Fyrirsögn og skýring hverrar kynslóðar á síðunni. Akkerið er notað í tenglum á borð við <code>#fyrsta-kynslod</code>.</p>
			{ error && <div className="notice notice-error inline"><p>{ error }</p></div> }
			<table className="widefat vs-oa-kyn">
				<thead><tr><th>Nr.</th><th>Akkeri</th><th>Fyrirsögn</th><th>Skýring</th><th /></tr></thead>
				<tbody>
					{ rows.map( ( r ) => (
						<tr key={ r.key }>
							<td><input className="vs-oa-input" type="number" min="1" value={ r.kynslod } aria-label="Númer kynslóðar" onChange={ ( e ) => set( r.key, 'kynslod', e.target.value ) } /></td>
							<td><input className="vs-oa-input" value={ r.akkeri } aria-label="Akkeri" onChange={ ( e ) => set( r.key, 'akkeri', e.target.value ) } /></td>
							<td><RichEditor html={ r.heiti_html } label="Fyrirsögn" onChange={ ( v ) => set( r.key, 'heiti_html', v ) } /></td>
							<td><RichEditor html={ r.skyring_html } label="Skýring" onChange={ ( v ) => set( r.key, 'skyring_html', v ) } /></td>
							<td>
								<button type="button" className="button-link is-destructive" onClick={ () => setRows( ( rs ) => rs.filter( ( x ) => x.key !== r.key ) ) }>
									Fjarlægja
								</button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<p className="vs-oa-actions">
				<button type="button" className="button" onClick={ add }>Bæta við kynslóð</button>
				<button type="button" className="button button-primary" disabled={ busy } onClick={ save }>
					{ busy ? 'Vista…' : 'Vista kynslóðir' }
				</button>
			</p>
		</Dialog>
	);
}
