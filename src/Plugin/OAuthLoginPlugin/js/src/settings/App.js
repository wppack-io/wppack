import { useState, useEffect } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	Button,
	Notice,
	Spinner,
	BaseControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import './style.css';

const MASKED = '********';

function SourceBadge( { source } ) {
	if ( ! source || source === 'default' ) {
		return null;
	}
	const labels = {
		constant: __( 'Constant', 'wppack-oauth-login' ),
		option: __( 'Saved', 'wppack-oauth-login' ),
	};
	return (
		<span className={ `wpp-oauth-source-badge wpp-oauth-source-${ source }` }>
			{ labels[ source ] || source }
		</span>
	);
}

function Field( { id, label, field, value, onChange, help, disabled } ) {
	const isReadonly = disabled || field?.readonly;
	return (
		<BaseControl
			id={ id }
			label={
				<>
					{ label }
					<SourceBadge source={ field?.source } />
				</>
			}
			help={ help }
		>
			<TextControl
				id={ id }
				value={ value || '' }
				onChange={ onChange }
				disabled={ isReadonly }
				__nextHasNoMarginBottom
			/>
		</BaseControl>
	);
}

function BoolField( { label, field, value, onChange, help, disabled } ) {
	const isReadonly = disabled || field?.readonly;
	return (
		<BaseControl help={ help }>
			<ToggleControl
				label={
					<>
						{ label }
						<SourceBadge source={ field?.source } />
					</>
				}
				checked={ !! value }
				onChange={ onChange }
				disabled={ isReadonly }
				__nextHasNoMarginBottom
			/>
		</BaseControl>
	);
}

function PathField( { id, label, field, value, onChange, prefix, disabled } ) {
	const isReadonly = disabled || field?.readonly;
	return (
		<BaseControl
			id={ id }
			label={
				<>
					{ label }
					<SourceBadge source={ field?.source } />
				</>
			}
		>
			<div className="wpp-oauth-path-field">
				<span className="wpp-oauth-path-prefix">{ prefix }</span>
				<input
					type="text"
					id={ id }
					className="components-text-control__input"
					value={ value || '' }
					onChange={ ( e ) => onChange( e.target.value ) }
					disabled={ isReadonly }
				/>
			</div>
		</BaseControl>
	);
}

