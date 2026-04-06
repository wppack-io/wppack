import { createRoot } from '@wordpress/element';
import App from './App';
import './style.css';

const wrapper = document.getElementById( 'wppack-passkey-profile-wrapper' );

if ( wrapper ) {
	// Move section after the profile form, within #wpbody-content
	const form = document.getElementById( 'your-profile' );
	if ( form && form.parentNode ) {
		form.parentNode.insertBefore( wrapper, form.nextSibling );
	}
	wrapper.style.display = '';

	const container = document.getElementById( 'wppack-passkey-profile' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <App /> );
	}
}
