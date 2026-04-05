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

function badgeLabel( label, showBadge ) {
	if ( ! showBadge ) {
		return label;
	}
	return <><span>{ label }</span><SourceBadge source="constant" /></>;
}

export default function App() {
	const [ formData, setFormData ] = useState( {
		bearerToken: '',
		autoProvision: false,
		defaultRole: 'subscriber',
		allowGroupManagement: false,
		allowUserDeletion: false,
		maxResults: 100,
	} );
	const [ meta, setMeta ] = useState( {
		settings: {},
		baseUrl: '',
		roles: {},
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
		setMeta( {
			settings: s,
			baseUrl: data.baseUrl || '',
			roles: data.roles || {},
		} );
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/scim/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to load settings.', 'wppack-scim' ) } );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/wppack/v1/scim/settings',
			method: 'POST',
			data: formData,
		} )
			.then( ( data ) => {
				applyResponse( data );
				setNotice( { type: 'success', message: __( 'Settings saved.', 'wppack-scim' ) } );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to save settings.', 'wppack-scim' ) } );
			} )
			.finally( () => setSaving( false ) );
	};

	const s = ( key ) => meta.settings[ key ] || {};

	const roleOptions = useMemo( () =>
		Object.entries( meta.roles ).map( ( [ value, label ] ) => ( { label, value } ) ),
	[ meta.roles ] );

	// ── Authentication fields ──

	const authFields = useMemo( () => [
		{
			id: 'bearerToken',
			label: badgeLabel( __( 'Bearer Token', 'wppack-scim' ), s( 'bearerToken' ).source === 'constant' ),
			type: 'text',
			description: __( 'Token for SCIM API authentication. Leave as masked to keep current value.', 'wppack-scim' ),
			getValue: ( { item } ) => item.bearerToken || '',
			Edit: ( { data, field } ) => (
				<TextControl
					id={ field.id }
					label={ field.label }
					value={ data.bearerToken || '' }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, bearerToken: val } ) ) }
					disabled={ !! s( 'bearerToken' ).readonly }
					help={ field.description }
					__nextHasNoMarginBottom
				/>
			),
		},
	], [ meta.settings ] );

	// ── Provisioning fields ──

	const provisioningFields = useMemo( () => [
		{
			id: 'autoProvision',
			label: badgeLabel( __( 'Auto Provision', 'wppack-scim' ), s( 'autoProvision' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.autoProvision,
			Edit: ( { data, field } ) => (
				<ToggleControl
					label={ field.label }
					help={ __( 'Automatically create WordPress users from SCIM requests.', 'wppack-scim' ) }
					checked={ !! data.autoProvision }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, autoProvision: val } ) ) }
					disabled={ !! s( 'autoProvision' ).readonly }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'defaultRole',
			label: badgeLabel( __( 'Default Role', 'wppack-scim' ), s( 'defaultRole' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.defaultRole || 'subscriber',
			Edit: ( { data, field } ) => (
				<SelectControl
					id={ field.id }
					label={ field.label }
					value={ data.defaultRole || 'subscriber' }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, defaultRole: val } ) ) }
					disabled={ !! s( 'defaultRole' ).readonly }
					options={ roleOptions }
					className="wpp-scim-small-select"
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'allowGroupManagement',
			label: badgeLabel( __( 'Allow Group Management', 'wppack-scim' ), s( 'allowGroupManagement' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.allowGroupManagement,
			Edit: ( { data, field } ) => (
				<ToggleControl
					label={ field.label }
					help={ __( 'Allow SCIM to manage WordPress roles as groups.', 'wppack-scim' ) }
					checked={ !! data.allowGroupManagement }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, allowGroupManagement: val } ) ) }
					disabled={ !! s( 'allowGroupManagement' ).readonly }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'allowUserDeletion',
			label: badgeLabel( __( 'Allow User Deletion', 'wppack-scim' ), s( 'allowUserDeletion' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => item.allowUserDeletion,
			Edit: ( { data, field } ) => (
				<ToggleControl
					label={ field.label }
					help={ __( 'Allow SCIM DELETE requests to remove WordPress users.', 'wppack-scim' ) }
					checked={ !! data.allowUserDeletion }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, allowUserDeletion: val } ) ) }
					disabled={ !! s( 'allowUserDeletion' ).readonly }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'maxResults',
			label: badgeLabel( __( 'Max Results', 'wppack-scim' ), s( 'maxResults' ).source === 'constant' ),
			type: 'text',
			getValue: ( { item } ) => String( item.maxResults || 100 ),
			Edit: ( { data, field } ) => (
				<TextControl
					id={ field.id }
					label={ field.label }
					type="number"
					min={ 1 }
					max={ 1000 }
					value={ String( data.maxResults || 100 ) }
					onChange={ ( val ) => setFormData( ( prev ) => ( { ...prev, maxResults: parseInt( val, 10 ) || 100 } ) ) }
					disabled={ !! s( 'maxResults' ).readonly }
					className="wpp-scim-small-input"
					help={ __( 'Maximum number of resources returned per list request (1-1000).', 'wppack-scim' ) }
					__nextHasNoMarginBottom
				/>
			),
		},
	], [ meta.settings, roleOptions ] );

	// ── SCIM Endpoints fields ──

	const endpointFields = useMemo( () => {
		const base = meta.baseUrl;
		const endpoints = [
			{ id: 'endpointUsers', label: 'Users', path: '/scim/v2/Users' },
			{ id: 'endpointGroups', label: 'Groups', path: '/scim/v2/Groups' },
			{ id: 'endpointServiceProviderConfig', label: 'ServiceProviderConfig', path: '/scim/v2/ServiceProviderConfig' },
			{ id: 'endpointSchemas', label: 'Schemas', path: '/scim/v2/Schemas' },
			{ id: 'endpointResourceTypes', label: 'ResourceTypes', path: '/scim/v2/ResourceTypes' },
		];
		return endpoints.map( ( ep ) => ( {
			id: ep.id,
			label: ep.label,
			type: 'text',
			getValue: () => `${ base }${ ep.path }`,
			Edit: ( { field } ) => (
				<TextControl
					id={ field.id }
					label={ field.label }
					value={ `${ base }${ ep.path }` }
					readOnly
					__nextHasNoMarginBottom
				/>
			),
		} ) );
	}, [ meta.baseUrl ] );

	// ── Combined form layout ──

	const allFields = useMemo(
		() => [ ...authFields, ...provisioningFields, ...endpointFields ],
		[ authFields, provisioningFields, endpointFields ]
	);

	const formLayout = useMemo( () => ( {
		fields: [
			{
				id: 'auth-section',
				label: __( 'Authentication', 'wppack-scim' ),
				children: authFields.map( ( f ) => f.id ),
				layout: { type: 'regular' },
			},
			{
				id: 'provisioning-section',
				label: __( 'Provisioning', 'wppack-scim' ),
				children: provisioningFields.map( ( f ) => f.id ),
				layout: { type: 'regular' },
			},
			{
				id: 'endpoints-section',
				label: __( 'SCIM Endpoints', 'wppack-scim' ),
				children: endpointFields.map( ( f ) => f.id ),
				layout: { type: 'regular' },
			},
		],
	} ), [ authFields, provisioningFields, endpointFields ] );

	const handleFormChange = ( edits ) => {
		setFormData( ( prev ) => ( { ...prev, ...edits } ) );
	};

	if ( loading ) {
		return <div className="wpp-scim-loading"><Spinner /></div>;
	}

	return (
		<Page title={ __( 'SCIM Settings', 'wppack-scim' ) } hasPadding>
			<div className="wpp-scim-settings">
				{ notice && (
					<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) }>
						{ notice.message }
					</Notice>
				) }

				<div className="wpp-scim-dataform-wrap">
					<DataForm
						data={ formData }
						fields={ allFields }
						form={ formLayout }
						onChange={ handleFormChange }
					/>
				</div>

				<div className="wpp-scim-actions">
					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ saving }
						disabled={ saving }
					>
						{ saving ? __( 'Saving…', 'wppack-scim' ) : __( 'Save Settings', 'wppack-scim' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
