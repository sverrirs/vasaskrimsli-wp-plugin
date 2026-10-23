import { useRef, useState } from 'react';
import RichEditor, { prompting } from './RichEditor.jsx';

/**
 * A formatted cell: shows the stored HTML, becomes an editor on click.
 * Leaving the cell saves (only if something was actually changed); Escape cancels.
 */
export function RichCell( { html, label, onSave } ) {
	const [ editing, setEditing ] = useState( false );
	const draft = useRef( null );
	const wrap = useRef( null );

	const finish = ( save ) => {
		const value = draft.current;
		draft.current = null;
		setEditing( false );
		if ( save && value !== null && value !== html ) {
			onSave( value );
		}
	};

	if ( ! editing ) {
		return (
			<div
				className={ 'vs-oa-cell' + ( html ? '' : ' is-empty' ) }
				role="button"
				tabIndex={ 0 }
				aria-label={ `Breyta: ${ label }` }
				onClick={ ( e ) => {
					if ( ! e.target.closest( 'a' ) || e.ctrlKey || e.metaKey ) {
						e.preventDefault();
						setEditing( true );
					}
				} }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' || e.key === 'F2' ) {
						e.preventDefault();
						setEditing( true );
					}
				} }
				dangerouslySetInnerHTML={ { __html: html || '' } }
			/>
		);
	}
	return (
		<div
			ref={ wrap }
			className="vs-oa-cell is-editing"
			onBlur={ ( e ) => {
				if ( ! prompting && ! wrap.current?.contains( e.relatedTarget ) ) {
					finish( true );
				}
			} }
		>
			<RichEditor
				html={ html }
				label={ label }
				autoFocus
				onChange={ ( v ) => ( draft.current = v ) }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Escape' ) {
						e.preventDefault();
						finish( false );
					} else if ( e.key === 'Enter' && ( e.ctrlKey || e.metaKey ) ) {
						e.preventDefault();
						finish( true );
					}
				} }
			/>
		</div>
	);
}

/** A plain one-line value (Númer, Kynslóð). Saves on blur or Enter when changed. */
export function TextCell( { value, label, type = 'text', onSave } ) {
	const [ draft, setDraft ] = useState( String( value ?? '' ) );
	const [ prev, setPrev ] = useState( value );
	if ( prev !== value ) {
		setPrev( value );
		setDraft( String( value ?? '' ) );
	}
	const commit = () => {
		if ( draft.trim() !== String( value ?? '' ) ) {
			onSave( type === 'number' ? Number( draft ) : draft.trim() );
		}
	};
	return (
		<input
			className="vs-oa-input"
			type={ type }
			min={ type === 'number' ? 1 : undefined }
			aria-label={ label }
			value={ draft }
			onChange={ ( e ) => setDraft( e.target.value ) }
			onBlur={ commit }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' ) {
					e.currentTarget.blur();
				} else if ( e.key === 'Escape' ) {
					setDraft( String( value ?? '' ) );
				}
			} }
		/>
	);
}

/** Categories as removable chips plus an input with suggestions. New names create new categories. */
export function FlokkarCell( { value, all, label, onSave } ) {
	const [ text, setText ] = useState( '' );
	const listId = useRef( `vs-oa-flokkar-${ Math.random().toString( 36 ).slice( 2 ) }` ).current;
	const add = () => {
		const name = text.trim();
		setText( '' );
		if ( name && ! value.includes( name ) ) {
			onSave( [ ...value, name ] );
		}
	};
	return (
		<div className="vs-oa-flokkar">
			{ value.map( ( f ) => (
				<span className="vs-oa-chip" key={ f }>
					{ f }
					<button type="button" aria-label={ `Fjarlægja flokkinn ${ f }` } onClick={ () => onSave( value.filter( ( x ) => x !== f ) ) }>
						×
					</button>
				</span>
			) ) }
			<input
				className="vs-oa-input vs-oa-input--chip"
				list={ listId }
				aria-label={ `${ label }: bæta við flokki` }
				placeholder="+ flokkur"
				value={ text }
				onChange={ ( e ) => setText( e.target.value ) }
				onBlur={ add }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						add();
					}
				} }
			/>
			<datalist id={ listId }>
				{ all.filter( ( f ) => ! value.includes( f ) ).map( ( f ) => (
					<option key={ f } value={ f } />
				) ) }
			</datalist>
		</div>
	);
}
