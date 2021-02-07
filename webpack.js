const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
    colors: true,
    modules: false,
}

webpackConfig.entry = {
    filetypes: { import: path.join(__dirname, 'src', 'filetypes.js'), filename: 'filetypes.js' },
    gpxpod: { import: path.join(__dirname, 'src', 'gpxpod.js'), filename: 'gpxpod.js' },
    gpxvcomp: { import: path.join(__dirname, 'src', 'gpxvcomp.js'), filename: 'gpxvcomp.js' },
}

module.exports = webpackConfig
