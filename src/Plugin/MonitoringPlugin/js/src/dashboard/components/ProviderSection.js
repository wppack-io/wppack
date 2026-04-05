import { Card, CardHeader, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import MetricCard from './MetricCard';

export default function ProviderSection( { provider, results, onSelectMetric } ) {
	// Build dimension summary for header
	const dimensions =
		provider.metrics.length > 0
			? Object.entries( provider.metrics[ 0 ].dimensions || {} )
					.map( ( [ k, v ] ) => `${ k }: ${ v }` )
					.join( ' \u00B7 ' )
			: '';

	const subtitle = [ provider.settings?.region, dimensions ]
		.filter( Boolean )
		.join( ' \u00B7 ' );

	// Map metric results by sourceId
	const resultMap = {};
	results.forEach( ( r ) => {
		resultMap[ r.sourceId ] = r;
	} );

	return (
		<Card className="wpp-monitoring-provider" size="small">
			<CardHeader>
				<div className="wpp-monitoring-provider-header">
					<div className="wpp-monitoring-provider-title">
						{ provider.label }
					</div>
					{ subtitle && (
						<div className="wpp-monitoring-provider-subtitle">
							{ subtitle }
						</div>
					) }
				</div>
				{ provider.locked && (
					<span className="wpp-monitoring-badge">{ __( 'Plugin', 'wppack-monitoring' ) }</span>
				) }
			</CardHeader>
			<CardBody>
				<div className="wpp-monitoring-metrics-grid">
					{ provider.metrics.map( ( metric ) => (
						<MetricCard
							key={ metric.id }
							metric={ metric }
							result={ resultMap[ metric.id ] }
							onSelect={ onSelectMetric }
						/>
					) ) }
				</div>
			</CardBody>
		</Card>
	);
}
