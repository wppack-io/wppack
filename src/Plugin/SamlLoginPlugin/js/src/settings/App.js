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

const SENSITIVE_MASK = '********';

function SourceBadge( { source } ) {
	if ( source === 'default' ) {
		return null;
	}
	const labels = {
		constant: __( 'Constant', 'wppack-saml-login' ),
		env: __( 'Env', 'wppack-saml-login' ),
		option: __( 'Saved', 'wppack-saml-login' ),
	};
	return (
		<span className={ `wpp-saml-source-badge wpp-saml-source-${ source }` }>
			{ labels[ source ] || source }
		</span>
	);
}

function Field( { id, label, field, value, onChange, help } ) {
	const isReadonly = field?.readonly;
	const source = field?.source || 'default';

	return (
		<BaseControl
			id={ id }
			label={
				<>
					{ label }
					<SourceBadge source={ source } />
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

function BoolField( { id, label, field, value, onChange, help } ) {
	const isReadonly = field?.readonly;

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

export default function App() {
	const [ settings, setSettings ] = useState( null );
	const [ formData, setFormData ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		apiFetch( {
			path: '/wppack/v1/saml-login/settings',
		} )
			.then( ( data ) => {
				setSettings( data );
				const initial = {};
				Object.entries( data ).forEach( ( [ key, meta ] ) => {
					initial[ key ] = meta.value;
				} );
				setFormData( initial );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Failed to load settings.', 'wppack-saml-login' ),
				} );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );

		apiFetch( {
			path: '/wppack/v1/saml-login/settings',
			method: 'POST',
			data: formData,
		} )
			.then( ( data ) => {
				setSettings( data );
				const updated = {};
				Object.entries( data ).forEach( ( [ key, meta ] ) => {
					updated[ key ] = meta.value;
				} );
				setFormData( updated );
				setNotice( {
					type: 'success',
					message: __( 'Settings saved.', 'wppack-saml-login' ),
				} );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Failed to save settings.', 'wppack-saml-login' ),
				} );
			} )
			.finally( () => setSaving( false ) );
	};

	const updateField = ( key ) => ( value ) => {
		setFormData( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	if ( loading ) {
		return (
			<div className="wpp-saml-loading">
				<Spinner />
			</div>
		);
	}

	const f = ( key ) => settings?.[ key ] || {};
	const isSensitive = ( key ) =>
		[ 'idpX509Cert', 'idpCertFingerprint' ].includes( key );

	return (
		<div className="wpp-saml-settings">
			<h1>{ __( 'SAML Login Settings', 'wppack-saml-login' ) }</h1>

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
					title={ __( 'Identity Provider (IdP)', 'wppack-saml-login' ) }
					initialOpen={ true }
				>
					<Field
						id="idpEntityId"
						label={ __( 'Entity ID', 'wppack-saml-login' ) }
						field={ f( 'idpEntityId' ) }
						value={ formData.idpEntityId }
						onChange={ updateField( 'idpEntityId' ) }
					/>
					<Field
						id="idpSsoUrl"
						label={ __( 'SSO URL', 'wppack-saml-login' ) }
						field={ f( 'idpSsoUrl' ) }
						value={ formData.idpSsoUrl }
						onChange={ updateField( 'idpSsoUrl' ) }
					/>
					<Field
						id="idpSloUrl"
						label={ __( 'SLO URL', 'wppack-saml-login' ) }
						field={ f( 'idpSloUrl' ) }
						value={ formData.idpSloUrl }
						onChange={ updateField( 'idpSloUrl' ) }
						help={ __( 'Single Logout URL (optional)', 'wppack-saml-login' ) }
					/>
					<BaseControl
						id="idpX509Cert"
						label={
							<>
								{ __( 'X.509 Certificate', 'wppack-saml-login' ) }
								<SourceBadge source={ f( 'idpX509Cert' ).source } />
							</>
						}
					>
						<TextareaControl
							id="idpX509Cert"
							value={
								isSensitive( 'idpX509Cert' ) &&
								formData.idpX509Cert === SENSITIVE_MASK
									? SENSITIVE_MASK
									: formData.idpX509Cert || ''
							}
							onChange={ updateField( 'idpX509Cert' ) }
							disabled={ f( 'idpX509Cert' ).readonly }
							rows={ 4 }
							help={ __(
								'PEM format. Leave as masked to keep current value.',
								'wppack-saml-login'
							) }
							__nextHasNoMarginBottom
						/>
					</BaseControl>
					<Field
						id="idpCertFingerprint"
						label={ __( 'Certificate Fingerprint', 'wppack-saml-login' ) }
						field={ f( 'idpCertFingerprint' ) }
						value={ formData.idpCertFingerprint }
						onChange={ updateField( 'idpCertFingerprint' ) }
						help={ __(
							'Leave as masked to keep current value.',
							'wppack-saml-login'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Service Provider (SP)', 'wppack-saml-login' ) }
					initialOpen={ false }
				>
					<Field
						id="spEntityId"
						label={ __( 'Entity ID', 'wppack-saml-login' ) }
						field={ f( 'spEntityId' ) }
						value={ formData.spEntityId }
						onChange={ updateField( 'spEntityId' ) }
						help={ __( 'Defaults to site URL if empty.', 'wppack-saml-login' ) }
					/>
					<Field
						id="spAcsUrl"
						label={ __( 'ACS URL', 'wppack-saml-login' ) }
						field={ f( 'spAcsUrl' ) }
						value={ formData.spAcsUrl }
						onChange={ updateField( 'spAcsUrl' ) }
					/>
					<Field
						id="spSloUrl"
						label={ __( 'SLO URL', 'wppack-saml-login' ) }
						field={ f( 'spSloUrl' ) }
						value={ formData.spSloUrl }
						onChange={ updateField( 'spSloUrl' ) }
					/>
					<SelectControl
						label={
							<>
								{ __( 'NameID Format', 'wppack-saml-login' ) }
								<SourceBadge source={ f( 'spNameIdFormat' ).source } />
							</>
						}
						value={ formData.spNameIdFormat }
						onChange={ updateField( 'spNameIdFormat' ) }
						disabled={ f( 'spNameIdFormat' ).readonly }
						options={ [
							{
								label: 'Unspecified',
								value: 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
							},
							{
								label: 'Email Address',
								value: 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
							},
							{
								label: 'Persistent',
								value: 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
							},
							{
								label: 'Transient',
								value: 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
							},
						] }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Security', 'wppack-saml-login' ) }
					initialOpen={ false }
				>
					<BoolField
						id="strict"
						label={ __( 'Strict Mode', 'wppack-saml-login' ) }
						field={ f( 'strict' ) }
						value={ formData.strict }
						onChange={ updateField( 'strict' ) }
					/>
					<BoolField
						id="wantAssertionsSigned"
						label={ __(
							'Require Signed Assertions',
							'wppack-saml-login'
						) }
						field={ f( 'wantAssertionsSigned' ) }
						value={ formData.wantAssertionsSigned }
						onChange={ updateField( 'wantAssertionsSigned' ) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'User Provisioning', 'wppack-saml-login' ) }
					initialOpen={ false }
				>
					<BoolField
						id="autoProvision"
						label={ __( 'Auto-create Users', 'wppack-saml-login' ) }
						field={ f( 'autoProvision' ) }
						value={ formData.autoProvision }
						onChange={ updateField( 'autoProvision' ) }
					/>
					<SelectControl
						label={
							<>
								{ __( 'Default Role', 'wppack-saml-login' ) }
								<SourceBadge source={ f( 'defaultRole' ).source } />
							</>
						}
						value={ formData.defaultRole }
						onChange={ updateField( 'defaultRole' ) }
						disabled={ f( 'defaultRole' ).readonly }
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
						id="emailAttribute"
						label={ __( 'Email Attribute', 'wppack-saml-login' ) }
						field={ f( 'emailAttribute' ) }
						value={ formData.emailAttribute }
						onChange={ updateField( 'emailAttribute' ) }
					/>
					<Field
						id="firstNameAttribute"
						label={ __( 'First Name Attribute', 'wppack-saml-login' ) }
						field={ f( 'firstNameAttribute' ) }
						value={ formData.firstNameAttribute }
						onChange={ updateField( 'firstNameAttribute' ) }
					/>
					<Field
						id="lastNameAttribute"
						label={ __( 'Last Name Attribute', 'wppack-saml-login' ) }
						field={ f( 'lastNameAttribute' ) }
						value={ formData.lastNameAttribute }
						onChange={ updateField( 'lastNameAttribute' ) }
					/>
					<Field
						id="displayNameAttribute"
						label={ __( 'Display Name Attribute', 'wppack-saml-login' ) }
						field={ f( 'displayNameAttribute' ) }
						value={ formData.displayNameAttribute }
						onChange={ updateField( 'displayNameAttribute' ) }
					/>
					<Field
						id="roleAttribute"
						label={ __( 'Role Attribute', 'wppack-saml-login' ) }
						field={ f( 'roleAttribute' ) }
						value={ formData.roleAttribute }
						onChange={ updateField( 'roleAttribute' ) }
						help={ __(
							'SAML attribute name for role mapping.',
							'wppack-saml-login'
						) }
					/>
					<BaseControl
						id="roleMapping"
						label={
							<>
								{ __( 'Role Mapping (JSON)', 'wppack-saml-login' ) }
								<SourceBadge source={ f( 'roleMapping' ).source } />
							</>
						}
						help={ __(
							'JSON object mapping SAML roles to WordPress roles, e.g. {"admin":"administrator","member":"subscriber"}',
							'wppack-saml-login'
						) }
					>
						<TextareaControl
							id="roleMapping"
							value={ formData.roleMapping || '' }
							onChange={ updateField( 'roleMapping' ) }
							disabled={ f( 'roleMapping' ).readonly }
							rows={ 3 }
							__nextHasNoMarginBottom
						/>
					</BaseControl>
					<BoolField
						id="addUserToBlog"
						label={ __( 'Add User to Blog', 'wppack-saml-login' ) }
						field={ f( 'addUserToBlog' ) }
						value={ formData.addUserToBlog }
						onChange={ updateField( 'addUserToBlog' ) }
						help={ __(
							'Multisite: add user to the current blog on login.',
							'wppack-saml-login'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Advanced', 'wppack-saml-login' ) }
					initialOpen={ false }
				>
					<BoolField
						id="ssoOnly"
						label={ __( 'SSO Only Mode', 'wppack-saml-login' ) }
						field={ f( 'ssoOnly' ) }
						value={ formData.ssoOnly }
						onChange={ updateField( 'ssoOnly' ) }
						help={ __(
							'Disable WordPress login form and redirect to SAML IdP.',
							'wppack-saml-login'
						) }
					/>
					<BoolField
						id="debug"
						label={ __( 'Debug Mode', 'wppack-saml-login' ) }
						field={ f( 'debug' ) }
						value={ formData.debug }
						onChange={ updateField( 'debug' ) }
					/>
					<BoolField
						id="allowRepeatAttributeName"
						label={ __(
							'Allow Repeat Attribute Name',
							'wppack-saml-login'
						) }
						field={ f( 'allowRepeatAttributeName' ) }
						value={ formData.allowRepeatAttributeName }
						onChange={ updateField( 'allowRepeatAttributeName' ) }
					/>
					<Field
						id="metadataPath"
						label={ __( 'Metadata Path', 'wppack-saml-login' ) }
						field={ f( 'metadataPath' ) }
						value={ formData.metadataPath }
						onChange={ updateField( 'metadataPath' ) }
					/>
					<Field
						id="acsPath"
						label={ __( 'ACS Path', 'wppack-saml-login' ) }
						field={ f( 'acsPath' ) }
						value={ formData.acsPath }
						onChange={ updateField( 'acsPath' ) }
					/>
					<Field
						id="sloPath"
						label={ __( 'SLO Path', 'wppack-saml-login' ) }
						field={ f( 'sloPath' ) }
						value={ formData.sloPath }
						onChange={ updateField( 'sloPath' ) }
					/>
				</PanelBody>
			</Panel>

			<div className="wpp-saml-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'wppack-saml-login' )
						: __( 'Save Settings', 'wppack-saml-login' ) }
				</Button>
			</div>
		</div>
	);
}
