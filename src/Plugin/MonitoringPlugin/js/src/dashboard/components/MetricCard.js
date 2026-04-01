import Sparkline from './Sparkline';

export default function MetricCard( { metric, result } ) {
	const { label, description, stat, unit } = metric;
	const error = result?.error;
	const datapoints = result?.datapoints || [];

	if ( error ) {
		return (
			<div className="wpp-monitoring-card wpp-monitoring-card--error">
				<div className="wpp-monitoring-card-label">{ label }</div>
				<div className="wpp-monitoring-card-error">{ error }</div>
			</div>
		);
	}

	const lastValue =
		datapoints.length > 0
			? datapoints[ datapoints.length - 1 ].value
			: null;

	const formatValue = ( val ) => {
		if ( val === null ) {
			return '\u2014';
		}
		if ( unit === 'Percent' ) {
			return `${ val.toFixed( 1 ) }%`;
		}
		if ( unit === 'Bytes' ) {
			if ( val >= 1_073_741_824 ) {
				return `${ ( val / 1_073_741_824 ).toFixed( 1 ) } GB`;
			}
			if ( val >= 1_048_576 ) {
				return `${ ( val / 1_048_576 ).toFixed( 1 ) } MB`;
			}
			if ( val >= 1024 ) {
				return `${ ( val / 1024 ).toFixed( 1 ) } KB`;
			}
			return `${ val.toFixed( 0 ) } B`;
		}
		if ( val >= 1_000_000 ) {
			return `${ ( val / 1_000_000 ).toFixed( 1 ) }M`;
		}
		if ( val >= 1000 ) {
			return `${ ( val / 1000 ).toFixed( 1 ) }K`;
		}
		return val.toFixed( 0 );
	};

	return (
		<div className="wpp-monitoring-card">
			<div className="wpp-monitoring-card-label">{ label }</div>
			<div className="wpp-monitoring-card-value">
				{ formatValue( lastValue ) }
			</div>
			{ datapoints.length > 1 && <Sparkline datapoints={ datapoints } /> }
			<div className="wpp-monitoring-card-meta">
				{ stat } &middot; { unit }
			</div>
			{ description && (
				<div className="wpp-monitoring-card-desc">{ description }</div>
			) }
		</div>
	);
}
