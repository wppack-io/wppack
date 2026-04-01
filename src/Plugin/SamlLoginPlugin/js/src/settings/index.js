import { createRoot } from '@wordpress/element';
import App from './App';
import './style-admin-ui.css';
import './style.css';

const container = document.getElementById( 'wppack-saml-login-settings' );

if ( container ) {
	const root = createRoot( container );
	root.render( <App /> );
}
