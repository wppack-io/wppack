import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const CHART_WIDTH = 700;
const CHART_HEIGHT = 250;
const PADDING = { top: 10, right: 20, bottom: 30, left: 60 };

export default function DetailChart( { datapoints, unit } ) {
	const [ hover, setHover ] = useState( null );
	const svgRef = useRef( null );

	if ( ! datapoints || datapoints.length < 2 ) {
		return <div className="wpp-monitoring-detail-chart-empty">{ __( 'Not enough data points.', 'wppack-monitoring' ) }</div>;
	}

	const plotW = CHART_WIDTH - PADDING.left - PADDING.right;
	const plotH = CHART_HEIGHT - PADDING.top - PADDING.bottom;

	const values = datapoints.map( ( dp ) => dp.value );
	const min = Math.min( ...values );
	const max = Math.max( ...values );
	const range = max - min || 1;

	const toX = ( i ) => PADDING.left + ( i / ( datapoints.length - 1 ) ) * plotW;
	const toY = ( v ) => PADDING.top + plotH - ( ( v - min ) / range ) * plotH;

	const points = datapoints.map( ( dp, i ) => `${ toX( i ) },${ toY( dp.value ) }` ).join( ' ' );

	// Y-axis labels (5 ticks)
	const yTicks = Array.from( { length: 5 }, ( _, i ) => {
		const v = min + ( range * i ) / 4;
		return { y: toY( v ), label: formatAxisValue( v, unit ) };
	} );

	// X-axis labels (5 ticks)
	const xTicks = Array.from( { length: 5 }, ( _, i ) => {
		const idx = Math.round( ( i / 4 ) * ( datapoints.length - 1 ) );
		const dp = datapoints[ idx ];
		return { x: toX( idx ), label: formatTime( dp.timestamp ) };
	} );

	const handleMouseMove = ( e ) => {
		const svg = svgRef.current;
		if ( ! svg ) return;
		const rect = svg.getBoundingClientRect();
		const mouseX = ( ( e.clientX - rect.left ) / rect.width ) * CHART_WIDTH;
		const relX = mouseX - PADDING.left;
		if ( relX < 0 || relX > plotW ) {
			setHover( null );
			return;
		}
		const idx = Math.round( ( relX / plotW ) * ( datapoints.length - 1 ) );
		const dp = datapoints[ idx ];
		if ( dp ) {
			setHover( { x: toX( idx ), y: toY( dp.value ), dp, idx } );
		}
	};

	return (
		<div className="wpp-monitoring-detail-chart">
			<svg
				ref={ svgRef }
				viewBox={ `0 0 ${ CHART_WIDTH } ${ CHART_HEIGHT }` }
				preserveAspectRatio="xMidYMid meet"
				onMouseMove={ handleMouseMove }
				onMouseLeave={ () => setHover( null ) }
			>
				{ /* Grid lines */ }
				{ yTicks.map( ( t, i ) => (
					<line key={ `g${ i }` } x1={ PADDING.left } y1={ t.y } x2={ CHART_WIDTH - PADDING.right } y2={ t.y } stroke="#e0e0e0" strokeWidth="0.5" />
				) ) }

				{ /* Y-axis labels */ }
				{ yTicks.map( ( t, i ) => (
					<text key={ `y${ i }` } x={ PADDING.left - 8 } y={ t.y + 4 } textAnchor="end" fontSize="10" fill="#757575">{ t.label }</text>
				) ) }

				{ /* X-axis labels */ }
				{ xTicks.map( ( t, i ) => (
					<text key={ `x${ i }` } x={ t.x } y={ CHART_HEIGHT - 6 } textAnchor="middle" fontSize="10" fill="#757575">{ t.label }</text>
				) ) }

				{ /* Data line */ }
				<polyline
					points={ points }
					fill="none"
					stroke="#3858e9"
					strokeWidth="2"
				/>

				{ /* Hover indicator */ }
				{ hover && (
					<>
						<circle cx={ hover.x } cy={ hover.y } r="4" fill="#3858e9" />
						<line x1={ hover.x } y1={ PADDING.top } x2={ hover.x } y2={ PADDING.top + plotH } stroke="#3858e9" strokeWidth="0.5" strokeDasharray="4" />
					</>
				) }
			</svg>
			{ hover && (
				<div className="wpp-monitoring-detail-tooltip" style={ { left: `${ ( hover.x / CHART_WIDTH ) * 100 }%` } }>
					<div className="wpp-monitoring-detail-tooltip-time">{ formatDateTime( hover.dp.timestamp ) }</div>
					<div className="wpp-monitoring-detail-tooltip-value">{ formatDisplayValue( hover.dp.value, unit ) }</div>
				</div>
			) }
		</div>
	);
}

function formatAxisValue( value, unit ) {
	if ( unit === 'Percent' ) return value.toFixed( 1 ) + '%';
	if ( unit === 'Bytes' ) {
		if ( value >= 1_073_741_824 ) return ( value / 1_073_741_824 ).toFixed( 1 ) + 'G';
		if ( value >= 1_048_576 ) return ( value / 1_048_576 ).toFixed( 1 ) + 'M';
		if ( value >= 1024 ) return ( value / 1024 ).toFixed( 0 ) + 'K';
		return value.toFixed( 0 );
	}
	if ( unit === 'Milliseconds' ) return value.toFixed( 0 ) + 'ms';
	if ( unit === 'Seconds' ) return value.toFixed( 3 ) + 's';
	if ( value >= 1_000_000 ) return ( value / 1_000_000 ).toFixed( 1 ) + 'M';
	if ( value >= 1000 ) return ( value / 1000 ).toFixed( 1 ) + 'K';
	return value.toFixed( 1 );
}

function formatDisplayValue( value, unit ) {
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

function formatTime( ts ) {
	const d = new Date( ts );
	return d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
}

function formatDateTime( ts ) {
	const d = new Date( ts );
	return d.toLocaleString( [], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' } );
}
