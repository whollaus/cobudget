const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
    main: path.join(__dirname, 'src', 'main.js'),
    settings: path.join(__dirname, 'src', 'settings.js')
}

// Load lazy chunks next to the entry bundle that Nextcloud resolved. This keeps
// custom app paths and webroots working, for example /apps, /custom_apps and
// /nextcloud/custom_apps behind a reverse proxy.
webpackConfig.output.publicPath = 'auto'

if (!webpackConfig.module) webpackConfig.module = { rules: [] };
webpackConfig.module.rules.push({
    test: /\.m?js/,
    resolve: {
        fullySpecified: false
    }
});

module.exports = webpackConfig
