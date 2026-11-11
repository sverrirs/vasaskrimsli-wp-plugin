import { useEffect, useId, useRef, useState } from 'react';

const INNTAK = {
	nafn: { autoComplete: 'section-pantandi name' },
	netfang: { type: 'email', autoComplete: 'section-pantandi email' },
	simi: { type: 'tel', autoComplete: 'section-pantandi tel' },
	vidtakandi: { autoComplete: 'section-sending shipping organization' },
	heimilisfang: { autoComplete: 'section-sending shipping address-line1' },
	inngangur: { autoComplete: 'section-sending shipping address-line2' },
	stadur: { autoComplete: 'section-sending shipping address-level2' },
	postnumer: { autoComplete: 'section-sending shipping postal-code', inputMode: 'numeric' },
	greidandi: { autoComplete: 'section-reikningur billing organization' },
	kennitala: { inputMode: 'numeric' },
	netfang_greid: { type: 'email', autoComplete: 'section-reikningur billing email' },
};

function Villa( { id, texti } ) {
	return texti && <p className="vs-pontun__villa" id={ id }>{ texti }</p>;
}

function Reitur( { id, lykill, heiti, skilyrdi, villa } ) {
	const langt = lykill === 'aths' || lykill === 'aths_reikningur';
	const props = {
		id,
		name: lykill,
		required: skilyrdi,
		maxLength: langt ? 2000 : 200,
		'aria-invalid': villa ? true : undefined,
		'aria-describedby': villa ? id + '-villa' : undefined,
	};

	return (
		<div className="vs-pontun__reitur">
			<label htmlFor={ id }>
				{ heiti }{ skilyrdi && <span aria-hidden="true"> *</span> }
			</label>
			{ langt ? <textarea { ...props } rows={ 3 } /> : <input { ...props } { ...INNTAK[ lykill ] } /> }
			<Villa id={ id + '-villa' } texti={ villa } />
		</div>
	);
}

function Hluti( { id, titill, children, ...props } ) {
	return (
		<section className="vs-pontun__hluti" aria-labelledby={ id + '-heiti' } id={ id } { ...props }>
			<h2 id={ id + '-heiti' }>{ titill }</h2>
			{ children }
		</section>
	);
}

function Vorulina( { id, flokkur, lina, index, max, uppfaera, fjarlaegja } ) {
	const samhengi = flokkur.heiti + ', lína ' + ( index + 1 );
	const className = flokkur.val.length > 1 ? 'vs-pontun__lina vs-pontun__lina--fleirval' : 'vs-pontun__lina';
	const fjoldi = Number( lina.fjoldi );
	const skref = ( breyting ) => {
		uppfaera( { fjoldi: String( Math.max( 0, Math.min( max, Math.trunc( fjoldi ) + breyting ) ) ) } );
	};

	return (
		<div className={ className } role="group" aria-label={ samhengi }>
			<div className="vs-pontun__val">
				{ flokkur.val.map( ( v ) => (
					<div className="vs-pontun__reitur" key={ v.id }>
						<label htmlFor={ id + '-' + v.id }>{ v.heiti }</label>
						<select
							id={ id + '-' + v.id }
							value={ lina.val[ v.id ] }
							onChange={ ( e ) => uppfaera( { val: { ...lina.val, [ v.id ]: e.target.value } } ) }
						>
							{ v.kostir.map( ( kostur ) => <option key={ kostur } value={ kostur }>{ kostur }</option> ) }
						</select>
					</div>
				) ) }
			</div>
			<div className="vs-pontun__reitur">
				<label htmlFor={ id + '-fjoldi' }>Fjöldi</label>
				<div className="vs-pontun__skref">
					<button type="button" aria-label={ 'Fækka um einn: ' + samhengi } disabled={ fjoldi <= 0 } onClick={ () => skref( -1 ) }>−</button>
					<input
						id={ id + '-fjoldi' }
						type="number"
						inputMode="numeric"
						min={ 0 }
						max={ max }
						step={ 1 }
						value={ lina.fjoldi }
						onChange={ ( e ) => uppfaera( { fjoldi: e.target.value } ) }
					/>
					<button type="button" aria-label={ 'Fjölga um einn: ' + samhengi } disabled={ fjoldi >= max } onClick={ () => skref( 1 ) }>+</button>
				</div>
			</div>
			{ fjarlaegja && (
				<button className="vs-pontun__textahnappur" type="button" aria-label={ 'Fjarlægja: ' + samhengi } onClick={ fjarlaegja }>
					Fjarlægja línu
				</button>
			) }
		</div>
	);
}

function nyLina( flokkur, id ) {
	return {
		id,
		flokkur: flokkur.id,
		val: Object.fromEntries( flokkur.val.map( ( v ) => [ v.id, v.kostir[ 0 ] ] ) ),
		fjoldi: '0',
	};
}

