import { createRoot } from 'react-dom/client';
import Pontun from './Pontun.jsx';
import './pontun.css';

for ( const block of document.querySelectorAll( '.vs-pontun' ) ) {
	const json = block.querySelector( '.vs-pontun__data' );
	const app = block.querySelector( '.vs-pontun__app' );
	if ( ! json || ! app ) {
		continue;
	}
	let data;
	try {
		data = JSON.parse( json.textContent );
	} catch {
		continue; // Keep the contact information from the server.
	}
	if ( data.flokkar.length ) {
		createRoot( app ).render( <Pontun data={ data } /> );
	}
}
