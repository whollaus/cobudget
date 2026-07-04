const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
    main: path.join(__dirname, 'src', 'main.js'),
    settings: path.join(__dirname, 'src', 'settings.js')
}

if (!webpackConfig.module) webpackConfig.module = { rules: [] };
webpackConfig.module.rules.push({
    test: /\.m?js/,
    resolve: {
        fullySpecified: false
    }
});

module.exports = webpackConfig
