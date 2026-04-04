/**
 * Metric templates built dynamically from bridge metadata.
 *
 * Bridge packages define their templates in PHP via MetricProviderInterface::getTemplates().
 * The MonitoringPlugin passes them to JS via wp_localize_script.
 */
const bridgeData = window.wppMonitoring?.bridges ?? {};

export const METRIC_TEMPLATES = Object.entries( bridgeData )
	.filter( ( [ name ] ) => ! name.startsWith( 'mock-' ) )
	.flatMap( ( [ bridge, meta ] ) =>
		( meta.templates || [] ).map( ( t ) => ( { ...t, bridge } ) )
	);
