const path = require('path')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
    bootstrap: path.join(__dirname, 'src', 'bootstrap.js'),
    main: path.join(__dirname, 'src', 'main.js'),
    settings: path.join(__dirname, 'src', 'settings.js')
}

// Load lazy chunks next to the entry bundle that Nextcloud resolved. This keeps
// custom app paths and webroots working, for example /apps, /custom_apps and
// /nextcloud/custom_apps behind a reverse proxy.
webpackConfig.output.publicPath = 'auto'

if (!webpackConfig.module) webpackConfig.module = { rules: [] };
webpackConfig.module.rules.push({
    test: /api-request-limits\.md$/,
    type: 'asset/source',
});
webpackConfig.module.rules.push({
    test: /\.m?js/,
    resolve: {
        fullySpecified: false
    }
});

// Keep styles out of JavaScript parsing and make the shell CSS available before
// Vue starts. Lazy component CSS is loaded by webpack alongside its JS chunk.
for (const rule of webpackConfig.module.rules) {
    if (Array.isArray(rule.use)) {
        rule.use = rule.use.map(loader => loader === 'style-loader' ? MiniCssExtractPlugin.loader : loader)
    }
}
webpackConfig.plugins.push(new MiniCssExtractPlugin({
    // Vue styles are scoped; global component rules use distinct class names.
    // Their import order across lazy views does not define the app cascade.
    ignoreOrder: true,
    filename: 'cobudget-[name].css',
    chunkFilename: 'cobudget-[name].css?v=[contenthash]',
}))
webpackConfig.optimization.minimizer.push(new CssMinimizerPlugin({
    // Preserve modern relative-color and custom-property calc() expressions.
    minimizerOptions: { preset: ['default', { calc: false }] },
}))

module.exports = webpackConfig