export default function Pontun( { data } ) {
	const id = useId();
	const fieldId = ( reitur ) => id + '-reitur-' + reitur;
	const rowId = ( n ) => id + '-lina-' + n;
	const addId = ( flokkur ) => id + '-baeta-' + flokkur;
	const [ linur, setLinur ] = useState( () => data.flokkar.map( ( f, n ) => nyLina( f, n ) ) );
	const [ busy, setBusy ] = useState( false );
	const [ svar, setSvar ] = useState( null );
	const iVinnslu = useRef( false );
	const naestaLina = useRef( data.flokkar.length );
	const vorufokus = useRef( null );
	const samantekt = useRef( null );
	const villur = svar?.villur ?? {};
	const numer = svar?.numer;

	useEffect( () => {
		if ( vorufokus.current ) {
			document.getElementById( vorufokus.current )?.focus();
			vorufokus.current = null;
		}
	}, [ linur ] );

	useEffect( () => {
		if ( svar ) {
			samantekt.current?.focus();
		}
	}, [ svar ] );

	function baetaVid( flokkur ) {
		const n = naestaLina.current++;
		vorufokus.current = rowId( n ) + '-' + flokkur.val[ 0 ].id;
		setLinur( ( fyrri ) => [ ...fyrri, nyLina( flokkur, n ) ] );
	}

	function fjarlaegja( lina ) {
		vorufokus.current = addId( lina.flokkur );
		setLinur( ( fyrri ) => fyrri.filter( ( l ) => l.id !== lina.id ) );
	}

	function reitur( key ) {
		const [ heiti, skilyrdi ] = data.reitir[ key ];
		const reitId = fieldId( key );
		return <Reitur key={ key } id={ reitId } lykill={ key } heiti={ heiti } skilyrdi={ skilyrdi } villa={ villur[ key ] } />;
	}

	async function senda( event ) {
		event.preventDefault();
		if ( iVinnslu.current ) {
			return;
		}
		const form = new FormData( event.currentTarget );
		const reitir = Object.fromEntries( Object.keys( data.reitir ).map( ( key ) => [ key, form.get( key ) ?? '' ] ) );
		const dropp = form.has( 'dropp' );
		const payload = linur.map( ( { flokkur, val, fjoldi } ) => ( { flokkur, val, fjoldi: Number( fjoldi ) } ) );
		const vantar = {};
		for ( const [ key, [ heiti, skilyrdi ] ] of Object.entries( data.reitir ) ) {
			if ( skilyrdi && ! reitir[ key ].trim() ) {
				vantar[ key ] = 'Fylltu út: ' + heiti + '.';
			}
		}
		if ( payload.some( ( { fjoldi } ) => ! Number.isInteger( fjoldi ) || fjoldi < 0 || fjoldi > data.maxFjoldi ) ) {
			vantar.linur = 'Sláðu inn heilan fjölda frá 0 til ' + data.maxFjoldi + '.';
		} else if ( ! payload.some( ( { fjoldi } ) => fjoldi > 0 ) ) {
			vantar.linur = 'Veldu að minnsta kosti eina vöru og fjölda.';
		}
		if ( ! dropp ) {
			vantar.dropp = 'Hakaðu við að vörurnar séu sendar með Dropp.';
		}
		if ( Object.keys( vantar ).length ) {
			setSvar( { villur: vantar } );
			return;
		}

		iVinnslu.current = true;
		setBusy( true );
		try {
			const response = await fetch( data.slod, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { reitir, linur: payload, dropp, vefsida: form.get( 'vefsida' ), t: data.t } ),
			} );
			const body = await response.json();
			if ( response.ok && Number.isSafeInteger( body.numer ) && body.numer > 0 ) {
				setSvar( { numer: body.numer } );
			} else if ( body.villur && Object.keys( body.villur ).length ) {
				setSvar( { villur: body.villur } );
			} else {
				throw new Error( 'Unexpected order response' );
			}
		} catch {
			setSvar( { villur: { almennt: 'Ekki tókst að staðfesta móttöku pöntunarinnar. Hafðu samband ef þú ert í vafa um hvort hún hafi borist.' } } );
		} finally {
			iVinnslu.current = false;
			setBusy( false );
		}
	}

	if ( numer ) {
		return (
			<section className="vs-pontun__mottekin" ref={ samantekt } tabIndex={ -1 } aria-labelledby={ id + '-mottekin' } aria-describedby={ id + '-numer' }>
				<h2 id={ id + '-mottekin' }>Takk fyrir pöntunina</h2>
				<p id={ id + '-numer' }>Pöntunarnúmerið þitt er <strong>{ numer }</strong>.</p>
			</section>
		);
	}

	const villurod = [ 'almennt', 'nafn', 'netfang', 'simi', 'linur', 'vidtakandi', 'heimilisfang', 'inngangur', 'stadur', 'postnumer',
		'dropp', 'greidandi', 'kennitala', 'netfang_greid', 'aths_reikningur', 'aths' ];
	const skilabod = villurod.filter( ( key ) => villur[ key ] );

	return (
		<form className="vs-pontun__form" method="post" noValidate onSubmit={ senda }>
			{ skilabod.length > 0 && (
				<section className="vs-pontun__villur" ref={ samantekt } tabIndex={ -1 } aria-labelledby={ id + '-villur' }>
					<h2 id={ id + '-villur' }>Pöntunin var ekki send</h2>
					<ul>
						{ skilabod.map( ( key ) => (
							<li key={ key }>
								{ key === 'almennt' ? villur[ key ] : <a href={ '#' + fieldId( key ) }>{ villur[ key ] }</a> }
							</li>
						) ) }
					</ul>
				</section>
			) }
			<fieldset className="vs-pontun__reitir" disabled={ busy }>
				<Hluti id={ id + '-pantandi' } titill="Hver pantar?">
					<p className="vs-pontun__hjalp">Reitir merktir með * eru skyldureitir.</p>
					{ reitur( 'nafn' ) }
					<div className="vs-pontun__grid">{ [ 'netfang', 'simi' ].map( reitur ) }</div>
				</Hluti>
				<Hluti id={ fieldId( 'linur' ) } titill="Vörur" tabIndex={ -1 } aria-describedby={ villur.linur ? id + '-linuvilla' : undefined }>
					<Villa id={ id + '-linuvilla' } texti={ villur.linur } />
					{ data.flokkar.map( ( flokkur ) => {
						const vorur = linur.filter( ( lina ) => lina.flokkur === flokkur.id );
						return (
							<div className="vs-pontun__flokkur" key={ flokkur.id }>
								<h3>{ flokkur.heiti }</h3>
								{ flokkur.skyring && <p className="vs-pontun__hjalp">{ flokkur.skyring }</p> }
								{ vorur.map( ( lina, index ) => (
									<Vorulina
										key={ lina.id }
										id={ rowId( lina.id ) }
										flokkur={ flokkur }
										lina={ lina }
										index={ index }
										max={ data.maxFjoldi }
										uppfaera={ ( breyting ) => setLinur( ( fyrri ) => fyrri.map( ( l ) => l.id === lina.id ? { ...l, ...breyting } : l ) ) }
										fjarlaegja={ vorur.length > 1 ? () => fjarlaegja( lina ) : undefined }
									/>
								) ) }
								<button
									id={ addId( flokkur.id ) }
									className="vs-pontun__baeta"
									type="button"
									aria-label={ 'Önnur lína: ' + flokkur.heiti }
									disabled={ linur.length >= data.maxLinur }
									onClick={ () => baetaVid( flokkur ) }
								>
									+ Önnur lína
								</button>
							</div>
						);
					} ) }
				</Hluti>
				<Hluti id={ id + '-vidtakandi' } titill="Sendist til">
					{ [ 'vidtakandi', 'heimilisfang', 'inngangur' ].map( reitur ) }
					<div className="vs-pontun__grid">{ [ 'stadur', 'postnumer' ].map( reitur ) }</div>
				</Hluti>
				<Hluti id={ id + '-sending' } titill={ data.sending.heiti }>
					<p id={ id + '-sendingarlysing' }>{ data.sending.skyring }</p>
					<label className="vs-pontun__hak" htmlFor={ fieldId( 'dropp' ) }>
						<input
							id={ fieldId( 'dropp' ) }
							name="dropp"
							type="checkbox"
							required
							aria-invalid={ villur.dropp ? true : undefined }
							aria-describedby={ id + '-sendingarlysing' + ( villur.dropp ? ' ' + id + '-droppvilla' : '' ) }
						/>
						<span>{ data.sending.samthykki } <span aria-hidden="true">*</span></span>
					</label>
					<Villa id={ id + '-droppvilla' } texti={ villur.dropp } />
				</Hluti>
				<Hluti id={ id + '-reikningur' } titill="Reikningsupplýsingar">
					{ reitur( 'greidandi' ) }
					<div className="vs-pontun__grid">{ [ 'kennitala', 'netfang_greid' ].map( reitur ) }</div>
					{ reitur( 'aths_reikningur' ) }
				</Hluti>
				<Hluti id={ id + '-athugasemdir' } titill="Athugasemdir">
					{ reitur( 'aths' ) }
				</Hluti>
				<div hidden>
					<label htmlFor={ fieldId( 'vefsida' ) }>Vefsíða</label>
					<input id={ fieldId( 'vefsida' ) } name="vefsida" autoComplete="off" tabIndex={ -1 } />
				</div>
				<button className="vs-pontun__senda wp-element-button" type="submit">
					{ busy ? 'Sendi pöntun…' : 'Senda pöntun' }
				</button>
			</fieldset>
			<span className="screen-reader-text" role="status">{ busy ? 'Verið er að senda pöntunina.' : '' }</span>
		</form>
	);
}
