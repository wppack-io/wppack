import { __ } from '@wordpress/i18n';
import Sparkline from './Sparkline';

export default function MetricCard( { metric, result } ) {
	const { stat, unit } = metric;
	const label = __( metric.label, 'wppack-monitoring' );
	const description = metric.description ? __( metric.description, 'wppack-monitoring' ) : '';
	const error = result?.error;
	const datapoints = result?.datapoints || [];

	if ( error ) {
		return (
			<div className="wpp-monitoring-card wpp-monitoring-card--error">
				<div className="wpp-monitoring-card-label">{ label }</div>
				<div className="wpp-monitoring-card-value wpp-monitoring-card-value--muted">
					&mdash;
				</div>
				<div className="wpp-monitoring-card-error">{ error }</div>
			</div>
		);
	}

	const displayValue = ( () => {
		if ( datapoints.length === 0 ) {
			return null;
		}
		// Sum metrics: show total across the period
		if ( stat === 'Sum' ) {
			return datapoints.reduce( ( acc, dp ) => acc + dp.value, 0 );
		}
		// Average/Maximum/Minimum: show latest value
		return datapoints[ datapoints.length - 1 ].value;
	} )();

	const formatValue = ( val ) => {
		if ( val === null ) {
			return '\u2014';
		}

		const u = ( text ) => <span className="wpp-monitoring-card-unit">{ text }</span>;

		if ( unit === 'Percent' ) {
			return <>{ val.toFixed( 1 ) }{ u( '%' ) }</>;
		}
		if ( unit === 'Bytes' ) {
			if ( val >= 1_099_511_627_776 ) {
				return <>{ ( val / 1_099_511_627_776 ).toFixed( 1 ) } { u( 'TB' ) }</>;
			}
			if ( val >= 1_073_741_824 ) {
				return <>{ ( val / 1_073_741_824 ).toFixed( 1 ) } { u( 'GB' ) }</>;
			}
			if ( val >= 1_048_576 ) {
				return <>{ ( val / 1_048_576 ).toFixed( 1 ) } { u( 'MB' ) }</>;
			}
			if ( val >= 1024 ) {
				return <>{ ( val / 1024 ).toFixed( 1 ) } { u( 'KB' ) }</>;
			}
			return <>{ val.toFixed( 0 ) } { u( 'B' ) }</>;
		}
		if ( unit === 'Milliseconds' ) {
			return <>{ val.toFixed( 0 ) } { u( 'ms' ) }</>;
		}
		if ( val >= 1_000_000 ) {
			return <>{ ( val / 1_000_000 ).toFixed( 1 ) } { u( 'M' ) }</>;
		}
		if ( val >= 1000 ) {
			return <>{ ( val / 1000 ).toFixed( 1 ) } { u( 'K' ) }</>;
		}
		return val.toFixed( 0 );
	};

	return (
		<div className="wpp-monitoring-card">
			<div className="wpp-monitoring-card-label">{ label }</div>
			<div className="wpp-monitoring-card-value">
				{ formatValue( displayValue ) }
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
