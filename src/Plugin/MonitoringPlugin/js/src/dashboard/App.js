import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice, Panel, PanelBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import MetricCard from './components/MetricCard';
import './style.css';

export default function App() {
	const [ metrics, setMetrics ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ refreshing, setRefreshing ] = useState( false );

	const fetchMetrics = async ( force = false ) => {
		try {
			if ( force ) {
				setRefreshing( true );
			} else {
				setLoading( true );
			}

			const endpoint = force ? '/refresh' : '/metrics?period=3';
			const method = force ? 'POST' : 'GET';

			const data = await apiFetch( {
				path: `wppack/v1/monitoring${ endpoint }`,
				method,
			} );

			setMetrics( data );
			setError( null );
		} catch ( err ) {
			setError( err.message || 'Failed to fetch metrics' );
		} finally {
			setLoading( false );
			setRefreshing( false );
		}
	};

	useEffect( () => {
		fetchMetrics();
	}, [] );

	// Group results by group field
	const groups = {};
	if ( metrics?.results ) {
		metrics.results.forEach( ( r ) => {
			if ( ! groups[ r.group ] ) {
				groups[ r.group ] = [];
			}
			groups[ r.group ].push( r );
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
			<div className="wpp-monitoring-header">
				<h1>Infrastructure Monitoring</h1>
				<Button
					variant="secondary"
					isBusy={ refreshing }
					onClick={ () => fetchMetrics( true ) }
					disabled={ refreshing }
				>
					{ refreshing ? 'Refreshing...' : 'Refresh' }
				</Button>
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ metrics?.results?.length === 0 && (
				<Notice status="warning" isDismissible={ false }>
					No metric sources registered. Configure monitoring in your
					plugin settings.
				</Notice>
			) }

			{ Object.entries( groups ).map( ( [ group, results ] ) => (
				<Panel key={ group }>
					<PanelBody
						title={
							group.charAt( 0 ).toUpperCase() + group.slice( 1 )
						}
						initialOpen={ true }
					>
						<div className="wpp-monitoring-grid">
							{ results.map( ( result ) => (
								<MetricCard
									key={ result.sourceId }
									result={ result }
								/>
							) ) }
						</div>
					</PanelBody>
				</Panel>
			) ) }
		</div>
	);
}
