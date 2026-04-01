const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		dashboard: path.resolve( __dirname, 'js/src/dashboard/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'js/build' ),
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve?.alias || {} ),
			'@wordpress/dataviews/build-style': path.resolve(
				__dirname,
				'node_modules/@wordpress/dataviews/build-style'
			),
		},
	},
};
