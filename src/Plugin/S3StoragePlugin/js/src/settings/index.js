import { createRoot } from '@wordpress/element';
import './style-admin-ui.css';
import './style.css';
import App from './App';

const container = document.getElementById( 'wppack-storage-settings' );

if ( container ) {
	const root = createRoot( container );
	root.render( <App /> );
}
