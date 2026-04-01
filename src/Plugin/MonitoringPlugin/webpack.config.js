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
};
