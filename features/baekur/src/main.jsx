import { createRoot } from 'react-dom/client';
import Baekur from './Baekur.jsx';
import './baekur.css';

// Takes over each server-rendered catalogue block. Until this runs, the plain HTML list is shown.
for ( const block of document.querySelectorAll( '.vs-baekur' ) ) {
	const json = block.querySelector( '.vs-baekur__data' );
	const app = block.querySelector( '.vs-baekur__app' );
	if ( ! json || ! app ) {
		continue;
	}
	let data;
	try {
		data = JSON.parse( json.textContent );
	} catch {
		continue; // Keep the server-rendered list.
	}
	createRoot( app ).render( <Baekur data={ data } /> );
}
