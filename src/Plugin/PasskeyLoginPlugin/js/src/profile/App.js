import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	TextControl,
	Notice,
	Spinner,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

function b64urlEncode( buffer ) {
	const bytes = new Uint8Array( buffer );
	let str = '';
	for ( let i = 0; i < bytes.length; i++ ) {
		str += String.fromCharCode( bytes[ i ] );
	}
	return btoa( str ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
}

function b64urlDecode( str ) {
	let s = str.replace( /-/g, '+' ).replace( /_/g, '/' );
	while ( s.length % 4 ) {
		s += '=';
	}
	const binary = atob( s );
	const bytes = new Uint8Array( binary.length );
	for ( let i = 0; i < binary.length; i++ ) {
		bytes[ i ] = binary.charCodeAt( i );
	}
	return bytes.buffer;
}

function formatDate( dateStr ) {
	if ( ! dateStr ) {
		return '—';
	}
	try {
		return new Date( dateStr ).toLocaleDateString( undefined, {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
		} );
	} catch {
		return dateStr;
	}
}

export default function App() {
	const [ credentials, setCredentials ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ notice, setNotice ] = useState( null );
	const [ registering, setRegistering ] = useState( false );
	const [ editingCred, setEditingCred ] = useState( null );
	const [ editName, setEditName ] = useState( '' );
	const [ deletingId, setDeletingId ] = useState( null );

	const fetchCredentials = useCallback( () => {
		setLoading( true );
		apiFetch( { path: '/wppack/v1/passkey/credentials' } )
			.then( ( data ) => {
				setCredentials( Array.isArray( data ) ? data : [] );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to load passkeys.', 'wppack-passkey-login' ) } );
				setLoading( false );
			} );
	}, [] );

	useEffect( () => {
		fetchCredentials();
	}, [ fetchCredentials ] );

	const handleRegister = async () => {
		setRegistering( true );
		setNotice( null );
		try {
			const options = await apiFetch( {
				path: '/wppack/v1/passkey/register/options',
				method: 'POST',
			} );

			const publicKey = {
				...options,
				challenge: b64urlDecode( options.challenge ),
				user: {
					...options.user,
					id: b64urlDecode( options.user.id ),
				},
			};
			if ( options.excludeCredentials ) {
				publicKey.excludeCredentials = options.excludeCredentials.map( ( c ) => ( {
					...c,
					id: b64urlDecode( c.id ),
				} ) );
			}

			const credential = await navigator.credentials.create( { publicKey } );

			const attestation = {
				id: credential.id,
				rawId: b64urlEncode( credential.rawId ),
				type: credential.type,
				challengeKey: options.challengeKey,
				response: {
					attestationObject: b64urlEncode( credential.response.attestationObject ),
					clientDataJSON: b64urlEncode( credential.response.clientDataJSON ),
				},
			};

			if ( credential.response.getTransports ) {
				attestation.response.transports = credential.response.getTransports();
			}

			await apiFetch( {
				path: '/wppack/v1/passkey/register/verify',
				method: 'POST',
				data: attestation,
			} );

			setNotice( { type: 'success', message: __( 'Passkey registered successfully.', 'wppack-passkey-login' ) } );
			fetchCredentials();
		} catch ( err ) {
			const msg = err.name === 'NotAllowedError'
				? __( 'Registration was cancelled.', 'wppack-passkey-login' )
				: __( 'Failed to register passkey.', 'wppack-passkey-login' );
			setNotice( { type: 'error', message: msg } );
		} finally {
			setRegistering( false );
		}
	};

	const handleRename = () => {
		if ( ! editingCred ) {
			return;
		}
		apiFetch( {
			path: `/wppack/v1/passkey/credentials/${ editingCred.id }`,
			method: 'PUT',
			data: { deviceName: editName },
		} )
			.then( () => {
				setEditingCred( null );
				setEditName( '' );
				fetchCredentials();
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to rename passkey.', 'wppack-passkey-login' ) } );
			} );
	};

	const handleDelete = ( id ) => {
		setDeletingId( null );
		apiFetch( {
			path: `/wppack/v1/passkey/credentials/${ id }`,
			method: 'DELETE',
		} )
			.then( () => {
				setNotice( { type: 'success', message: __( 'Passkey deleted.', 'wppack-passkey-login' ) } );
				fetchCredentials();
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to delete passkey.', 'wppack-passkey-login' ) } );
			} );
	};

	if ( loading ) {
		return <div className="wpp-passkey-profile-loading"><Spinner /></div>;
	}

	return (
		<div className="wpp-passkey-profile">
			{ notice && (
				<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) }>
					{ notice.message }
				</Notice>
			) }

			{ credentials.length > 0 && (
				<table className="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th className="manage-column column-name column-primary">{ __( 'Name', 'wppack-passkey-login' ) }</th>
							<th className="manage-column column-type">{ __( 'Type', 'wppack-passkey-login' ) }</th>
							<th className="manage-column column-created">{ __( 'Registered', 'wppack-passkey-login' ) }</th>
							<th className="manage-column column-last_used">{ __( 'Last Used', 'wppack-passkey-login' ) }</th>
							<th className="manage-column column-revoke">{ __( 'Actions', 'wppack-passkey-login' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ credentials.map( ( cred ) => (
							<tr key={ cred.id }>
								<td className="name column-name has-row-actions column-primary" data-colname={ __( 'Name', 'wppack-passkey-login' ) }>
									{ cred.deviceName || __( 'Unnamed', 'wppack-passkey-login' ) }
									<div className="row-actions">
										<span className="edit">
											{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid */ }
											<a
												href="#"
												onClick={ ( e ) => {
													e.preventDefault();
													setEditingCred( cred );
													setEditName( cred.deviceName || '' );
												} }
											>
												{ __( 'Edit', 'wppack-passkey-login' ) }
											</a>
										</span>
									</div>
									<button
										type="button"
										className="toggle-row"
										onClick={ ( e ) => {
											e.currentTarget.closest( 'tr' ).classList.toggle( 'is-expanded' );
										} }
									>
										<span className="screen-reader-text">{ __( 'Show more details', 'wppack-passkey-login' ) }</span>
									</button>
								</td>
								<td className="type column-type" data-colname={ __( 'Type', 'wppack-passkey-login' ) }>
									{ cred.backupEligible ? __( 'Synced', 'wppack-passkey-login' ) : __( 'Device-bound', 'wppack-passkey-login' ) }
								</td>
								<td className="created column-created" data-colname={ __( 'Registered', 'wppack-passkey-login' ) }>
									{ formatDate( cred.createdAt ) }
								</td>
								<td className="last_used column-last_used" data-colname={ __( 'Last Used', 'wppack-passkey-login' ) }>
									{ formatDate( cred.lastUsedAt ) }
								</td>
								<td className="revoke column-revoke" data-colname={ __( 'Actions', 'wppack-passkey-login' ) }>
									<button
										type="button"
										className="button delete"
										aria-label={ `${ __( 'Revoke', 'wppack-passkey-login' ) } "${ cred.deviceName || __( 'Unnamed', 'wppack-passkey-login' ) }"` }
										onClick={ () => setDeletingId( cred.id ) }
									>
										{ __( 'Revoke', 'wppack-passkey-login' ) }
									</button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ credentials.length === 0 && (
				<p className="wpp-passkey-empty">
					{ __( 'No passkeys registered yet. Add one to enable passwordless login.', 'wppack-passkey-login' ) }
				</p>
			) }

			<div className="wpp-passkey-profile-actions">
				<button
					type="button"
					className="button button-secondary"
					onClick={ handleRegister }
					disabled={ registering || credentials.length >= ( window.wppPasskeyProfile?.maxCredentials || 3 ) }
				>
					{ registering ? __( 'Registering…', 'wppack-passkey-login' ) : __( 'Add Passkey', 'wppack-passkey-login' ) }
				</button>
			</div>

			{ editingCred && (
				<Modal
					title={ __( 'Edit Passkey Name', 'wppack-passkey-login' ) }
					onRequestClose={ () => setEditingCred( null ) }
					size="small"
				>
					<TextControl
						label={ __( 'Name', 'wppack-passkey-login' ) }
						value={ editName }
						onChange={ setEditName }
						__nextHasNoMarginBottom
					/>
					<div className="wpp-passkey-modal-actions">
						<Button
							variant="primary"
							onClick={ handleRename }
						>
							{ __( 'Save', 'wppack-passkey-login' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setEditingCred( null ) }
						>
							{ __( 'Cancel', 'wppack-passkey-login' ) }
						</Button>
					</div>
				</Modal>
			) }

			{ deletingId && (
				<Modal
					title={ __( 'Delete Passkey', 'wppack-passkey-login' ) }
					onRequestClose={ () => setDeletingId( null ) }
					size="small"
				>
					<p>{ __( 'Are you sure you want to delete this passkey? This action cannot be undone.', 'wppack-passkey-login' ) }</p>
					<div className="wpp-passkey-modal-actions">
						<Button
							variant="primary"
							isDestructive
							onClick={ () => handleDelete( deletingId ) }
						>
							{ __( 'Delete', 'wppack-passkey-login' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setDeletingId( null ) }
						>
							{ __( 'Cancel', 'wppack-passkey-login' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
