export default function Sparkline( { datapoints, height = 32 } ) {
	if ( ! datapoints || datapoints.length < 2 ) {
		return null;
	}

	const viewWidth = 200;
	const values = datapoints.map( ( d ) => d.value );
	const min = Math.min( ...values );
	const max = Math.max( ...values );
	const range = max - min || 1;

	const points = values
		.map( ( v, i ) => {
			const x = ( i / ( values.length - 1 ) ) * viewWidth;
			const y =
				height - ( ( v - min ) / range ) * ( height - 4 ) - 2;
			return `${ x },${ y }`;
		} )
		.join( ' ' );

	return (
		<svg
			className="wpp-monitoring-sparkline"
			height={ height }
			viewBox={ `0 0 ${ viewWidth } ${ height }` }
			preserveAspectRatio="none"
		>
			<polyline
				fill="none"
				stroke="currentColor"
				strokeWidth="1.5"
				points={ points }
			/>
		</svg>
	);
}
