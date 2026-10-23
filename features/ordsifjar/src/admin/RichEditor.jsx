import { useRef } from 'react';
import { Extension, Node } from '@tiptap/core';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';

/*
 * Inline rich-text editor matching the formatting the site allows:
 * bold, italics, links, line breaks, superscript and subscript (see Sanitize.php).
 * The document holds inline content only, so getHTML() returns e.g. "<strong>a</strong><br>b"
 * without wrapping <p> tags, which is how the cells are stored.
 */

const InlineDocument = Node.create( { name: 'doc', topNode: true, content: 'inline*' } );

const EnterIsLineBreak = Extension.create( {
	name: 'enterIsLineBreak',
	addKeyboardShortcuts() {
		return { Enter: () => this.editor.commands.setHardBreak() };
	},
} );

const extensions = [
	InlineDocument,
	StarterKit.configure( {
		document: false,
		paragraph: false,
		heading: false,
		blockquote: false,
		bulletList: false,
		orderedList: false,
		listItem: false,
		listKeymap: false,
		code: false,
		codeBlock: false,
		horizontalRule: false,
		strike: false,
		underline: false,
		dropcursor: false,
		gapcursor: false,
		trailingNode: false,
		link: {
			openOnClick: false,
			autolink: false,
			linkOnPaste: true,
			// Keep links exactly as stored: no target/rel/class added unless the link already has them.
			HTMLAttributes: { target: null, rel: null, class: null },
		},
	} ),
	Superscript,
	Subscript,
	EnterIsLineBreak,
];

function Tool( { label, title, active, onClick } ) {
	return (
		<button
			type="button"
			className="vs-oa-tool"
			title={ title }
			aria-label={ title }
			aria-pressed={ active }
			onMouseDown={ ( e ) => e.preventDefault() /* keep focus in the editor */ }
			onClick={ onClick }
		>
			{ label }
		</button>
	);
}

/** True while the link prompt is open, so the cell does not treat the lost focus as "done". */
export let prompting = false;

function editLink( editor ) {
	const current = editor.getAttributes( 'link' ).href ?? '';
	prompting = true;
	let href;
	try {
		href = window.prompt( 'Slóð tengils (tómt fjarlægir tengilinn):', current || 'https://' );
	} finally {
		prompting = false;
	}
	if ( href === null ) {
		return;
	}
	const chain = editor.chain().focus().extendMarkRange( 'link' );
	if ( href.trim() === '' || href.trim() === 'https://' ) {
		chain.unsetLink().run();
	} else {
		chain.setLink( { href: href.trim() } ).run();
	}
}

/**
 * @param {string}   html      Initial HTML.
 * @param {Function} onChange  Called with the new HTML on every user edit.
 * @param {Function} onKeyDown Extra key handling (Escape, Ctrl+Enter) by the caller.
 */
export default function RichEditor( { html, onChange, onKeyDown, autoFocus = false, label } ) {
	const changed = useRef( onChange );
	changed.current = onChange;

	const editor = useEditor( {
		extensions,
		content: html,
		autofocus: autoFocus ? 'end' : false,
		editorProps: { attributes: { class: 'vs-oa-editor', 'aria-label': label ?? '', role: 'textbox', 'aria-multiline': 'true' } },
		// Only real user edits count; loading the content must not mark the cell as changed.
		onUpdate: ( { editor: ed, transaction } ) => {
			if ( transaction.docChanged ) {
				changed.current( ed.getHTML() );
			}
		},
	} );

	const state = useEditorState( {
		editor,
		selector: ( { editor: ed } ) =>
			ed
				? {
						bold: ed.isActive( 'bold' ),
						italic: ed.isActive( 'italic' ),
						link: ed.isActive( 'link' ),
						sup: ed.isActive( 'superscript' ),
						sub: ed.isActive( 'subscript' ),
				  }
				: {},
	} );


	if ( ! editor ) {
		return null;
	}
	return (
		<div className="vs-oa-rich" onKeyDown={ onKeyDown }>
			<div className="vs-oa-toolbar" role="toolbar" aria-label="Snið">
				<Tool label={ <strong>F</strong> } title="Feitletrað (Ctrl+B)" active={ state.bold } onClick={ () => editor.chain().focus().toggleBold().run() } />
				<Tool label={ <em>S</em> } title="Skáletrað (Ctrl+I)" active={ state.italic } onClick={ () => editor.chain().focus().toggleItalic().run() } />
				<Tool label="Tengill" title="Tengill" active={ state.link } onClick={ () => editLink( editor ) } />
				<Tool label={ <>x<sup>2</sup></> } title="Hávísir" active={ state.sup } onClick={ () => editor.chain().focus().toggleSuperscript().run() } />
				<Tool label={ <>x<sub>2</sub></> } title="Lágvísir" active={ state.sub } onClick={ () => editor.chain().focus().toggleSubscript().run() } />
				<Tool label="Hreinsa" title="Fjarlægja snið af völdum texta" onClick={ () => editor.chain().focus().unsetAllMarks().run() } />
			</div>
			<EditorContent editor={ editor } />
		</div>
	);
}
