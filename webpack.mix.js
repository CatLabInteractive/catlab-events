let mix = require('laravel-mix');


/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

//

mix
    .sass('resources/assets/sass/app.scss', 'public/css')
    .sass('resources/assets/sass/admin.scss', 'public/css')
    .copy('resources/assets/images', 'public/images', false)
    // The public site: no dependencies, see kv.js.
    .scripts([
        'resources/assets/js/kv.js'
    ], 'public/js/kv.js')
    .scripts([
        'resources/assets/js/jquery.js',
        'resources/assets/js/bootstrap.min.js',
        'resources/assets/js/jquery.counterup.min.js',
        'resources/assets/js/jquery.jCounter.js',
        'resources/assets/js/waypoints.min.js',
        'resources/assets/js/jquery.colorbox.js',
        'resources/assets/js/livestream.js'
    ], 'public/js/livestream.js')
    .js([
        'resources/assets/js/admin.js'
    ], 'public/js/admin.js')
    // TinyMCE 6 (MIT) for the CMS page editor, self-hosted: no CDN, no API
    // key. Only the minified build and what cms-editor.js loads from it.
    .copy('node_modules/tinymce/tinymce.min.js', 'public/js/tinymce')
    .copy('node_modules/tinymce/license.txt', 'public/js/tinymce')
    .copyDirectory('node_modules/tinymce/icons', 'public/js/tinymce/icons')
    .copyDirectory('node_modules/tinymce/models', 'public/js/tinymce/models')
    .copyDirectory('node_modules/tinymce/themes', 'public/js/tinymce/themes')
    .copyDirectory('node_modules/tinymce/skins', 'public/js/tinymce/skins')
    .copyDirectory('node_modules/tinymce/plugins/link', 'public/js/tinymce/plugins/link')
    .copyDirectory('node_modules/tinymce/plugins/image', 'public/js/tinymce/plugins/image')
    .copyDirectory('node_modules/tinymce/plugins/lists', 'public/js/tinymce/plugins/lists')
    .copyDirectory('node_modules/tinymce/plugins/table', 'public/js/tinymce/plugins/table')
    .copyDirectory('node_modules/tinymce/plugins/media', 'public/js/tinymce/plugins/media')
    .copyDirectory('node_modules/tinymce/plugins/code', 'public/js/tinymce/plugins/code')
    .copyDirectory('node_modules/tinymce/plugins/autoresize', 'public/js/tinymce/plugins/autoresize')
    .version()
;

// `npm run hot`: laravel-mix 6 still writes webpack-dev-server 4 options.
// package.json overrides webpack-dev-server to 5 (security fixes) and
// webpack-cli to 5 (webpack-cli 4 cannot start dev-server 5). Translate the
// two options that dev-server 5 removed. Builds without --hot have no
// devServer block and are not affected. `mix watch --hot --https` does not
// work: dev-server 5 dropped the --https flag.
mix.override((config) => {
    const devServer = config.devServer;
    if (!devServer) {
        return;
    }

    // Listen on every interface, so the browser on the host reaches the
    // server through the port that docker-compose.yml publishes. The page
    // still loads the assets from http://localhost:8080 (public/hot).
    devServer.host = '0.0.0.0';

    if ('https' in devServer) {
        if (devServer.https) {
            devServer.server = 'https';
        }
        delete devServer.https;
    }

    if (devServer.onBeforeSetupMiddleware) {
        const before = devServer.onBeforeSetupMiddleware;
        delete devServer.onBeforeSetupMiddleware;
        devServer.setupMiddlewares = (middlewares, server) => {
            before(server);
            return middlewares;
        };
    }
});
