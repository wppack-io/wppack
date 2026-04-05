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

	// Time-based X axis
	const timestamps = datapoints.map( ( dp ) => new Date( dp.timestamp ).getTime() );
	const tMin = timestamps[ 0 ];
	const tMax = timestamps[ timestamps.length - 1 ];
	const tRange = tMax - tMin || 1;

	const toX = ( t ) => PADDING.left + ( ( t - tMin ) / tRange ) * plotW;
	const toY = ( v ) => PADDING.top + plotH - ( ( v - min ) / range ) * plotH;

	const points = datapoints.map( ( dp, i ) =>
		`${ toX( timestamps[ i ] ) },${ toY( dp.value ) }`
	).join( ' ' );

	// Y-axis labels (5 ticks)
	const yTicks = Array.from( { length: 5 }, ( _, i ) => {
		const v = min + ( range * i ) / 4;
		return { y: toY( v ), label: formatAxisValue( v, unit ) };
	} );

	// X-axis: aligned to nice time boundaries
	const xTicks = computeTimeTicks( tMin, tMax, plotW );

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
		// Find nearest datapoint by timestamp
		const mouseT = tMin + ( relX / plotW ) * tRange;
		let nearest = 0;
		let nearestDist = Infinity;
		for ( let i = 0; i < timestamps.length; i++ ) {
			const dist = Math.abs( timestamps[ i ] - mouseT );
			if ( dist < nearestDist ) {
				nearestDist = dist;
				nearest = i;
			}
		}
		const dp = datapoints[ nearest ];
		if ( dp ) {
			setHover( { x: toX( timestamps[ nearest ] ), y: toY( dp.value ), dp, idx: nearest } );
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

				{ /* X-axis tick lines + labels */ }
				{ xTicks.map( ( t, i ) => (
					<g key={ `x${ i }` }>
						<line x1={ toX( t.time ) } y1={ PADDING.top } x2={ toX( t.time ) } y2={ PADDING.top + plotH } stroke="#f0f0f0" strokeWidth="0.5" />
						<text x={ toX( t.time ) } y={ CHART_HEIGHT - 6 } textAnchor="middle" fontSize="10" fill="#757575">{ t.label }</text>
					</g>
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

/**
 * Compute nice time-aligned X-axis ticks.
 */
function computeTimeTicks( tMin, tMax ) {
	const rangeMs = tMax - tMin;
	const rangeHours = rangeMs / ( 3600 * 1000 );

	let intervalMs;
	let formatFn;

	if ( rangeHours <= 2 ) {
		// ≤2h: every 15 min
		intervalMs = 15 * 60 * 1000;
		formatFn = ( d ) => d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
	} else if ( rangeHours <= 6 ) {
		// ≤6h: every 1 hour
		intervalMs = 60 * 60 * 1000;
		formatFn = ( d ) => d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
	} else if ( rangeHours <= 24 ) {
		// ≤1d: every 3 hours
		intervalMs = 3 * 60 * 60 * 1000;
		formatFn = ( d ) => d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
	} else if ( rangeHours <= 72 ) {
		// ≤3d: every 6 hours
		intervalMs = 6 * 60 * 60 * 1000;
		formatFn = ( d ) => {
			const h = d.getHours();
			return h === 0
				? d.toLocaleDateString( [], { month: 'short', day: 'numeric' } )
				: d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
		};
	} else {
		// >3d: every 1 day at 0:00
		intervalMs = 24 * 60 * 60 * 1000;
		formatFn = ( d ) => d.toLocaleDateString( [], { month: 'short', day: 'numeric' } );
	}

	// Snap to the first aligned boundary after tMin (local timezone)
	const intervalHours = intervalMs / ( 3600 * 1000 );
	const d0 = new Date( tMin );
	d0.setMinutes( 0, 0, 0 );
	// Round up to next aligned hour (0, 6, 12, 18 for 6h; 0, 3, 6... for 3h; etc.)
	const curHour = d0.getHours();
	const nextHour = Math.ceil( curHour / intervalHours ) * intervalHours;
	if ( nextHour > curHour || d0.getTime() < tMin ) {
		d0.setHours( nextHour );
	}
	if ( d0.getTime() <= tMin ) {
		d0.setTime( d0.getTime() + intervalMs );
	}
	const first = d0.getTime();

	const ticks = [];
	for ( let t = first; t <= tMax; t += intervalMs ) {
		ticks.push( { time: t, label: formatFn( new Date( t ) ) } );
	}

	return ticks;
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

function formatDateTime( ts ) {
	const d = new Date( ts );
	return d.toLocaleString( [], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' } );
}
