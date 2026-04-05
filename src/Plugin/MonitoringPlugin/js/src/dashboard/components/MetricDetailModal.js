import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, ButtonGroup, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import DetailChart from './DetailChart';

const PERIODS = [
	{ label: '1h', value: 1 },
	{ label: '3h', value: 3 },
	{ label: '6h', value: 6 },
	{ label: '12h', value: 12 },
	{ label: '1d', value: 24 },
	{ label: '3d', value: 72 },
	{ label: '7d', value: 168 },
];

export default function MetricDetailModal( { metric, result, initialPeriod, onClose } ) {
	const label = __( metric.label, 'wppack-monitoring' );
	const description = metric.description ? __( metric.description, 'wppack-monitoring' ) : '';

	const [ period, setPeriod ] = useState( initialPeriod || 3 );
	const [ currentResult, setCurrentResult ] = useState( result );
	const [ fetching, setFetching ] = useState( false );

	useEffect( () => {
		if ( period === initialPeriod ) {
			setCurrentResult( result );
			return;
		}

		setFetching( true );
		apiFetch( {
			path: `wppack/v1/monitoring/metrics?period=${ period }`,
		} )
			.then( ( data ) => {
				const matched = ( data.results || [] ).find(
					( r ) => r.sourceId === metric.id
				);
				if ( matched ) {
					setCurrentResult( matched );
				}
			} )
			.finally( () => setFetching( false ) );
	}, [ period ] );

	const datapoints = currentResult?.datapoints || [];
	const error = currentResult?.error;
	const stats = computeStats( datapoints );

	return (
		<Modal
			title={ label }
			onRequestClose={ onClose }
			size="large"
			className="wpp-monitoring-detail-modal"
		>
			<div className="wpp-monitoring-detail-period">
				<ButtonGroup>
					{ PERIODS.map( ( p ) => (
						<Button
							key={ p.value }
							variant={ period === p.value ? 'primary' : 'secondary' }
							size="small"
							onClick={ () => setPeriod( p.value ) }
						>
							{ p.label }
						</Button>
					) ) }
				</ButtonGroup>
			</div>

			{ error ? (
				<div className="wpp-monitoring-detail-error">{ error }</div>
			) : (
				<div className={ fetching ? 'wpp-monitoring-detail-body is-refreshing' : 'wpp-monitoring-detail-body' }>
					<DetailChart datapoints={ datapoints } unit={ metric.unit } />

					<div className="wpp-monitoring-detail-stats">
						<StatBox label={ __( 'Latest', 'wppack-monitoring' ) } value={ stats.latest } unit={ metric.unit } />
						<StatBox label={ __( 'Average', 'wppack-monitoring' ) } value={ stats.avg } unit={ metric.unit } />
						<StatBox label={ __( 'Min', 'wppack-monitoring' ) } value={ stats.min } unit={ metric.unit } />
						<StatBox label={ __( 'Max', 'wppack-monitoring' ) } value={ stats.max } unit={ metric.unit } />
						<StatBox label={ __( 'Data Points', 'wppack-monitoring' ) } value={ stats.count } />
					</div>

					<div className="wpp-monitoring-detail-meta">
						<span>{ __( 'Stat', 'wppack-monitoring' ) }: { metric.stat }</span>
						<span>{ __( 'Unit', 'wppack-monitoring' ) }: { metric.unit }</span>
						{ metric.namespace && <span>{ __( 'Namespace', 'wppack-monitoring' ) }: { metric.namespace }</span> }
						{ metric.metricName && <span>{ __( 'Metric', 'wppack-monitoring' ) }: { metric.metricName }</span> }
					</div>
					{ description && <p className="wpp-monitoring-detail-desc">{ description }</p> }
				</div>
			) }
		</Modal>
	);
}

function StatBox( { label, value, unit } ) {
	const formatted = unit ? formatStatValue( value, unit ) : String( value );
	return (
		<div className="wpp-monitoring-stat-box">
			<div className="wpp-monitoring-stat-label">{ label }</div>
			<div className="wpp-monitoring-stat-value">{ formatted }</div>
		</div>
	);
}

function formatStatValue( value, unit ) {
	if ( value === null || value === undefined ) return '\u2014';
	if ( unit === 'Percent' ) return value.toFixed( 2 ) + '%';
	if ( unit === 'Bytes' ) {
		if ( value >= 1_099_511_627_776 ) return ( value / 1_099_511_627_776 ).toFixed( 2 ) + ' TB';
		if ( value >= 1_073_741_824 ) return ( value / 1_073_741_824 ).toFixed( 2 ) + ' GB';
		if ( value >= 1_048_576 ) return ( value / 1_048_576 ).toFixed( 2 ) + ' MB';
		if ( value >= 1024 ) return ( value / 1024 ).toFixed( 2 ) + ' KB';
		return value.toFixed( 0 ) + ' B';
	}
	if ( unit === 'Milliseconds' ) return value.toFixed( 2 ) + ' ms';
	if ( unit === 'Seconds' ) return value.toFixed( 4 ) + ' s';
	return value.toLocaleString( undefined, { maximumFractionDigits: 2 } );
}

function computeStats( datapoints ) {
	if ( ! datapoints || datapoints.length === 0 ) {
		return { latest: null, avg: null, min: null, max: null, count: 0 };
	}
	const values = datapoints.map( ( dp ) => dp.value );
	const sum = values.reduce( ( a, b ) => a + b, 0 );
	return {
		latest: values[ values.length - 1 ],
		avg: sum / values.length,
		min: Math.min( ...values ),
		max: Math.max( ...values ),
		count: datapoints.length,
	};
}
