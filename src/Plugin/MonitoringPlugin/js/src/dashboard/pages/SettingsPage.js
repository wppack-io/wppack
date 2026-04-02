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
			item.bridge === 'cloudwatch' ? __( 'CloudWatch', 'wppack-monitoring' ) : item.bridge,
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

const PROVIDER_FORM_FIELDS = [
	{ id: 'label', label: __( 'Label', 'wppack-monitoring' ), type: 'text' },
	{
		id: 'bridge',
		label: __( 'Provider', 'wppack-monitoring' ),
		type: 'text',
		elements: [ { value: 'cloudwatch', label: __( 'AWS CloudWatch', 'wppack-monitoring' ) } ],
	},
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

const PROVIDER_FORM = {
	fields: [
		{
			id: 'general',
			label: __( 'General', 'wppack-monitoring' ),
			children: [ 'label', 'bridge' ],
			layout: { type: 'regular' },
		},
		{
			id: 'aws',
			label: __( 'AWS Credentials', 'wppack-monitoring' ),
			children: [
				'settings.region',
				'settings.accessKeyId',
				'settings.secretAccessKey',
			],
			layout: { type: 'regular' },
		},
	],
};

export default function SettingsPage() {
	const [ providers, setProviders ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ selectedProvider, setSelectedProvider ] = useState( null );
	const [ addMode, setAddMode ] = useState( false );
	const [ selectedTemplate, setSelectedTemplate ] = useState( null );
	const [ dimensionValue, setDimensionValue ] = useState( '' );
	const [ showIam, setShowIam ] = useState( false );
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

			<div className="wpp-monitoring-settings-header">
				<Button
					variant="tertiary"
					onClick={ () => setShowIam( true ) }
					size="compact"
				>
					{ __( 'IAM Policy', 'wppack-monitoring' ) }
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
						<table className="wpp-monitoring-modal-table wpp-monitoring-detail-table">
							<tbody>
								<tr>
									<th>{ __( 'Label', 'wppack-monitoring' ) }</th>
									<td>{ selectedProvider.label }</td>
								</tr>
								<tr>
									<th>{ __( 'Provider', 'wppack-monitoring' ) }</th>
									<td>{ selectedProvider.bridge === 'cloudwatch' ? 'AWS CloudWatch' : selectedProvider.bridge }</td>
								</tr>
								<tr>
									<th>{ __( 'Region', 'wppack-monitoring' ) }</th>
									<td>{ selectedProvider.settings?.region || '\u2014' }</td>
								</tr>
							</tbody>
						</table>
					) : (
						<DataForm
							data={ selectedProvider }
							fields={ PROVIDER_FORM_FIELDS }
							form={ PROVIDER_FORM }
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
					) }

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
						setDimensionValue( '' );
						setSelectedProvider( null );
					} }
					size="large"
				>
					<SelectControl
						label={ __( 'Template', 'wppack-monitoring' ) }
						value={ selectedTemplate?.id || '' }
						onChange={ ( templateId ) => {
							const tmpl = METRIC_TEMPLATES.find( ( t ) => t.id === templateId );
							setSelectedTemplate( tmpl || null );
							setDimensionValue( '' );
							if ( tmpl ) {
								setSelectedProvider( {
									id: '',
									label: tmpl.label,
									bridge: tmpl.bridge,
									settings: { region: '', accessKeyId: '', secretAccessKey: '' },
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
							<TextControl
								label={ __( 'Label', 'wppack-monitoring' ) }
								value={ selectedProvider.label }
								onChange={ ( value ) =>
									setSelectedProvider( ( prev ) => ( { ...prev, label: value } ) )
								}
							/>
							<SelectControl
								label={ __( 'Region', 'wppack-monitoring' ) + ' *' }
								value={ selectedProvider.settings?.region || '' }
								options={ AWS_REGIONS }
								onChange={ ( value ) =>
									setSelectedProvider( ( prev ) => ( {
										...prev,
										settings: { ...prev.settings, region: value },
									} ) )
								}
							/>
							<TextControl
								label={ selectedTemplate.dimensionLabel + ' *' }
								value={ dimensionValue }
								placeholder={ selectedTemplate.dimensionPlaceholder || '' }
								help={ selectedTemplate.dimensionKey }
								onChange={ ( value ) => setDimensionValue( value ) }
							/>

							{ selectedProvider.metrics?.length > 0 && (
								<div className="wpp-monitoring-template-metrics">
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
									disabled={ ! selectedProvider.label || ! selectedProvider.settings?.region || ! dimensionValue }
									onClick={ async () => {
										const provider = {
											...selectedProvider,
											id: selectedProvider.label.toLowerCase().replace( /[^a-z0-9]+/g, '-' ),
											metrics: selectedProvider.metrics.map( ( m, i ) => {
												const tmplMetric = selectedTemplate.metrics[ i ] || {};
												return {
													...m,
													dimensions: {
														...( dimensionValue ? { [ selectedTemplate.dimensionKey ]: dimensionValue } : {} ),
														...( tmplMetric.extraDimensions || {} ),
													},
													periodSeconds: tmplMetric.period || m.periodSeconds,
												};
											} ),
										};
										await handleSaveProvider( provider );
										setAddMode( false );
										setSelectedTemplate( null );
										setDimensionValue( '' );
										setSelectedProvider( null );
									} }
								>
									{ __( 'Save', 'wppack-monitoring' ) }
								</Button>
							</div>
						</>
					) }
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
		</div>
	);
}
