const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const ESLintPlugin = require('eslint-webpack-plugin')
const StyleLintPlugin = require('stylelint-webpack-plugin')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
    colors: true,
    modules: false,
}

const appId = 'gpxpod'
webpackConfig.entry = {
    filesPlugin: { import: path.join(__dirname, 'src', 'filesPlugin.js'), filename: appId + '-filesPlugin.js' },
	vueGpxpod: { import: path.join(__dirname, 'src', 'vueGpxpod.js'), filename: appId + '-vueGpxpod.js' },
	vueGpxComparison: { import: path.join(__dirname, 'src', 'vueGpxComparison.js'), filename: appId + '-vueGpxComparison.js' },
	adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: appId + '-adminSettings.js' },
}

webpackConfig.plugins.push(
	new ESLintPlugin({
		extensions: ['js', 'vue'],
		files: 'src',
		failOnError: !isDev,
	})
)
webpackConfig.plugins.push(
	new StyleLintPlugin({
		files: 'src/**/*.{css,scss,vue}',
		failOnError: !isDev,
	}),
)

webpackConfig.module.rules.push({
	test: /\.svg$/i,
	type: 'asset/source',
})

module.exports = webpackConfig
