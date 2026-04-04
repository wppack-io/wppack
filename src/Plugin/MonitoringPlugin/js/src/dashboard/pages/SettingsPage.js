import { DataViews, DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice, Modal, Icon, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { lock, copy } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { METRIC_TEMPLATES } from '../data/templates';

// Bridge metadata from PHP (via wp_localize_script)
const BRIDGE_DATA = window.wppMonitoring?.bridges ?? {};

// Build BRIDGE_OPTIONS dynamically from available bridges
const BRIDGE_OPTIONS = Object.entries( BRIDGE_DATA ).map( ( [ name, meta ] ) => ( {
	value: name,
	label: __( meta.label, 'wppack-monitoring' ),
} ) );

// Merge dimension labels from all bridges
const DIMENSION_LABELS = Object.assign(
	{}, ...Object.values( BRIDGE_DATA ).map( ( m ) => {
		const labels = {};
		for ( const [ key, label ] of Object.entries( m.dimensionLabels || {} ) ) {
			labels[ key ] = __( label, 'wppack-monitoring' );
		}
		return labels;
	} )
);

// Get bridge metadata by name.
// Mock bridges (empty formFields) inherit from the first real bridge.
function getBridge( name ) {
	const bridge = BRIDGE_DATA[ name ];
	if ( bridge && bridge.formFields?.length > 0 ) {
		return bridge;
	}
	for ( const meta of Object.values( BRIDGE_DATA ) ) {
		if ( meta.formFields?.length > 0 ) {
			return { ...meta, ...( bridge ? { name: bridge.name, label: bridge.label } : {} ) };
		}
	}
	return bridge || {};
}

// Build DataForm-compatible credential fields from bridge metadata
function buildCredentialFields( bridgeMeta ) {
	return ( bridgeMeta.formFields || [] ).map( ( f ) => {
		const settingsKey = f.id.replace( 'settings.', '' );
		const field = {
			id: f.id,
			label: __( f.label, 'wppack-monitoring' ),
			type: f.type || 'text',
			getValue: ( { item } ) => item.settings?.[ settingsKey ] || '',
			setValue: ( value ) => ( { settings: { [ settingsKey ]: value } } ),
		};
		if ( f.description ) {
			field.description = __( f.description, 'wppack-monitoring' );
		}
		if ( f.elements ) {
			field.elements = f.elements;
		}
		return field;
	} );
}

// Bridges that have setup guides
const GUIDE_BRIDGES = Object.entries( BRIDGE_DATA )
	.filter( ( [ , meta ] ) => meta.setupGuide )
	.map( ( [ name, meta ] ) => ( { name, ...meta.setupGuide } ) );

const PROVIDER_FIELDS = [
	{
		id: 'label',
		label: __( 'Label', 'wppack-monitoring' ),
		type: 'text',
		enableGlobalSearch: true,
	},
	{
		id: 'bridge',
		label: __( 'Provider', 'wppack-monitoring' ),
		type: 'text',
		render: ( { item } ) =>
			BRIDGE_OPTIONS.find( ( b ) => b.value === item.bridge )?.label || item.bridge,
	},
	{
		id: 'settings.region',
		label: __( 'Region', 'wppack-monitoring' ),
		type: 'text',
		getValue: ( { item } ) => item.settings?.region || '\u2014',
	},
	{
		id: 'locked',
		label: __( 'Source', 'wppack-monitoring' ),
		type: 'text',
		render: ( { item } ) =>
			item.locked ? (
				<span className="wpp-monitoring-source-badge">
					<Icon icon={ lock } size={ 14 } /> { __( 'Plugin', 'wppack-monitoring' ) }
				</span>
			) : (
				__( 'Custom', 'wppack-monitoring' )
			),
	},
	{
		id: 'metricsCount',
		label: __( 'Metrics', 'wppack-monitoring' ),
		type: 'integer',
		enableSorting: true,
		getValue: ( { item } ) => item.metrics?.length || 0,
	},
];

const COMMON_FORM_FIELDS = [
	{ id: 'label', label: __( 'Label', 'wppack-monitoring' ), type: 'text' },
	{
		id: 'bridge',
		label: __( 'Provider', 'wppack-monitoring' ),
		type: 'text',
		Edit: ( { data, field } ) => (
			<TextControl
				id={ field.id }
				label={ field.label }
				value={ BRIDGE_OPTIONS.find( ( b ) => b.value === data.bridge )?.label || data.bridge }
				disabled
				__nextHasNoMarginBottom
			/>
		),
	},
];

function getDimensionFields( provider ) {
	const dims = provider.metrics?.[ 0 ]?.dimensions;
	if ( ! dims || Object.keys( dims ).length === 0 ) {
		return [];
	}
	return Object.entries( dims ).map( ( [ key, value ] ) => ( {
		id: `dim.${ key }`,
		label: DIMENSION_LABELS[ key ] || key,
		type: 'text',
		getValue: () => value,
		Edit: ( { field } ) => (
			<TextControl
				id={ field.id }
				label={ field.label }
				value={ value }
				disabled
				__nextHasNoMarginBottom
			/>
		),
	} ) );
}

function getProviderFormFields( bridge, provider ) {
	const credentialFields = buildCredentialFields( getBridge( bridge ) );
	const dimensionFields = getDimensionFields( provider );
	return [ ...COMMON_FORM_FIELDS, ...credentialFields, ...dimensionFields ];
}

function getLockedFormFields( bridge, provider ) {
	const fields = getProviderFormFields( bridge, provider );
	return fields.map( ( field ) => {
		if ( field.Edit ) {
			return field;
		}
		return {
			...field,
			Edit: ( { data, field: f } ) => {
				let value = field.getValue
					? field.getValue( { item: data } )
					: data[ field.id ] ?? '';
				if ( field.elements ) {
					const el = field.elements.find( ( e ) => e.value === value );
					if ( el ) {
						value = el.label;
					}
				}
				return (
					<TextControl
						id={ f.id }
						label={ f.label }
						value={ String( value ) }
						disabled
						__nextHasNoMarginBottom
					/>
				);
			},
		};
	} );
}

function getProviderForm( bridge, provider ) {
	const meta = getBridge( bridge );
	const credentialChildren = meta.credentialFieldIds || [];
	const credentialLabel = meta.label
		? __( '%s Credentials', 'wppack-monitoring' ).replace( '%s', __( meta.label, 'wppack-monitoring' ) )
		: __( 'Credentials', 'wppack-monitoring' );

	const dims = provider?.metrics?.[ 0 ]?.dimensions;
	const dimensionIds = dims ? Object.keys( dims ).map( ( k ) => `dim.${ k }` ) : [];

	const sections = [
		{
			id: 'general',
			label: __( 'General', 'wppack-monitoring' ),
			children: [ 'label', 'bridge' ],
			layout: { type: 'regular' },
		},
		{
			id: 'credentials',
			label: credentialLabel,
			children: credentialChildren,
			layout: { type: 'regular' },
		},
	];

	if ( dimensionIds.length > 0 ) {
		sections.push( {
			id: 'dimensions',
			label: __( 'Dimensions', 'wppack-monitoring' ),
			children: dimensionIds,
			layout: { type: 'regular' },
		} );
	}

	return { fields: sections };
}

function getAddFormFields( template ) {
	const credentialFields = buildCredentialFields( getBridge( template.bridge ) );
	return [
		...COMMON_FORM_FIELDS,
		...credentialFields,
		{
			id: '_dimensionValue',
			label: template.dimensionLabel,
			type: 'text',
			description: template.dimensionPlaceholder || '',
			getValue: ( { item } ) => item._dimensionValue || '',
		},
	];
}

function getAddForm( template ) {
	const meta = getBridge( template.bridge );
	const credentialChildren = meta.credentialFieldIds || [];
	const credentialLabel = meta.label
		? __( '%s Credentials', 'wppack-monitoring' ).replace( '%s', __( meta.label, 'wppack-monitoring' ) )
		: __( 'Credentials', 'wppack-monitoring' );

	return {
		fields: [
			{
				id: 'general',
				label: __( 'General', 'wppack-monitoring' ),
				children: [ 'label', 'bridge' ],
				layout: { type: 'regular' },
			},
			{
				id: 'credentials',
				label: credentialLabel,
				children: credentialChildren,
				layout: { type: 'regular' },
			},
			{
				id: 'dimensions',
				label: __( 'Dimensions', 'wppack-monitoring' ),
				children: [ '_dimensionValue' ],
				layout: { type: 'regular' },
			},
		],
	};
}

export default function SettingsPage() {
	const [ providers, setProviders ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ selectedProvider, setSelectedProvider ] = useState( null );
	const [ addMode, setAddMode ] = useState( false );
	const [ selectedTemplate, setSelectedTemplate ] = useState( null );
	const [ activeGuide, setActiveGuide ] = useState( null );
	const [ syncing, setSyncing ] = useState( false );
	const [ syncNotice, setSyncNotice ] = useState( null );
	const [ view, setView ] = useState( {
		type: 'table',
		fields: [
			'label',
			'bridge',
			'settings.region',
			'locked',
			'metricsCount',
		],
		layout: { density: 'balanced' },
	} );

	const fetchProviders = async () => {
		try {
			setLoading( true );
			const result = await apiFetch( {
				path: 'wppack/v1/monitoring/providers',
			} );
			setProviders( result.providers || [] );
			setError( null );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		fetchProviders();
	}, [] );

	const handleSaveProvider = async ( provider ) => {
		const isNew = ! providers.find( ( p ) => p.id === provider.id );
		await apiFetch( {
			path: 'wppack/v1/monitoring/providers',
			method: isNew ? 'POST' : 'PUT',
			data: provider,
		} );
		await fetchProviders();
		setSelectedProvider( null );
	};

	const handleDeleteProvider = async ( id ) => {
		await apiFetch( {
			path: 'wppack/v1/monitoring/providers',
			method: 'DELETE',
			data: { id },
		} );
		await fetchProviders();
		setSelectedProvider( null );
	};

	const actions = [
		{
			id: 'view',
			label: __( 'View Details', 'wppack-monitoring' ),
			isPrimary: true,
			callback: ( items ) => {
				if ( items[ 0 ] ) {
					setSelectedProvider( items[ 0 ] );
				}
			},
		},
		{
			id: 'delete',
			label: __( 'Delete', 'wppack-monitoring' ),
			isPrimary: false,
			isEligible: ( item ) => ! item.locked,
			callback: async ( items ) => {
				for ( const item of items ) {
					await handleDeleteProvider( item.id );
				}
			},
		},
	];

	if ( loading ) {
		return (
			<div className="wpp-monitoring-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="wpp-monitoring-settings">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ syncNotice && (
				<Notice status={ syncNotice.type } isDismissible onDismiss={ () => setSyncNotice( null ) }>
					{ syncNotice.message }
				</Notice>
			) }

			<div className="wpp-monitoring-settings-header">
				{ GUIDE_BRIDGES.map( ( g ) => (
					<Button
						key={ g.name }
						variant="tertiary"
						onClick={ () => setActiveGuide( g.name ) }
						size="compact"
					>
						{ __( g.buttonLabel, 'wppack-monitoring' ) }
					</Button>
				) ) }
				<Button
					variant="secondary"
					onClick={ async () => {
						setSyncing( true );
						setSyncNotice( null );
						try {
							const result = await apiFetch( {
								path: 'wppack/v1/monitoring/sync-templates',
								method: 'POST',
							} );
							if ( result.updated > 0 ) {
								setSyncNotice( { type: 'success', message: result.updated + __( ' providers synced with templates.', 'wppack-monitoring' ) } );
								await fetchProviders();
							} else {
								setSyncNotice( { type: 'info', message: __( 'All providers are up to date.', 'wppack-monitoring' ) } );
							}
						} catch ( err ) {
							setSyncNotice( { type: 'error', message: err.message } );
						} finally {
							setSyncing( false );
						}
					} }
					isBusy={ syncing }
					disabled={ syncing }
					size="compact"
				>
					{ __( 'Sync Templates', 'wppack-monitoring' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => setAddMode( true ) }
					size="compact"
				>
					{ __( 'Add Provider', 'wppack-monitoring' ) }
				</Button>
			</div>

			<DataViews
				data={ providers }
				fields={ PROVIDER_FIELDS }
				view={ view }
				onChangeView={ setView }
				actions={ actions }
				getItemId={ ( item ) => item.id }
				isItemClickable={ () => true }
				onClickItem={ ( { item } ) => setSelectedProvider( item ) }
				defaultLayouts={ { table: {} } }
				paginationInfo={ {
					totalItems: providers.length,
					totalPages: 1,
				} }
			/>

			{ selectedProvider && ! addMode && (
				<Modal
					title={
						selectedProvider.locked
							? selectedProvider.label
							: selectedProvider.id
								? __( 'Edit Provider', 'wppack-monitoring' )
								: __( 'Add Provider', 'wppack-monitoring' )
					}
					onRequestClose={ () => setSelectedProvider( null ) }
					size="large"
				>
					{ selectedProvider.locked && (
						<Notice status="info" isDismissible={ false } className="wpp-monitoring-readonly-notice">
							{ __( 'This provider is managed by a plugin and cannot be edited.', 'wppack-monitoring' ) }
						</Notice>
					) }
					<div className="wpp-monitoring-dataform-wrap"><DataForm
						data={ selectedProvider }
						fields={ selectedProvider.locked
							? getLockedFormFields( selectedProvider.bridge, selectedProvider )
							: getProviderFormFields( selectedProvider.bridge, selectedProvider ) }
						form={ getProviderForm( selectedProvider.bridge, selectedProvider ) }
						onChange={ selectedProvider.locked
							? () => {}
							: ( edits ) => {
								setSelectedProvider( ( prev ) => {
									const next = { ...prev };
									for ( const [
										key,
										value,
									] of Object.entries( edits ) ) {
										if ( key === 'settings' ) {
											next.settings = {
												...next.settings,
												...value,
											};
										} else {
											next[ key ] = value;
										}
									}
									return next;
								} );
							} }
					/></div>


					{ /* Metrics table */ }
					{ selectedProvider.metrics?.length > 0 && (
						<div className="wpp-monitoring-metrics-list">
							<h3>{ __( 'Metrics', 'wppack-monitoring' ) }</h3>
							<table className="wpp-monitoring-modal-table">
								<thead>
									<tr>
										<th>{ __( 'Label', 'wppack-monitoring' ) }</th>
										<th>{ __( 'Description', 'wppack-monitoring' ) }</th>
										<th>{ __( 'Namespace', 'wppack-monitoring' ) }</th>
										<th>{ __( 'Metric', 'wppack-monitoring' ) }</th>
										<th>{ __( 'Stat', 'wppack-monitoring' ) }</th>
										<th>{ __( 'Unit', 'wppack-monitoring' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ selectedProvider.metrics.map( ( m ) => (
										<tr key={ m.id }>
											<td>{ __( m.label, 'wppack-monitoring' ) }</td>
											<td className="wpp-monitoring-table-desc">{ m.description ? __( m.description, 'wppack-monitoring' ) : '' }</td>
											<td>{ m.namespace }</td>
											<td>{ m.metricName }</td>
											<td>{ m.stat }</td>
											<td>{ m.unit }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }

					{ ! selectedProvider.locked && (
						<div className="wpp-monitoring-modal-actions">
							<Button
								variant="primary"
								onClick={ () =>
									handleSaveProvider( selectedProvider )
								}
							>
								{ __( 'Save', 'wppack-monitoring' ) }
							</Button>
							{ selectedProvider.id && (
								<Button
									isDestructive
									onClick={ () =>
										handleDeleteProvider(
											selectedProvider.id
										)
									}
								>
									{ __( 'Delete', 'wppack-monitoring' ) }
								</Button>
							) }
						</div>
					) }
				</Modal>
			) }

			{ addMode && (
				<Modal
					title={ __( 'Add Provider from Template', 'wppack-monitoring' ) }
					onRequestClose={ () => {
						setAddMode( false );
						setSelectedTemplate( null );
						setSelectedProvider( null );
					} }
					size="large"
				>
					<div className="wpp-monitoring-add-form">
					<SelectControl
						label={ __( 'Template', 'wppack-monitoring' ) }
						value={ selectedTemplate?.id || '' }
						onChange={ ( templateId ) => {
							const tmpl = METRIC_TEMPLATES.find( ( t ) => t.id === templateId );
							setSelectedTemplate( tmpl || null );
							if ( tmpl ) {
								setSelectedProvider( {
									id: '',
									label: tmpl.label,
									bridge: tmpl.bridge,
									settings: { ...( getBridge( tmpl.bridge ).defaultSettings || {} ) },
									metrics: tmpl.metrics.map( ( m ) => ( {
										id: `${ tmpl.id }.${ m.metricName.toLowerCase() }`,
										label: m.label,
										description: m.description,
										namespace: tmpl.namespace,
										metricName: m.metricName,
										unit: m.unit,
										stat: m.stat,
										dimensions: {},
										periodSeconds: 300,
										locked: false,
									} ) ),
									locked: false,
									_dimensionValue: '',
								} );
							} else {
								setSelectedProvider( null );
							}
						} }
					>
						<option value="">{ __( '\u2014 Select template \u2014', 'wppack-monitoring' ) }</option>
						{ Object.entries( BRIDGE_DATA )
							.filter( ( [ name, meta ] ) => ! name.startsWith( 'mock-' ) && meta.templates?.length > 0 )
							.map( ( [ bridgeName, meta ] ) => (
								<optgroup key={ bridgeName } label={ __( meta.label, 'wppack-monitoring' ) }>
									{ METRIC_TEMPLATES.filter( ( t ) => t.bridge === bridgeName ).map( ( t ) => (
										<option key={ t.id } value={ t.id }>{ t.label }</option>
									) ) }
								</optgroup>
							) ) }
					</SelectControl>

					{ selectedTemplate && selectedProvider && (
						<>
							<div className="wpp-monitoring-dataform-wrap"><DataForm
								data={ selectedProvider }
								fields={ getAddFormFields( selectedTemplate ) }
								form={ getAddForm( selectedTemplate ) }
								onChange={ ( edits ) => {
									setSelectedProvider( ( prev ) => {
										const next = { ...prev };
										for ( const [
											key,
											value,
										] of Object.entries( edits ) ) {
											if ( key === 'settings' ) {
												next.settings = {
													...next.settings,
													...value,
												};
											} else {
												next[ key ] = value;
											}
										}
										return next;
									} );
								} }
							/></div>

							{ selectedProvider.metrics?.length > 0 && (
								<div className="wpp-monitoring-metrics-list">
									<h3>{ __( 'Metrics', 'wppack-monitoring' ) }</h3>
									<table className="wpp-monitoring-modal-table">
										<thead>
											<tr>
												<th>{ __( 'Label', 'wppack-monitoring' ) }</th>
												<th>{ __( 'Description', 'wppack-monitoring' ) }</th>
												<th>{ __( 'Namespace', 'wppack-monitoring' ) }</th>
												<th>{ __( 'Metric', 'wppack-monitoring' ) }</th>
												<th>{ __( 'Stat', 'wppack-monitoring' ) }</th>
												<th>{ __( 'Unit', 'wppack-monitoring' ) }</th>
											</tr>
										</thead>
										<tbody>
											{ selectedProvider.metrics.map( ( m ) => (
												<tr key={ m.id }>
													<td>{ m.label }</td>
													<td className="wpp-monitoring-table-desc">{ m.description }</td>
													<td>{ m.namespace }</td>
													<td>{ m.metricName }</td>
													<td>{ m.stat }</td>
													<td>{ m.unit }</td>
												</tr>
											) ) }
										</tbody>
									</table>
								</div>
							) }

							<div className="wpp-monitoring-modal-actions">
								<Button
									variant="primary"
									disabled={ ! selectedProvider.label || ! selectedProvider._dimensionValue || (
										getBridge( selectedTemplate.bridge ).requiredFields || []
									).some( ( fid ) => ! selectedProvider.settings?.[ fid.replace( 'settings.', '' ) ] ) }
									onClick={ async () => {
										const dimVal = selectedProvider._dimensionValue;
										const provider = {
											...selectedProvider,
											id: selectedProvider.label.toLowerCase().replace( /[^a-z0-9]+/g, '-' ),
											templateId: selectedTemplate.id,
											metrics: selectedProvider.metrics.map( ( m, i ) => {
												const tmplMetric = selectedTemplate.metrics[ i ] || {};
												return {
													...m,
													dimensions: {
														...( dimVal ? { [ selectedTemplate.dimensionKey ]: dimVal } : {} ),
														...( tmplMetric.extraDimensions || {} ),
													},
													periodSeconds: tmplMetric.period || m.periodSeconds,
												};
											} ),
										};
										delete provider._dimensionValue;
										await handleSaveProvider( provider );
										setAddMode( false );
										setSelectedTemplate( null );
										setSelectedProvider( null );
									} }
								>
									{ __( 'Save', 'wppack-monitoring' ) }
								</Button>
							</div>
						</>
					) }
				</div>
				</Modal>
			) }
			{ activeGuide && ( () => {
				const guide = getBridge( activeGuide )?.setupGuide;
				if ( ! guide ) {
					return null;
				}
				return (
					<Modal
						title={ __( guide.title, 'wppack-monitoring' ) }
						onRequestClose={ () => setActiveGuide( null ) }
						size="medium"
					>
						{ ( guide.content || [] ).map( ( block, i ) => {
							switch ( block.type ) {
								case 'paragraph':
									return <p key={ i }>{ __( block.text, 'wppack-monitoring' ) }</p>;
								case 'note':
									return <p key={ i } className="wpp-monitoring-iam-note">{ __( block.text, 'wppack-monitoring' ) }</p>;
								case 'heading':
									return <h3 key={ i }>{ __( block.text, 'wppack-monitoring' ) }</h3>;
								case 'code':
									return (
										<div key={ i } className="wpp-monitoring-iam-block">
											<pre className="wpp-monitoring-iam-code">{ block.code }</pre>
											{ block.copyable && (
												<Button
													icon={ copy }
													label={ __( 'Copy', 'wppack-monitoring' ) }
													size="small"
													className="wpp-monitoring-iam-copy"
													onClick={ () => {
														navigator.clipboard.writeText( block.code );
													} }
												/>
											) }
										</div>
									);
								case 'steps':
									return (
										<ol key={ i } className="wpp-monitoring-cf-steps">
											{ block.items.map( ( item, j ) => (
												<li key={ j } dangerouslySetInnerHTML={ { __html: __( item, 'wppack-monitoring' ) } } />
											) ) }
										</ol>
									);
								default:
									return null;
							}
						} ) }
					</Modal>
				);
			} )() }
		</div>
	);
}