function ProviderPanel( { name, provider, onChange, onDelete, isReadonly, icons } ) {
	const f = provider.fields || {};
	const icon = icons[ f.type ] || icons[ name ] || provider.icon;
	const update = ( key ) => ( val ) => {
		onChange( name, { ...f, [ key ]: val } );
	};

	const titleElement = (
		<span className="wpp-oauth-panel-title">
			{ icon && (
				<span
					className="wpp-oauth-panel-icon"
					dangerouslySetInnerHTML={ { __html: icon } }
				/>
			) }
			{ `${ f.label || name } (${ f.type || '?' })` }
		</span>
	);

	return (
		<PanelBody
			title={ titleElement }
			initialOpen={ false }
			className="wpp-oauth-provider-panel"
		>
			{ isReadonly && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This provider is defined via constant and cannot be edited.',
						'wppack-oauth-login'
					) }
				</Notice>
			) }
			<Field
				id={ `${ name }-name` }
				label={ __( 'Name', 'wppack-oauth-login' ) }
				value={ name }
				disabled={ true }
				onChange={ () => {} }
				help={ __( 'Provider identifier used in URLs.', 'wppack-oauth-login' ) }
			/>
			<Field
				id={ `${ name }-type` }
				label={ __( 'Type', 'wppack-oauth-login' ) }
				value={ f.type || '' }
				disabled={ true }
				onChange={ () => {} }
			/>
			<BaseControl label={ __( 'Login Button Preview', 'wppack-oauth-login' ) }>
				<div className="wpp-oauth-button-preview">
					<a className="button button-large wpp-oauth-login-button">
						{ icon && (
							<span
								className="wpp-oauth-login-icon"
								dangerouslySetInnerHTML={ { __html: icon } }
							/>
						) }
						{ `Login with ${ f.label || name }` }
					</a>
				</div>
			</BaseControl>
			<Field
				id={ `${ name }-client-id` }
				label={ __( 'Client ID', 'wppack-oauth-login' ) }
				value={ f.client_id }
				onChange={ update( 'client_id' ) }
				disabled={ isReadonly }
			/>
			<Field
				id={ `${ name }-client-secret` }
				label={ __( 'Client Secret', 'wppack-oauth-login' ) }
				value={ f.client_secret }
				onChange={ update( 'client_secret' ) }
				disabled={ isReadonly }
				help={
					f.client_secret === MASKED
						? __(
								'Leave as masked to keep current value.',
								'wppack-oauth-login'
						  )
						: undefined
				}
			/>
			<Field
				id={ `${ name }-label` }
				label={ __( 'Label', 'wppack-oauth-login' ) }
				value={ f.label }
				onChange={ update( 'label' ) }
				disabled={ isReadonly }
			/>
			{ f.type === 'entra-id' && (
				<Field
					id={ `${ name }-tenant-id` }
					label={ __( 'Tenant ID', 'wppack-oauth-login' ) }
					value={ f.tenant_id }
					onChange={ update( 'tenant_id' ) }
					disabled={ isReadonly }
					help={ __( 'Required for Microsoft Entra ID.', 'wppack-oauth-login' ) }
				/>
			) }
			{ [ 'okta', 'auth0', 'onelogin', 'keycloak', 'cognito' ].includes( f.type ) && (
				<Field
					id={ `${ name }-domain` }
					label={ __( 'Domain', 'wppack-oauth-login' ) }
					value={ f.domain }
					onChange={ update( 'domain' ) }
					disabled={ isReadonly }
					help={ __( 'Provider domain (e.g., your-tenant.okta.com).', 'wppack-oauth-login' ) }
				/>
			) }
			{ ( f.type === 'google' || ! f.type ) && (
				<Field
					id={ `${ name }-hosted-domain` }
					label={ __( 'Hosted Domain', 'wppack-oauth-login' ) }
					value={ f.hosted_domain }
					onChange={ update( 'hosted_domain' ) }
					disabled={ isReadonly }
					help={ __(
						'Restrict to Google Workspace domain.',
						'wppack-oauth-login'
					) }
				/>
			) }
			{ ( f.type === 'oidc' || ! f.type ) && (
				<Field
					id={ `${ name }-discovery-url` }
					label={ __( 'Discovery URL', 'wppack-oauth-login' ) }
					value={ f.discovery_url }
					onChange={ update( 'discovery_url' ) }
					disabled={ isReadonly }
					help={ __(
						'OpenID Connect discovery endpoint.',
						'wppack-oauth-login'
					) }
				/>
			) }
			<Field
				id={ `${ name }-scopes` }
				label={ __( 'Scopes', 'wppack-oauth-login' ) }
				value={ f.scopes }
				onChange={ update( 'scopes' ) }
				disabled={ isReadonly }
				help={ __(
					'Space-separated. Leave empty for defaults.',
					'wppack-oauth-login'
				) }
			/>
			<BoolField
				label={ __( 'Auto-create Users', 'wppack-oauth-login' ) }
				value={ f.auto_provision }
				onChange={ update( 'auto_provision' ) }
				disabled={ isReadonly }
			/>
			<SelectControl
				label={ __( 'Default Role', 'wppack-oauth-login' ) }
				value={ f.default_role || 'subscriber' }
				onChange={ update( 'default_role' ) }
				disabled={ isReadonly }
				options={ [
					{ label: 'Subscriber', value: 'subscriber' },
					{ label: 'Contributor', value: 'contributor' },
					{ label: 'Author', value: 'author' },
					{ label: 'Editor', value: 'editor' },
					{ label: 'Administrator', value: 'administrator' },
				] }
				__nextHasNoMarginBottom
			/>
			<Field
				id={ `${ name }-role-claim` }
				label={ __( 'Role Claim', 'wppack-oauth-login' ) }
				value={ f.role_claim }
				onChange={ update( 'role_claim' ) }
				disabled={ isReadonly }
				help={ __(
					'JWT claim name containing role.',
					'wppack-oauth-login'
				) }
			/>
			<BaseControl
				id={ `${ name }-role-mapping` }
				label={ __( 'Role Mapping (JSON)', 'wppack-oauth-login' ) }
				help={ __(
					'e.g. {"Admin":"administrator","Member":"subscriber"}',
					'wppack-oauth-login'
				) }
			>
				<TextareaControl
					id={ `${ name }-role-mapping` }
					value={ f.role_mapping || '' }
					onChange={ update( 'role_mapping' ) }
					disabled={ isReadonly }
					rows={ 3 }
					__nextHasNoMarginBottom
				/>
			</BaseControl>
			{ ! isReadonly && (
				<div className="wpp-oauth-delete-provider">
					<Button
						variant="secondary"
						isDestructive
						onClick={ () => onDelete( name ) }
					>
						{ __( 'Delete Provider', 'wppack-oauth-login' ) }
					</Button>
				</div>
			) }
		</PanelBody>
	);
}

