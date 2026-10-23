import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './admin.css';

const root = document.getElementById( 'vs-ordsifjar-admin' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
