import { DataViews, DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice, Modal, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { lock } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const PROVIDER_FIELDS = [
	{
		id: 'label',
		label: __( 'Label', 'wppack-monitoring' ),
		type: 'text',
		enableGlobalSearch: true,
	},
	{
		id: 'bridge',
		label: __( 'Bridge', 'wppack-monitoring' ),
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
		getValue: ( { item } ) => item.metrics?.length || 0,
		render: ( { item } ) => (
			<span className="wpp-monitoring-align-end">
				{ item.metrics?.length || 0 }
			</span>
		),
	},
];

const PROVIDER_FORM_FIELDS = [
	{ id: 'label', label: __( 'Label', 'wppack-monitoring' ), type: 'text' },
	{
		id: 'bridge',
		label: __( 'Bridge', 'wppack-monitoring' ),
		type: 'text',
		elements: [ { value: 'cloudwatch', label: __( 'AWS CloudWatch', 'wppack-monitoring' ) } ],
	},
	{
		id: 'settings.region',
		label: __( 'Region', 'wppack-monitoring' ),
		type: 'text',
		description: __( 'e.g., ap-northeast-1', 'wppack-monitoring' ),
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
			isPrimary: false,
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
					variant="primary"
					onClick={ () =>
						setSelectedProvider( {
							id: '',
							label: '',
							bridge: 'cloudwatch',
							settings: {
								region: '',
								accessKeyId: '',
								secretAccessKey: '',
							},
							metrics: [],
							locked: false,
						} )
					}
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

			{ selectedProvider && (
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
					<DataForm
						data={ selectedProvider }
						fields={
							selectedProvider.locked
								? PROVIDER_FORM_FIELDS.filter(
										( f ) =>
											f.id !== 'settings.accessKeyId' &&
											f.id !== 'settings.secretAccessKey'
									).map( ( f ) => ( {
										...f,
										readOnly: true,
									} ) )
								: PROVIDER_FORM_FIELDS
						}
						form={
							selectedProvider.locked
								? {
										fields: [
											{
												id: 'general',
												label: __( 'General', 'wppack-monitoring' ),
												children: [ 'label', 'bridge' ],
												layout: { type: 'regular' },
											},
											{
												id: 'aws',
												label: __( 'AWS Settings', 'wppack-monitoring' ),
												children: [ 'settings.region' ],
												layout: { type: 'regular' },
											},
										],
									}
								: PROVIDER_FORM
						}
						onChange={
							selectedProvider.locked
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
									}
						}
					/>

					{ /* Metrics table */ }
					{ selectedProvider.metrics?.length > 0 && (
						<div className="wpp-monitoring-metrics-list">
							<h3>{ __( 'Metrics', 'wppack-monitoring' ) }</h3>
							<table className="widefat striped">
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
		</div>
	);
}
