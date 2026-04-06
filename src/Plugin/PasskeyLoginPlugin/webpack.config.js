const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		settings: path.resolve( __dirname, 'js/src/settings/index.js' ),
		profile: path.resolve( __dirname, 'js/src/profile/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'js/build' ),
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve?.alias || {} ),
			'@wordpress/admin-ui/build-style': path.resolve(
				__dirname,
				'node_modules/@wordpress/admin-ui/build-style'
			),
		},
	},
};
