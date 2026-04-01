import { useState, useEffect } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	TextControl,
	ToggleControl,
	SelectControl,
	Button,
	Notice,
	Spinner,
	BaseControl,
} from '@wordpress/components';
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const MASKED = '********';

function SourceBadge( { source } ) {
	if ( ! source || source === 'default' ) {
		return null;
	}
	const labels = {
		constant: __( 'Constant', 'wppack-scim' ),
		option: __( 'Saved', 'wppack-scim' ),
	};
	return (
		<span className={ `wpp-scim-source-badge wpp-scim-source-${ source }` }>
			{ labels[ source ] || source }
		</span>
	);
}

export default function App() {
	const [ settings, setSettings ] = useState( null );
	const [ form, setForm ] = useState( {} );
	const [ baseUrl, setBaseUrl ] = useState( '' );
	const [ roles, setRoles ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		setSettings( data.settings );
		setBaseUrl( data.baseUrl || '' );
		setRoles( data.roles || {} );

		const f = {};
		Object.entries( data.settings || {} ).forEach( ( [ k, m ] ) => {
			f[ k ] = m.value;
		} );
		setForm( f );
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/scim/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Failed to load settings.', 'wppack-scim' ),
				} );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );

		apiFetch( {
			path: '/wppack/v1/scim/settings',
			method: 'POST',
			data: form,
		} )
			.then( ( data ) => {
				applyResponse( data );
				setNotice( {
					type: 'success',
					message: __( 'Settings saved.', 'wppack-scim' ),
				} );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Failed to save settings.', 'wppack-scim' ),
				} );
			} )
			.finally( () => setSaving( false ) );
	};

	const update = ( key ) => ( value ) => {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	const s = ( key ) => settings?.[ key ] || {};

	if ( loading ) {
		return (
			<div className="wpp-scim-loading">
				<Spinner />
			</div>
		);
	}

	const roleOptions = Object.entries( roles ).map( ( [ value, label ] ) => ( {
		label,
		value,
	} ) );

	return (
		<Page title={ __( 'SCIM Settings', 'wppack-scim' ) } hasPadding>
			<div className="wpp-scim-settings">
				{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<Panel>
				<PanelBody
					title={ __( 'Authentication', 'wppack-scim' ) }
					initialOpen={ true }
				>
					<TextControl
						id="bearerToken"
						label={
							<>
								{ __( 'Bearer Token', 'wppack-scim' ) }
								<SourceBadge source={ s( 'bearerToken' ).source } />
							</>
						}
						value={ form.bearerToken || '' }
						onChange={ update( 'bearerToken' ) }
						disabled={ s( 'bearerToken' ).readonly }
						help={ __( 'Token for SCIM API authentication. Leave as masked to keep current value.', 'wppack-scim' ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Provisioning', 'wppack-scim' ) }
					initialOpen={ true }
				>
					<ToggleControl
						label={
							<>
								{ __( 'Auto Provision', 'wppack-scim' ) }
								<SourceBadge source={ s( 'autoProvision' ).source } />
							</>
						}
						help={ __( 'Automatically create WordPress users from SCIM requests.', 'wppack-scim' ) }
						checked={ !! form.autoProvision }
						onChange={ update( 'autoProvision' ) }
						disabled={ s( 'autoProvision' ).readonly }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={
							<>
								{ __( 'Default Role', 'wppack-scim' ) }
								<SourceBadge source={ s( 'defaultRole' ).source } />
							</>
						}
						value={ form.defaultRole || 'subscriber' }
						onChange={ update( 'defaultRole' ) }
						disabled={ s( 'defaultRole' ).readonly }
						options={ roleOptions }
						className="wpp-scim-small-select"
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={
							<>
								{ __( 'Allow Group Management', 'wppack-scim' ) }
								<SourceBadge source={ s( 'allowGroupManagement' ).source } />
							</>
						}
						help={ __( 'Allow SCIM to manage WordPress roles as groups.', 'wppack-scim' ) }
						checked={ !! form.allowGroupManagement }
						onChange={ update( 'allowGroupManagement' ) }
						disabled={ s( 'allowGroupManagement' ).readonly }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={
							<>
								{ __( 'Allow User Deletion', 'wppack-scim' ) }
								<SourceBadge source={ s( 'allowUserDeletion' ).source } />
							</>
						}
						help={ __( 'Allow SCIM DELETE requests to remove WordPress users.', 'wppack-scim' ) }
						checked={ !! form.allowUserDeletion }
						onChange={ update( 'allowUserDeletion' ) }
						disabled={ s( 'allowUserDeletion' ).readonly }
						__nextHasNoMarginBottom
					/>
					<TextControl
						id="maxResults"
						label={
							<>
								{ __( 'Max Results', 'wppack-scim' ) }
								<SourceBadge source={ s( 'maxResults' ).source } />
							</>
						}
						type="number"
						min={ 1 }
						max={ 1000 }
						value={ String( form.maxResults || 100 ) }
						onChange={ ( val ) => update( 'maxResults' )( parseInt( val, 10 ) || 100 ) }
						disabled={ s( 'maxResults' ).readonly }
						className="wpp-scim-small-input"
						help={ __( 'Maximum number of resources returned per list request (1-1000).', 'wppack-scim' ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'SCIM Endpoints', 'wppack-scim' ) }
					initialOpen={ false }
				>
					<TextControl
						label="Users"
						value={ `${ baseUrl }/scim/v2/Users` }
						readOnly
						__nextHasNoMarginBottom
					/>
					<TextControl
						label="Groups"
						value={ `${ baseUrl }/scim/v2/Groups` }
						readOnly
						__nextHasNoMarginBottom
					/>
					<TextControl
						label="ServiceProviderConfig"
						value={ `${ baseUrl }/scim/v2/ServiceProviderConfig` }
						readOnly
						__nextHasNoMarginBottom
					/>
					<TextControl
						label="Schemas"
						value={ `${ baseUrl }/scim/v2/Schemas` }
						readOnly
						__nextHasNoMarginBottom
					/>
					<TextControl
						label="ResourceTypes"
						value={ `${ baseUrl }/scim/v2/ResourceTypes` }
						readOnly
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</Panel>

			<div className="wpp-scim-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'wppack-scim' )
						: __( 'Save Settings', 'wppack-scim' ) }
				</Button>
			</div>
			</div>
		</Page>
	);
}
