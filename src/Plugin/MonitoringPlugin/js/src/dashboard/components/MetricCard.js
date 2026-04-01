import Sparkline from './Sparkline';

export default function MetricCard( { result } ) {
	const { label, unit, datapoints, error } = result;

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
		if ( val >= 1_000_000 ) {
			return `${ ( val / 1_000_000 ).toFixed( 1 ) }M`;
		}
		if ( val >= 1_000 ) {
			return `${ ( val / 1_000 ).toFixed( 1 ) }K`;
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
		</div>
	);
}
