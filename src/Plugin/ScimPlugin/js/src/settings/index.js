import { createRoot } from '@wordpress/element';
import App from './App';

const container = document.getElementById( 'wppack-scim-settings' );

if ( container ) {
	const root = createRoot( container );
	root.render( <App /> );
}
