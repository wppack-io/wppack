import { DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	TextControl,
	TextareaControl,
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

const SENSITIVE_MASK = '********';

function SourceBadge( { source } ) {
	if ( ! source || source === 'default' ) {
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

function badgeLabel( label, source ) {
	if ( ! source || source === 'default' ) {
		return label;
	}
	return <><span>{ label }</span><SourceBadge source={ source } /></>;
}

function PathField( { id, label, value, onChange, prefix, disabled } ) {
	return (
		<BaseControl id={ id } label={ label }>
			<div className="wpp-saml-path-field">
				<span className="wpp-saml-path-prefix">{ prefix }</span>
				<input
					type="text"
					id={ id }
					className="components-text-control__input"
					value={ value || '' }
					onChange={ ( e ) => onChange( e.target.value ) }
					disabled={ disabled }
				/>
			</div>
		</BaseControl>
	);
}

export default function App() {
	const [ formData, setFormData ] = useState( {} );
	const [ meta, setMeta ] = useState( {
		fields: {},
		siteUrl: '',
	} );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		const values = {};
		Object.entries( data.fields ).forEach( ( [ key, m ] ) => {
			values[ key ] = m.value;
		} );
		setFormData( values );
		setMeta( {
			fields: data.fields,
			siteUrl: data.siteUrl || '',
		} );
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/saml-login/settings' } )
			.then( ( data ) => {
				applyResponse( data );
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
				applyResponse( data );
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

	const handleDownloadMetadata = () => {
		apiFetch( {
			path: '/wppack/v1/saml-login/metadata',
			parse: false,
		} )
			.then( ( response ) => response.blob() )
			.then( ( blob ) => {
				const url = URL.createObjectURL( blob );
				const a = document.createElement( 'a' );
				a.href = url;
				a.download = 'sp-metadata.xml';
				a.click();
				URL.revokeObjectURL( url );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to download metadata. Ensure SAML is configured.',
						'wppack-saml-login'
					),
				} );
			} );
	};

	const f = ( key ) => meta.fields[ key ] || {};
	const isSensitive = ( key ) =>
		[ 'idpX509Cert', 'idpCertFingerprint' ].includes( key );

	const nameIdOptions = useMemo(
		() => [
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
		],
		[]
	);

	// ── Identity Provider fields ──

	const idpFields = useMemo(
		() => [
			{
				id: 'idpEntityId',
				label: badgeLabel(
					__( 'Entity ID', 'wppack-saml-login' ),
					f( 'idpEntityId' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.idpEntityId || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								idpEntityId: val,
							} ) )
						}
						disabled={ f( 'idpEntityId' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'idpSsoUrl',
				label: badgeLabel(
					__( 'SSO URL', 'wppack-saml-login' ),
					f( 'idpSsoUrl' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.idpSsoUrl || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								idpSsoUrl: val,
							} ) )
						}
						disabled={ f( 'idpSsoUrl' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'idpSloUrl',
				label: badgeLabel(
					__( 'SLO URL', 'wppack-saml-login' ),
					f( 'idpSloUrl' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.idpSloUrl || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								idpSloUrl: val,
							} ) )
						}
						disabled={ f( 'idpSloUrl' ).readonly }
						help={ __(
							'Single Logout URL (optional)',
							'wppack-saml-login'
						) }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'idpX509Cert',
				label: badgeLabel(
					__( 'X.509 Certificate', 'wppack-saml-login' ),
					f( 'idpX509Cert' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextareaControl
						id={ field.id }
						label={ field.label }
						value={
							isSensitive( 'idpX509Cert' ) &&
							data.idpX509Cert === SENSITIVE_MASK
								? SENSITIVE_MASK
								: data.idpX509Cert || ''
						}
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								idpX509Cert: val,
							} ) )
						}
						disabled={ f( 'idpX509Cert' ).readonly }
						rows={ 4 }
						help={ __(
							'PEM format. Leave as masked to keep current value.',
							'wppack-saml-login'
						) }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'idpCertFingerprint',
				label: badgeLabel(
					__( 'Certificate Fingerprint', 'wppack-saml-login' ),
					f( 'idpCertFingerprint' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.idpCertFingerprint || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								idpCertFingerprint: val,
							} ) )
						}
						disabled={ f( 'idpCertFingerprint' ).readonly }
						help={ __(
							'Leave as masked to keep current value.',
							'wppack-saml-login'
						) }
						__nextHasNoMarginBottom
					/>
				),
			},
		],
		[ meta.fields ]
	);

	// ── Service Provider fields ──

	const spFields = useMemo(
		() => [
			{
				id: 'spEntityId',
				label: badgeLabel(
					__( 'Entity ID', 'wppack-saml-login' ),
					f( 'spEntityId' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.spEntityId || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								spEntityId: val,
							} ) )
						}
						disabled={ f( 'spEntityId' ).readonly }
						help={ __(
							'Defaults to site URL if empty.',
							'wppack-saml-login'
						) }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'metadataPath',
				label: badgeLabel(
					__( 'Metadata Path', 'wppack-saml-login' ),
					f( 'metadataPath' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<PathField
						id={ field.id }
						label={ field.label }
						value={ data.metadataPath }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								metadataPath: val,
							} ) )
						}
						prefix={ meta.siteUrl }
						disabled={ f( 'metadataPath' ).readonly }
					/>
				),
			},
			{
				id: 'acsPath',
				label: badgeLabel(
					__( 'ACS Path', 'wppack-saml-login' ),
					f( 'acsPath' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<PathField
						id={ field.id }
						label={ field.label }
						value={ data.acsPath }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								acsPath: val,
							} ) )
						}
						prefix={ meta.siteUrl }
						disabled={ f( 'acsPath' ).readonly }
					/>
				),
			},
			{
				id: 'sloPath',
				label: badgeLabel(
					__( 'SLO Path', 'wppack-saml-login' ),
					f( 'sloPath' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<PathField
						id={ field.id }
						label={ field.label }
						value={ data.sloPath }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								sloPath: val,
							} ) )
						}
						prefix={ meta.siteUrl }
						disabled={ f( 'sloPath' ).readonly }
					/>
				),
			},
			{
				id: 'spNameIdFormat',
				label: badgeLabel(
					__( 'NameID Format', 'wppack-saml-login' ),
					f( 'spNameIdFormat' ).source
				),
				type: 'text',
				elements: nameIdOptions,
				Edit: ( { data, field } ) => (
					<SelectControl
						className="wpp-saml-narrow"
						id={ field.id }
						label={ field.label }
						value={ data.spNameIdFormat }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								spNameIdFormat: val,
							} ) )
						}
						disabled={ f( 'spNameIdFormat' ).readonly }
						options={ nameIdOptions }
						__nextHasNoMarginBottom
					/>
				),
			},
		],
		[ meta.fields, meta.siteUrl, nameIdOptions ]
	);

	// ── Security fields ──

	const securityFields = useMemo(
		() => [
			{
				id: 'strict',
				label: badgeLabel(
					__( 'Strict Mode', 'wppack-saml-login' ),
					f( 'strict' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<ToggleControl
						label={ field.label }
						checked={ !! data.strict }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								strict: val,
							} ) )
						}
						disabled={ f( 'strict' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'wantAssertionsSigned',
				label: badgeLabel(
					__(
						'Require Signed Assertions',
						'wppack-saml-login'
					),
					f( 'wantAssertionsSigned' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<ToggleControl
						label={ field.label }
						checked={ !! data.wantAssertionsSigned }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								wantAssertionsSigned: val,
							} ) )
						}
						disabled={ f( 'wantAssertionsSigned' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
		],
		[ meta.fields ]
	);

	// ── User Provisioning fields ──

	const provisioningFields = useMemo(
		() => [
			{
				id: 'autoProvision',
				label: badgeLabel(
					__( 'Auto-create Users', 'wppack-saml-login' ),
					f( 'autoProvision' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<ToggleControl
						label={ field.label }
						checked={ !! data.autoProvision }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								autoProvision: val,
							} ) )
						}
						disabled={ f( 'autoProvision' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'emailAttribute',
				label: badgeLabel(
					__( 'Email Attribute', 'wppack-saml-login' ),
					f( 'emailAttribute' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.emailAttribute || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								emailAttribute: val,
							} ) )
						}
						disabled={ f( 'emailAttribute' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'firstNameAttribute',
				label: badgeLabel(
					__( 'First Name Attribute', 'wppack-saml-login' ),
					f( 'firstNameAttribute' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.firstNameAttribute || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								firstNameAttribute: val,
							} ) )
						}
						disabled={ f( 'firstNameAttribute' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'lastNameAttribute',
				label: badgeLabel(
					__( 'Last Name Attribute', 'wppack-saml-login' ),
					f( 'lastNameAttribute' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.lastNameAttribute || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								lastNameAttribute: val,
							} ) )
						}
						disabled={ f( 'lastNameAttribute' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'displayNameAttribute',
				label: badgeLabel(
					__( 'Display Name Attribute', 'wppack-saml-login' ),
					f( 'displayNameAttribute' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.displayNameAttribute || '' }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								displayNameAttribute: val,
							} ) )
						}
						disabled={ f( 'displayNameAttribute' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
		],
		[ meta.fields ]
	);

	// ── Advanced fields ──

	const advancedFields = useMemo(
		() => [
			{
				id: 'ssoOnly',
				label: badgeLabel(
					__( 'SSO Only Mode', 'wppack-saml-login' ),
					f( 'ssoOnly' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<ToggleControl
						label={ field.label }
						checked={ !! data.ssoOnly }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								ssoOnly: val,
							} ) )
						}
						disabled={ f( 'ssoOnly' ).readonly }
						help={ __(
							'Disable WordPress login form and redirect to SAML IdP.',
							'wppack-saml-login'
						) }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'debug',
				label: badgeLabel(
					__( 'Debug Mode', 'wppack-saml-login' ),
					f( 'debug' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<ToggleControl
						label={ field.label }
						checked={ !! data.debug }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								debug: val,
							} ) )
						}
						disabled={ f( 'debug' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'allowRepeatAttributeName',
				label: badgeLabel(
					__(
						'Allow Repeat Attribute Name',
						'wppack-saml-login'
					),
					f( 'allowRepeatAttributeName' ).source
				),
				type: 'text',
				Edit: ( { data, field } ) => (
					<ToggleControl
						label={ field.label }
						checked={ !! data.allowRepeatAttributeName }
						onChange={ ( val ) =>
							setFormData( ( prev ) => ( {
								...prev,
								allowRepeatAttributeName: val,
							} ) )
						}
						disabled={ f( 'allowRepeatAttributeName' ).readonly }
						__nextHasNoMarginBottom
					/>
				),
			},
		],
		[ meta.fields ]
	);

	// ── Combined form layout ──

	const allFields = useMemo(
		() => [
			...idpFields,
			...spFields,
			...securityFields,
			...provisioningFields,
			...advancedFields,
		],
		[ idpFields, spFields, securityFields, provisioningFields, advancedFields ]
	);

	const formConfig = useMemo(
		() => ( {
			fields: [
				{
					id: 'idp-section',
					label: __(
						'Identity Provider (IdP)',
						'wppack-saml-login'
					),
					children: idpFields.map( ( fld ) => fld.id ),
					layout: { type: 'regular' },
				},
				{
					id: 'sp-section',
					label: __(
						'Service Provider (SP)',
						'wppack-saml-login'
					),
					children: spFields.map( ( fld ) => fld.id ),
					layout: { type: 'regular' },
				},
				{
					id: 'security-section',
					label: __( 'Security', 'wppack-saml-login' ),
					children: securityFields.map( ( fld ) => fld.id ),
					layout: { type: 'regular' },
				},
				{
					id: 'provisioning-section',
					label: __(
						'User Provisioning',
						'wppack-saml-login'
					),
					children: provisioningFields.map( ( fld ) => fld.id ),
					layout: { type: 'regular' },
				},
				{
					id: 'advanced-section',
					label: __( 'Advanced', 'wppack-saml-login' ),
					children: advancedFields.map( ( fld ) => fld.id ),
					layout: { type: 'regular' },
				},
			],
		} ),
		[ idpFields, spFields, securityFields, provisioningFields, advancedFields ]
	);

	const handleFormChange = ( edits ) => {
		setFormData( ( prev ) => ( { ...prev, ...edits } ) );
	};

	if ( loading ) {
		return (
			<div className="wpp-saml-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<Page title={ __( 'SAML Login Settings', 'wppack-saml-login' ) } hasPadding>
			<div className="wpp-saml-settings">
				{ notice && (
					<Notice
						status={ notice.type }
						isDismissible
						onDismiss={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }

				<div className="wpp-saml-dataform-wrap">
					<DataForm
						data={ formData }
						fields={ allFields }
						form={ formConfig }
						onChange={ handleFormChange }
					/>
				</div>

				<div className="wpp-saml-metadata-download">
					<Button
						variant="secondary"
						onClick={ handleDownloadMetadata }
					>
						{ __( 'Download SP Metadata', 'wppack-saml-login' ) }
					</Button>
				</div>

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
		</Page>
	);
}
