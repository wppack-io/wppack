import { DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	TextControl,
	ToggleControl,
	SelectControl,
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

	// ── Combined form layout ──

	const allFields = useMemo(
		() => [ ...generalFields, ...securityFields ],
		[ generalFields, securityFields ]
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
		],
	} ), [ generalFields, securityFields ] );

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
