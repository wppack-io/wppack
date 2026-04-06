import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ProviderSection from '../components/ProviderSection';
import PeriodSelector from '../components/PeriodSelector';
import MetricDetailModal from '../components/MetricDetailModal';

export default function DashboardPage() {
	const [ data, setData ] = useState( null );
	const [ period, setPeriodState ] = useState( () => {
		const saved = localStorage.getItem( 'wppack_monitoring_period' );
		return saved ? Number( saved ) : 3;
	} );
	const setPeriod = ( v ) => {
		setPeriodState( v );
		localStorage.setItem( 'wppack_monitoring_period', String( v ) );
	};
	const [ loading, setLoading ] = useState( true );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ selectedMetric, setSelectedMetric ] = useState( null );
	const isInitial = useRef( true );

	const fetchMetrics = useCallback(
		async ( force = false ) => {
			try {
				if ( isInitial.current ) {
					setLoading( true );
				} else {
					setRefreshing( true );
				}
				const path = force
					? `wppack/v1/monitoring/refresh?period=${ period }`
					: `wppack/v1/monitoring/metrics?period=${ period }`;
				const method = force ? 'POST' : 'GET';
				const result = await apiFetch( { path, method } );
				setData( result );
				setError( null );
			} catch ( err ) {
				setError( err.message || __( 'Failed to fetch metrics', 'wppack-monitoring' ) );
			} finally {
				setLoading( false );
				setRefreshing( false );
				isInitial.current = false;
			}
		},
		[ period ]
	);

	useEffect( () => {
		fetchMetrics();
	}, [ fetchMetrics ] );

	// Group results by provider
	const resultsByProvider = {};
	if ( data?.results ) {
		data.results.forEach( ( r ) => {
			if ( ! resultsByProvider[ r.group ] ) {
				resultsByProvider[ r.group ] = [];
			}
			resultsByProvider[ r.group ].push( r );
		} );
	}

	if ( loading ) {
		return (
			<div className="wpp-monitoring-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="wpp-monitoring-dashboard">
			<div className="wpp-monitoring-toolbar">
				<PeriodSelector value={ period } onChange={ setPeriod } />
				<div className="wpp-monitoring-toolbar-right">
					{ data?.results?.[ 0 ]?.fetchedAt && (
						<span className="wpp-monitoring-last-updated">
							{ __( 'Last updated:', 'wppack-monitoring' ) } { new Date( data.results[ 0 ].fetchedAt ).toLocaleString( [], { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' } ) }
						</span>
					) }
					<Button
						variant="secondary"
						isBusy={ refreshing }
						onClick={ () => fetchMetrics( true ) }
						disabled={ refreshing }
						size="compact"
					>
						{ refreshing ? __( 'Refreshing\u2026', 'wppack-monitoring' ) : __( 'Refresh', 'wppack-monitoring' ) }
					</Button>
				</div>
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ data?.providers?.length === 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'No monitoring providers configured. Go to Settings to add one.', 'wppack-monitoring' ) }
				</Notice>
			) }

			<div className={ refreshing ? 'wpp-monitoring-content is-refreshing' : 'wpp-monitoring-content' }>
				{ data?.providers?.map( ( provider ) => (
					<ProviderSection
						key={ provider.id }
						provider={ provider }
						results={ resultsByProvider[ provider.id ] || [] }
						onSelectMetric={ ( metric, result ) => setSelectedMetric( { metric, result } ) }
					/>
				) ) }
			</div>

			{ selectedMetric && (
				<MetricDetailModal
					metric={ selectedMetric.metric }
					result={ selectedMetric.result }
					initialPeriod={ period }
					onClose={ () => setSelectedMetric( null ) }
				/>
			) }
		</div>
	);
}
