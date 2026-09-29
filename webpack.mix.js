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
    .copy('resources/assets/fonts', 'public/fonts', false)
    .copy('resources/assets/images', 'public/images', false)
    .copy('resources/assets/js/html5shiv.js', 'public/js')
    .copy('resources/assets/js/respond.min.js', 'public/js')
    .scripts([
        'resources/assets/js/jquery.js',
        'resources/assets/js/bootstrap.min.js',
        'resources/assets/js/jquery.counterup.min.js',
        'resources/assets/js/jquery.jCounter.js',
        'resources/assets/js/waypoints.min.js',
        'resources/assets/js/jquery.colorbox.js',
        //'resources/assets/js/smoothscroll.js',
        'resources/assets/js/gmap3.js',
        'resources/assets/js/jquery.easypiechart.js',
        'resources/assets/js/custom.js',
        'resources/assets/js/lazyload.js',
        'resources/assets/js/livestream.js'
    ], 'public/js/app.js')
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
    .version()
;
