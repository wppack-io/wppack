import { DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import {
	TextControl,
	ToggleControl,
	SelectControl,
	CheckboxControl,
	__experimentalNumberControl as NumberControl,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

function SourceBadge( { source } ) {
	if ( ! source || source === 'default' ) {
		return null;
	}
	const labels = {
		constant: __( 'Constant', 'wppack-passkey-login' ),
		option: __( 'Saved', 'wppack-passkey-login' ),
	};
	return (
		<span className={ `wpp-passkey-source-badge wpp-passkey-source-${ source }` }>
			{ labels[ source ] || source }
		</span>
	);
}

function badgeLabel( label, showBadge ) {
	if ( ! showBadge ) {
		return label;
	}
	return <><span>{ label }</span><SourceBadge source="constant" /></>;
}

export default function App() {
	const [ formData, setFormData ] = useState( {
		enabled: true,
		rpName: '',
		rpId: '',
		allowSignup: false,
		requireUserVerification: 'preferred',
		algorithms: [ -7, -257 ],
		attestation: 'none',
		authenticatorAttachment: '',
		timeout: 60000,
		residentKey: 'required',
	} );
	const [ meta, setMeta ] = useState( {
		settings: {},
	} );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		const s = data.settings || {};
		const fd = {};
		Object.entries( s ).forEach( ( [ k, m ] ) => {
			fd[ k ] = m.value;
		} );
		setFormData( ( prev ) => ( { ...prev, ...fd } ) );
		setMeta( { settings: s } );
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/passkey-login/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to load settings.', 'wppack-passkey-login' ) } );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/wppack/v1/passkey-login/settings',
			method: 'POST',
			data: formData,
		} )
			.then( ( data ) => {
				applyResponse( data );
				setNotice( { type: 'success', message: __( 'Settings saved.', 'wppack-passkey-login' ) } );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to save settings.', 'wppack-passkey-login' ) } );
			} )
			.finally( () => setSaving( false ) );
	};

	const s = ( key ) => meta.settings[ key ] || {};

	const userVerificationOptions = useMemo( () => [
		{ label: __( 'Preferred', 'wppack-passkey-login' ), value: 'preferred' },
		{ label: __( 'Required', 'wppack-passkey-login' ), value: 'required' },
		{ label: __( 'Discouraged', 'wppack-passkey-login' ), value: 'discouraged' },
	], [] );

	const coseAlgorithms = useMemo( () => [
		{ id: -7, name: 'ES256', recommended: true },
		{ id: -257, name: 'RS256', recommended: true },
		{ id: -8, name: 'EdDSA', recommended: true },
		{ id: -35, name: 'ES384', recommended: false },
		{ id: -36, name: 'ES512', recommended: false },
		{ id: -258, name: 'RS384', recommended: false },
		{ id: -259, name: 'RS512', recommended: false },
	], [] );

	const attestationOptions = useMemo( () => [
		{ label: 'none', value: 'none' },
		{ label: 'indirect', value: 'indirect' },
		{ label: 'direct', value: 'direct' },
		{ label: 'enterprise', value: 'enterprise' },
	], [] );

	const authenticatorAttachmentOptions = useMemo( () => [
		{ label: __( 'Any', 'wppack-passkey-login' ), value: '' },
		{ label: __( 'Platform (built-in biometrics)', 'wppack-passkey-login' ), value: 'platform' },
		{ label: __( 'Cross-platform (security keys)', 'wppack-passkey-login' ), value: 'cross-platform' },
	], [] );

	const residentKeyOptions = useMemo( () => [
		{ label: __( 'Required', 'wppack-passkey-login' ), value: 'required' },
		{ label: __( 'Preferred', 'wppack-passkey-login' ), value: 'preferred' },
		{ label: __( 'Discouraged', 'wppack-passkey-login' ), value: 'discouraged' },
	], [] );

	const toggleAlgorithm = useCallback( ( alg, checked ) => {
		setFormData( ( prev ) => {
			const current = prev.algorithms || [];
			const next = checked
				? [ ...current, alg ]
				: current.filter( ( a ) => a !== alg );
			return { ...prev, algorithms: next };
		} );
	}, [] );

	// ── General fields ──

	const generalFields = useMemo( () => [
		{
			id: 'enabled',
			label: badgeLabel( __( 'Enabled', 'wppack-passkey-login' ), s( 'enabled' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.enabled,
			Edit: ( { data, field } ) => (
				<ToggleControl
					label={ field.label }
					help={ __( 'Enable passkey authentication on the login page.', 'wppack-passkey-login' ) }
					checked={ !! data.enabled }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, enabled: val } ) ) }
					disabled={ !! s( 'enabled' ).readonly }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'rpName',
			label: badgeLabel( __( 'Relying Party Name', 'wppack-passkey-login' ), s( 'rpName' ).source === 'constant' ),
			type: 'text',
			description: __( 'Display name shown in the passkey prompt (e.g. your site name).', 'wppack-passkey-login' ),
			getValue: ( { item } ) => item.rpName || '',
			Edit: ( { data, field } ) => (
				<TextControl
					className="wpp-passkey-narrow"
					id={ field.id }
					label={ field.label }
					value={ data.rpName || '' }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, rpName: val } ) ) }
					disabled={ !! s( 'rpName' ).readonly }
					help={ field.description }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'rpId',
			label: badgeLabel( __( 'Relying Party ID', 'wppack-passkey-login' ), s( 'rpId' ).source === 'constant' ),
			type: 'text',
			description: __( 'Domain of the relying party (e.g. example.com). Must match the site domain.', 'wppack-passkey-login' ),
			getValue: ( { item } ) => item.rpId || '',
			Edit: ( { data, field } ) => (
				<TextControl
					className="wpp-passkey-narrow"
					id={ field.id }
					label={ field.label }
					value={ data.rpId || '' }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, rpId: val } ) ) }
					disabled={ !! s( 'rpId' ).readonly }
					help={ field.description }
					__nextHasNoMarginBottom
				/>
			),
		},
	], [ meta.settings ] );

	// ── Security fields ──

	const securityFields = useMemo( () => [
		{
			id: 'allowSignup',
			label: badgeLabel( __( 'Allow Signup', 'wppack-passkey-login' ), s( 'allowSignup' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.allowSignup,
			Edit: ( { data, field } ) => (
				<ToggleControl
					label={ field.label }
					help={ __( 'Allow new users to register via passkey.', 'wppack-passkey-login' ) }
					checked={ !! data.allowSignup }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, allowSignup: val } ) ) }
					disabled={ !! s( 'allowSignup' ).readonly }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'requireUserVerification',
			label: badgeLabel( __( 'User Verification', 'wppack-passkey-login' ), s( 'requireUserVerification' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.requireUserVerification || 'preferred',
			Edit: ( { data, field } ) => (
				<SelectControl
					id={ field.id }
					label={ field.label }
					value={ data.requireUserVerification || 'preferred' }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, requireUserVerification: val } ) ) }
					disabled={ !! s( 'requireUserVerification' ).readonly }
					options={ userVerificationOptions }
					help={ __( 'Controls whether biometric/PIN verification is required.', 'wppack-passkey-login' ) }
					className="wpp-passkey-small-select"
					__nextHasNoMarginBottom
				/>
			),
		},
	], [ meta.settings, userVerificationOptions ] );

	// ── Advanced fields ──

	const advancedFields = useMemo( () => [
		{
			id: 'algorithms',
			label: badgeLabel( __( 'COSE Algorithms', 'wppack-passkey-login' ), s( 'algorithms' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => ( item.algorithms || [] ).join( ', ' ),
			description: __( 'Select which COSE algorithms to accept for credential registration.', 'wppack-passkey-login' ),
			Edit: ( { field } ) => {
				const readonly = !! s( 'algorithms' ).readonly;
				const selected = formData.algorithms || [];
				return (
					<div className="wpp-passkey-algorithms">
						<div className="wpp-passkey-algorithms-label">{ field.label }</div>
						<p className="wpp-passkey-algorithms-help">
							{ __( 'Select which COSE algorithms to accept for credential registration.', 'wppack-passkey-login' ) }
						</p>
						{ coseAlgorithms.map( ( alg ) => (
							<CheckboxControl
								key={ alg.id }
								label={
									alg.recommended
										? `${ alg.name } (${ alg.id })`
										: `${ alg.name } (${ alg.id }) — ${ alg.id === -258 || alg.id === -259
											? __( 'Not recommended', 'wppack-passkey-login' )
											: __( 'Limited support', 'wppack-passkey-login' ) }`
								}
								checked={ selected.includes( alg.id ) }
								onChange={ ( val ) => toggleAlgorithm( alg.id, val ) }
								disabled={ readonly }
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
				);
			},
		},
		{
			id: 'attestation',
			label: badgeLabel( __( 'Attestation Conveyance', 'wppack-passkey-login' ), s( 'attestation' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.attestation || 'none',
			Edit: ( { data, field } ) => (
				<SelectControl
					id={ field.id }
					label={ field.label }
					value={ data.attestation || 'none' }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, attestation: val } ) ) }
					disabled={ !! s( 'attestation' ).readonly }
					options={ attestationOptions }
					help={ data.attestation === 'enterprise'
						? __( 'Enterprise attestation requires pre-registration with the authenticator vendor.', 'wppack-passkey-login' )
						: __( 'Controls whether the authenticator provides an attestation statement.', 'wppack-passkey-login' ) }
					className="wpp-passkey-small-select"
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'authenticatorAttachment',
			label: badgeLabel( __( 'Authenticator Attachment', 'wppack-passkey-login' ), s( 'authenticatorAttachment' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.authenticatorAttachment || '',
			Edit: ( { data, field } ) => (
				<SelectControl
					id={ field.id }
					label={ field.label }
					value={ data.authenticatorAttachment || '' }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, authenticatorAttachment: val } ) ) }
					disabled={ !! s( 'authenticatorAttachment' ).readonly }
					options={ authenticatorAttachmentOptions }
					help={ __( 'Restrict which authenticator types are allowed.', 'wppack-passkey-login' ) }
					className="wpp-passkey-small-select"
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'timeout',
			label: badgeLabel( __( 'Ceremony Timeout', 'wppack-passkey-login' ), s( 'timeout' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => String( item.timeout || 60000 ),
			Edit: ( { data, field } ) => (
				<NumberControl
					className="wpp-passkey-narrow-sm"
					id={ field.id }
					label={ field.label }
					value={ data.timeout ?? 60000 }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, timeout: Number( val ) } ) ) }
					disabled={ !! s( 'timeout' ).readonly }
					min={ 1000 }
					max={ 300000 }
					step={ 1000 }
					help={ __( 'Timeout in milliseconds (1000 - 300000).', 'wppack-passkey-login' ) }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'residentKey',
			label: badgeLabel( __( 'Resident Key', 'wppack-passkey-login' ), s( 'residentKey' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.residentKey || 'required',
			Edit: ( { data, field } ) => (
				<SelectControl
					id={ field.id }
					label={ field.label }
					value={ data.residentKey || 'required' }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, residentKey: val } ) ) }
					disabled={ !! s( 'residentKey' ).readonly }
					options={ residentKeyOptions }
					help={ data.residentKey === 'discouraged'
						? __( 'Discouraged: passkeys may not work without resident key support.', 'wppack-passkey-login' )
						: __( 'Controls whether a discoverable credential (resident key) is required.', 'wppack-passkey-login' ) }
					className="wpp-passkey-small-select"
					__nextHasNoMarginBottom
				/>
			),
		},
	], [ meta.settings, coseAlgorithms, attestationOptions, authenticatorAttachmentOptions, residentKeyOptions, formData.algorithms, toggleAlgorithm ] );

	// ── Combined form layout ──

	const allFields = useMemo(
		() => [ ...generalFields, ...securityFields, ...advancedFields ],
		[ generalFields, securityFields, advancedFields ]
	);

	const formLayout = useMemo( () => ( {
		fields: [
			{
				id: 'general-section',
				label: __( 'General', 'wppack-passkey-login' ),
				children: generalFields.map( ( f ) => f.id ),
				layout: { type: 'regular' },
			},
			{
				id: 'security-section',
				label: __( 'Security', 'wppack-passkey-login' ),
				children: securityFields.map( ( f ) => f.id ),
				layout: { type: 'regular' },
			},
			{
				id: 'advanced-section',
				label: __( 'Advanced', 'wppack-passkey-login' ),
				children: advancedFields.map( ( f ) => f.id ),
				layout: { type: 'regular' },
			},
		],
	} ), [ generalFields, securityFields, advancedFields ] );

	const handleFormChange = ( edits ) => {
		setFormData( ( prev ) => ( { ...prev, ...edits } ) );
	};

	if ( loading ) {
		return <div className="wpp-passkey-loading"><Spinner /></div>;
	}

	return (
		<Page title={ __( 'Passkey Login Settings', 'wppack-passkey-login' ) } hasPadding>
			<div className="wpp-passkey-settings">
				{ notice && (
					<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) }>
						{ notice.message }
					</Notice>
				) }

				<div className="wpp-passkey-dataform-wrap">
					<DataForm
						data={ formData }
						fields={ allFields }
						form={ formLayout }
						onChange={ handleFormChange }
					/>
				</div>

				<div className="wpp-passkey-actions">
					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ saving }
						disabled={ saving }
					>
						{ saving ? __( 'Saving…', 'wppack-passkey-login' ) : __( 'Save Settings', 'wppack-passkey-login' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
