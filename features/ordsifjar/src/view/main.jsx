import { createRoot } from 'react-dom/client';
import Ordsifjar from './Ordsifjar.jsx';
import './view.css';

// Takes over each server-rendered block. Until this runs, the plain HTML tables are shown.
for ( const block of document.querySelectorAll( '.vs-ordsifjar' ) ) {
	const json = block.querySelector( '.vs-ordsifjar__data' );
	const app = block.querySelector( '.vs-ordsifjar__app' );
	if ( ! json || ! app ) {
		continue;
	}
	let data;
	try {
		data = JSON.parse( json.textContent );
	} catch {
		continue; // Keep the server-rendered tables.
	}
	createRoot( app ).render( <Ordsifjar data={ data } /> );
}
