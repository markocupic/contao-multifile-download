const Encore = require('@symfony/webpack-encore');

Encore
	.setOutputPath('public/')
	.setPublicPath('/bundles/markocupiccontaomultifiledownload')
	.setManifestKeyPrefix('')

	//.addEntry('backend', './assets/backend.js')
	//.addEntry('frontend', './assets/frontend.js')

	.copyFiles({
		from: './assets',
		to: '[path][name].[hash:8].[ext]',
	})

	.disableSingleRuntimeChunk()
	.cleanupOutputBeforeBuild()
	.enableSourceMaps()
	.enableVersioning()

	// enables @babel/preset-env polyfills
	.configureBabelPresetEnv((config) => {
		config.useBuiltIns = 'usage';
		config.corejs = 3;
	})

	.enablePostCssLoader()
;

module.exports = Encore.getWebpackConfig();
