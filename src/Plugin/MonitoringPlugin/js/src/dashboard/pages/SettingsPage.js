import { DataViews, DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice, Modal, Icon, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { lock, copy } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { METRIC_TEMPLATES } from '../data/templates';

const AWS_REGIONS = [
	{ value: '', label: '\u2014' },
	{ value: 'us-east-1', label: 'us-east-1 \u2014 US East (N. Virginia)' },
	{ value: 'us-east-2', label: 'us-east-2 \u2014 US East (Ohio)' },
	{ value: 'us-west-1', label: 'us-west-1 \u2014 US West (N. California)' },
	{ value: 'us-west-2', label: 'us-west-2 \u2014 US West (Oregon)' },
	{ value: 'us-gov-east-1', label: 'us-gov-east-1 \u2014 AWS GovCloud (US-East)' },
	{ value: 'us-gov-west-1', label: 'us-gov-west-1 \u2014 AWS GovCloud (US-West)' },
	{ value: 'af-south-1', label: 'af-south-1 \u2014 Africa (Cape Town)' },
	{ value: 'ap-east-1', label: 'ap-east-1 \u2014 Asia Pacific (Hong Kong)' },
	{ value: 'ap-east-2', label: 'ap-east-2 \u2014 Asia Pacific (Taipei)' },
	{ value: 'ap-south-1', label: 'ap-south-1 \u2014 Asia Pacific (Mumbai)' },
	{ value: 'ap-south-2', label: 'ap-south-2 \u2014 Asia Pacific (Hyderabad)' },
	{ value: 'ap-southeast-1', label: 'ap-southeast-1 \u2014 Asia Pacific (Singapore)' },
	{ value: 'ap-southeast-2', label: 'ap-southeast-2 \u2014 Asia Pacific (Sydney)' },
	{ value: 'ap-southeast-3', label: 'ap-southeast-3 \u2014 Asia Pacific (Jakarta)' },
	{ value: 'ap-southeast-4', label: 'ap-southeast-4 \u2014 Asia Pacific (Melbourne)' },
	{ value: 'ap-southeast-5', label: 'ap-southeast-5 \u2014 Asia Pacific (Malaysia)' },
	{ value: 'ap-southeast-6', label: 'ap-southeast-6 \u2014 Asia Pacific (New Zealand)' },
	{ value: 'ap-southeast-7', label: 'ap-southeast-7 \u2014 Asia Pacific (Thailand)' },
	{ value: 'ap-northeast-1', label: 'ap-northeast-1 \u2014 Asia Pacific (Tokyo)' },
	{ value: 'ap-northeast-2', label: 'ap-northeast-2 \u2014 Asia Pacific (Seoul)' },
	{ value: 'ap-northeast-3', label: 'ap-northeast-3 \u2014 Asia Pacific (Osaka)' },
	{ value: 'ca-central-1', label: 'ca-central-1 \u2014 Canada (Central)' },
	{ value: 'ca-west-1', label: 'ca-west-1 \u2014 Canada West (Calgary)' },
	{ value: 'cn-north-1', label: 'cn-north-1 \u2014 China (Beijing)' },
	{ value: 'cn-northwest-1', label: 'cn-northwest-1 \u2014 China (Ningxia)' },
	{ value: 'eu-central-1', label: 'eu-central-1 \u2014 Europe (Frankfurt)' },
	{ value: 'eu-central-2', label: 'eu-central-2 \u2014 Europe (Zurich)' },
	{ value: 'eu-west-1', label: 'eu-west-1 \u2014 Europe (Ireland)' },
	{ value: 'eu-west-2', label: 'eu-west-2 \u2014 Europe (London)' },
	{ value: 'eu-west-3', label: 'eu-west-3 \u2014 Europe (Paris)' },
	{ value: 'eu-south-1', label: 'eu-south-1 \u2014 Europe (Milan)' },
	{ value: 'eu-south-2', label: 'eu-south-2 \u2014 Europe (Spain)' },
	{ value: 'eu-north-1', label: 'eu-north-1 \u2014 Europe (Stockholm)' },
	{ value: 'eusc-de-east-1', label: 'eusc-de-east-1 \u2014 European Sovereign Cloud (Germany)' },
	{ value: 'il-central-1', label: 'il-central-1 \u2014 Israel (Tel Aviv)' },
	{ value: 'mx-central-1', label: 'mx-central-1 \u2014 Mexico (Central)' },
	{ value: 'me-south-1', label: 'me-south-1 \u2014 Middle East (Bahrain)' },
	{ value: 'me-central-1', label: 'me-central-1 \u2014 Middle East (UAE)' },
	{ value: 'sa-east-1', label: 'sa-east-1 \u2014 South America (São Paulo)' },
];

const IAM_POLICY_JSON = `{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "WpPackMonitoring",
      "Effect": "Allow",
      "Action": "cloudwatch:GetMetricData",
      "Resource": "*"
    }
  ]
}`;

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

const BRIDGE_OPTIONS = [
	{ value: 'cloudwatch', label: __( 'AWS CloudWatch', 'wppack-monitoring' ) },
	{ value: 'cloudflare', label: __( 'Cloudflare', 'wppack-monitoring' ) },
	{ value: 'mock-aws', label: __( 'Mock (AWS)', 'wppack-monitoring' ) },
	{ value: 'mock-cloudflare', label: __( 'Mock (Cloudflare)', 'wppack-monitoring' ) },
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

const AWS_FORM_FIELDS = [
	{
		id: 'settings.region',
		label: __( 'Region', 'wppack-monitoring' ),
		type: 'text',
		elements: AWS_REGIONS.filter( ( r ) => r.value !== '' ),
		getValue: ( { item } ) => item.settings?.region || '',
		setValue: ( value ) => ( { settings: { region: value } } ),
	},
	{
		id: 'settings.accessKeyId',
		label: __( 'Access Key ID', 'wppack-monitoring' ),
		type: 'text',
		description: __( 'Optional \u2014 falls back to IAM role', 'wppack-monitoring' ),
		getValue: ( { item } ) => item.settings?.accessKeyId || '',
		setValue: ( value ) => ( { settings: { accessKeyId: value } } ),
	},
	{
		id: 'settings.secretAccessKey',
		label: __( 'Secret Access Key', 'wppack-monitoring' ),
		type: 'password',
		description: __( 'Optional \u2014 falls back to IAM role', 'wppack-monitoring' ),
		getValue: ( { item } ) => item.settings?.secretAccessKey || '',
		setValue: ( value ) => ( { settings: { secretAccessKey: value } } ),
	},
];

const CLOUDFLARE_FORM_FIELDS = [
	{
		id: 'settings.apiToken',
		label: __( 'API Token', 'wppack-monitoring' ),
		type: 'password',
		description: __( 'Cloudflare API Token with Zone Analytics permission', 'wppack-monitoring' ),
		getValue: ( { item } ) => item.settings?.apiToken || '',
		setValue: ( value ) => ( { settings: { apiToken: value } } ),
	},
];

function getDimensionFields( provider ) {
	const dims = provider.metrics?.[ 0 ]?.dimensions;
	if ( ! dims || Object.keys( dims ).length === 0 ) {
		return [];
	}
	return Object.entries( dims ).map( ( [ key, value ] ) => ( {
		id: `dim.${ key }`,
		label: key,
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
	const credentialFields = bridge === 'cloudflare' ? CLOUDFLARE_FORM_FIELDS : AWS_FORM_FIELDS;
	const dimensionFields = getDimensionFields( provider );
	return [ ...COMMON_FORM_FIELDS, ...credentialFields, ...dimensionFields ];
}

function getProviderForm( bridge, provider ) {
	const credentialChildren = bridge === 'cloudflare'
		? [ 'settings.apiToken' ]
		: [ 'settings.region', 'settings.accessKeyId', 'settings.secretAccessKey' ];

	const credentialLabel = bridge === 'cloudflare'
		? __( 'Cloudflare Credentials', 'wppack-monitoring' )
		: __( 'AWS Credentials', 'wppack-monitoring' );

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
	const credentialFields = template.bridge === 'cloudflare'
		? CLOUDFLARE_FORM_FIELDS
		: AWS_FORM_FIELDS;
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
	const credentialChildren = template.bridge === 'cloudflare'
		? [ 'settings.apiToken' ]
		: [ 'settings.region', 'settings.accessKeyId', 'settings.secretAccessKey' ];

	const credentialLabel = template.bridge === 'cloudflare'
		? __( 'Cloudflare Credentials', 'wppack-monitoring' )
		: __( 'AWS Credentials', 'wppack-monitoring' );

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
	const [ showIam, setShowIam ] = useState( false );
	const [ showCloudflare, setShowCloudflare ] = useState( false );
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
				<Button
					variant="tertiary"
					onClick={ () => setShowIam( true ) }
					size="compact"
				>
					{ __( 'AWS IAM Policy', 'wppack-monitoring' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ () => setShowCloudflare( true ) }
					size="compact"
				>
					{ __( 'Cloudflare API Token', 'wppack-monitoring' ) }
				</Button>
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
					{ selectedProvider.locked ? (
						<div>
							<TextControl
								label={ __( 'Label', 'wppack-monitoring' ) }
								value={ selectedProvider.label }
								disabled
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Provider', 'wppack-monitoring' ) }
								value={ BRIDGE_OPTIONS.find( ( b ) => b.value === selectedProvider.bridge )?.label || selectedProvider.bridge }
								disabled
								__nextHasNoMarginBottom
							/>
							{ selectedProvider.settings?.region && (
								<TextControl
									label={ __( 'Region', 'wppack-monitoring' ) }
									value={ selectedProvider.settings.region }
									disabled
									__nextHasNoMarginBottom
								/>
							) }
						</div>
					) : (
						<div className="wpp-monitoring-dataform-wrap"><DataForm
							data={ selectedProvider }
							fields={ getProviderFormFields( selectedProvider.bridge, selectedProvider ) }
							form={ getProviderForm( selectedProvider.bridge, selectedProvider ) }
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
						/>
					</div>) }


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
									settings: tmpl.bridge === 'cloudflare'
										? { apiToken: '' }
										: { region: '', accessKeyId: '', secretAccessKey: '' },
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
						options={ [
							{ value: '', label: __( '\u2014 Select template \u2014', 'wppack-monitoring' ) },
							...METRIC_TEMPLATES.map( ( t ) => ( { value: t.id, label: t.label } ) ),
						] }
					/>

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
										selectedTemplate.bridge === 'cloudflare'
											? ! selectedProvider.settings?.apiToken
											: ! selectedProvider.settings?.region
									) }
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
			{ showIam && (
				<Modal
					title={ __( 'IAM Policy', 'wppack-monitoring' ) }
					onRequestClose={ () => setShowIam( false ) }
					size="medium"
				>
					<p>
						{ __( 'The following IAM permission is required for metric data retrieval.', 'wppack-monitoring' ) }
					</p>
					<p className="wpp-monitoring-iam-note">
						{ __( 'Note: cloudwatch:GetMetricData does not support resource-level restrictions. Resource must be "*" per AWS specification.', 'wppack-monitoring' ) }
					</p>
					<div className="wpp-monitoring-iam-block">
						<pre className="wpp-monitoring-iam-code">{ IAM_POLICY_JSON }</pre>
						<Button
							icon={ copy }
							label={ __( 'Copy', 'wppack-monitoring' ) }
							size="small"
							className="wpp-monitoring-iam-copy"
							onClick={ () => {
								navigator.clipboard.writeText( IAM_POLICY_JSON );
							} }
						/>
					</div>
				</Modal>
			) }
			{ showCloudflare && (
				<Modal
					title={ __( 'Cloudflare API Token Setup', 'wppack-monitoring' ) }
					onRequestClose={ () => setShowCloudflare( false ) }
					size="medium"
				>
					<p>
						{ __( 'Cloudflare analytics data is retrieved via API Token. We recommend creating an Account API Token, which allows monitoring all zones in the account with a single token.', 'wppack-monitoring' ) }
					</p>

					<h3>{ __( 'Creating an Account API Token (Recommended)', 'wppack-monitoring' ) }</h3>
					<ol className="wpp-monitoring-cf-steps">
						<li>
							{ __( 'Go to the Cloudflare dashboard and navigate to', 'wppack-monitoring' ) }
							{ ' ' }
							<strong>My Profile &rarr; API Tokens</strong>
						</li>
						<li>
							{ __( 'Click "Create Token"', 'wppack-monitoring' ) }
						</li>
						<li>
							{ __( 'Select "Create Custom Token"', 'wppack-monitoring' ) }
						</li>
						<li>
							{ __( 'Set the following permissions:', 'wppack-monitoring' ) }
							<div className="wpp-monitoring-iam-block">
								<pre className="wpp-monitoring-iam-code">{ 'Account \u2014 Account Analytics \u2014 Read\nZone   \u2014 Analytics         \u2014 Read' }</pre>
							</div>
						</li>
						<li>
							{ __( 'Under "Account Resources", select the target account', 'wppack-monitoring' ) }
						</li>
						<li>
							{ __( 'Under "Zone Resources", select "All zones" (or specific zones)', 'wppack-monitoring' ) }
						</li>
						<li>
							{ __( 'Click "Continue to summary", then "Create Token"', 'wppack-monitoring' ) }
						</li>
						<li>
							{ __( 'Copy the token and paste it into the API Token field when adding a Cloudflare provider', 'wppack-monitoring' ) }
						</li>
					</ol>
					<p className="wpp-monitoring-iam-note">
						{ __( 'Tip: A single API Token can be reused across multiple Cloudflare providers (Zone analytics, WAF, etc.) as long as it has the required permissions.', 'wppack-monitoring' ) }
					</p>

					<h3>{ __( 'Finding Your Zone ID', 'wppack-monitoring' ) }</h3>
					<p>
						{ __( 'The Zone ID is shown on the right sidebar of your domain\'s Overview page in the Cloudflare dashboard, under the "API" section.', 'wppack-monitoring' ) }
					</p>
				</Modal>
			) }
		</div>
	);
}