export default function App() {
	const [ globalSettings, setGlobalSettings ] = useState( null );
	const [ providers, setProviders ] = useState( {} );
	const [ icons, setIcons ] = useState( {} );
	const [ siteUrl, setSiteUrl ] = useState( '' );
	const [ globalForm, setGlobalForm ] = useState( {} );
	const [ providerForm, setProviderForm ] = useState( {} );
	const [ deletedProviders, setDeletedProviders ] = useState( [] );
	const [ newProviderType, setNewProviderType ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		setSiteUrl( data.siteUrl || '' );
		setIcons( data.icons || {} );
		setGlobalSettings( data.global );
		setProviders( data.providers || {} );

		const gf = {};
		Object.entries( data.global || {} ).forEach( ( [ k, m ] ) => {
			gf[ k ] = m.value;
		} );
		setGlobalForm( gf );

		const pf = {};
		Object.entries( data.providers || {} ).forEach(
			( [ name, provider ] ) => {
				pf[ name ] = provider.fields || {};
			}
		);
		setProviderForm( pf );
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/oauth-login/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to load settings.',
						'wppack-oauth-login'
					),
				} );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );

		apiFetch( {
			path: '/wppack/v1/oauth-login/settings',
			method: 'POST',
			data: {
				global: globalForm,
				providers: providerForm,
				deletedProviders,
			},
		} )
			.then( ( data ) => {
				applyResponse( data );
				setDeletedProviders( [] );
				setNotice( {
					type: 'success',
					message: __(
						'Settings saved.',
						'wppack-oauth-login'
					),
				} );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to save settings.',
						'wppack-oauth-login'
					),
				} );
			} )
			.finally( () => setSaving( false ) );
	};

	const updateGlobal = ( key ) => ( value ) => {
		setGlobalForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	const updateProvider = ( name, fields ) => {
		setProviderForm( ( prev ) => ( { ...prev, [ name ]: fields } ) );
	};

	const handleAddProvider = () => {
		const type = newProviderType;
		if ( ! type ) {
			return;
		}

		// Generate unique name from type
		let name = type;
		let index = 1;
		while ( providerForm[ name ] || providers[ name ] ) {
			name = `${ type }-${ index }`;
			index++;
		}

		const typeLabels = {
			apple: 'Apple', auth0: 'Auth0', cognito: 'AWS Cognito',
			discord: 'Discord', 'entra-id': 'Microsoft Entra ID',
			facebook: 'Facebook', github: 'GitHub', google: 'Google',
			keycloak: 'Keycloak', line: 'LINE', okta: 'Okta',
			onelogin: 'OneLogin', slack: 'Slack', x: 'X', oidc: 'OIDC',
		};

		const defaultFields = {
			type,
			client_id: '',
			client_secret: '',
			label: typeLabels[ type ] || name,
			auto_provision: false,
			default_role: 'subscriber',
		};

		setProviders( ( prev ) => ( {
			...prev,
			[ name ]: { source: 'option', readonly: false, fields: defaultFields },
		} ) );
		setProviderForm( ( prev ) => ( {
			...prev,
			[ name ]: defaultFields,
		} ) );
		setNewProviderType( '' );
	};

	const handleDeleteProvider = ( name ) => {
		setDeletedProviders( ( prev ) => [ ...prev, name ] );
		setProviders( ( prev ) => {
			const next = { ...prev };
			delete next[ name ];
			return next;
		} );
		setProviderForm( ( prev ) => {
			const next = { ...prev };
			delete next[ name ];
			return next;
		} );
	};

	if ( loading ) {
		return (
			<div className="wpp-oauth-loading">
				<Spinner />
			</div>
		);
	}

	const g = ( key ) => globalSettings?.[ key ] || {};

	return (
		<div className="wpp-oauth-settings">
			<h1>{ __( 'OAuth Login Settings', 'wppack-oauth-login' ) }</h1>

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
					title={ __( 'Global Settings', 'wppack-oauth-login' ) }
					initialOpen={ true }
				>
					<BoolField
						label={ __( 'SSO Only Mode', 'wppack-oauth-login' ) }
						field={ g( 'ssoOnly' ) }
						value={ globalForm.ssoOnly }
						onChange={ updateGlobal( 'ssoOnly' ) }
						help={ __(
							'Disable WordPress login form.',
							'wppack-oauth-login'
						) }
					/>
					<BoolField
						label={ __(
							'Auto-create Users',
							'wppack-oauth-login'
						) }
						field={ g( 'autoProvision' ) }
						value={ globalForm.autoProvision }
						onChange={ updateGlobal( 'autoProvision' ) }
						help={ __(
							'Global default. Can be overridden per provider.',
							'wppack-oauth-login'
						) }
					/>
					<SelectControl
						label={
							<>
								{ __(
									'Default Role',
									'wppack-oauth-login'
								) }
								<SourceBadge
									source={ g( 'defaultRole' ).source }
								/>
							</>
						}
						value={ globalForm.defaultRole || 'subscriber' }
						onChange={ updateGlobal( 'defaultRole' ) }
						disabled={ g( 'defaultRole' ).readonly }
						options={ [
							{ label: 'Subscriber', value: 'subscriber' },
							{ label: 'Contributor', value: 'contributor' },
							{ label: 'Author', value: 'author' },
							{ label: 'Editor', value: 'editor' },
							{
								label: 'Administrator',
								value: 'administrator',
							},
						] }
						__nextHasNoMarginBottom
					/>
					<PathField
						id="authorizePath"
						label={ __(
							'Authorize Path',
							'wppack-oauth-login'
						) }
						field={ g( 'authorizePath' ) }
						value={ globalForm.authorizePath }
						onChange={ updateGlobal( 'authorizePath' ) }
						prefix={ siteUrl }
					/>
					<PathField
						id="callbackPath"
						label={ __(
							'Callback Path',
							'wppack-oauth-login'
						) }
						field={ g( 'callbackPath' ) }
						value={ globalForm.callbackPath }
						onChange={ updateGlobal( 'callbackPath' ) }
						prefix={ siteUrl }
					/>
					<PathField
						id="verifyPath"
						label={ __(
							'Verify Path',
							'wppack-oauth-login'
						) }
						field={ g( 'verifyPath' ) }
						value={ globalForm.verifyPath }
						onChange={ updateGlobal( 'verifyPath' ) }
						prefix={ siteUrl }
					/>
				</PanelBody>

				{ Object.entries( providers ).map(
					( [ name, provider ] ) => (
						<ProviderPanel
							key={ name }
							name={ name }
							provider={ {
								...provider,
								fields:
									providerForm[ name ] || provider.fields,
							} }
							onChange={ updateProvider }
							onDelete={ handleDeleteProvider }
							isReadonly={ provider.readonly }
							icons={ icons }
						/>
					)
				) }
			</Panel>

			<div className="wpp-oauth-add-provider">
				<SelectControl
					value={ newProviderType }
					onChange={ setNewProviderType }
					options={ [
						{ label: __( '— Select Provider —', 'wppack-oauth-login' ), value: '' },
						{ label: 'Apple', value: 'apple' },
						{ label: 'Auth0', value: 'auth0' },
						{ label: 'AWS Cognito', value: 'cognito' },
						{ label: 'Discord', value: 'discord' },
						{ label: 'Facebook', value: 'facebook' },
						{ label: 'GitHub', value: 'github' },
						{ label: 'Google', value: 'google' },
						{ label: 'Keycloak', value: 'keycloak' },
						{ label: 'LINE', value: 'line' },
						{ label: 'Microsoft Entra ID', value: 'entra-id' },
						{ label: 'Okta', value: 'okta' },
						{ label: 'OneLogin', value: 'onelogin' },
						{ label: 'Slack', value: 'slack' },
						{ label: 'X (Twitter)', value: 'x' },
						{ label: 'Generic OIDC', value: 'oidc' },
					] }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					onClick={ handleAddProvider }
					disabled={ ! newProviderType }
				>
					{ __( 'Add Provider', 'wppack-oauth-login' ) }
				</Button>
			</div>

			<div className="wpp-oauth-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'wppack-oauth-login' )
						: __( 'Save Settings', 'wppack-oauth-login' ) }
				</Button>
			</div>
		</div>
	);
}
