let mix = require('laravel-mix');
let ImageminPlugin = require( 'imagemin-webpack-plugin' ).default;

require('laravel-mix-criticalcss');


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
    .webpackConfig({
        plugins: [
            new ImageminPlugin( {
//            disable: process.env.NODE_ENV !== 'production', // Disable during development
                pngquant: {
                    quality: '95-100',
                },
                test: /\.(jpe?g|png|gif|svg)$/i,
            } )
        ]
    })
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
    .criticalCss({
        enabled: mix.inProduction(),
        paths: {
            base: 'https://events.catlab.eu/',
            templates: './resources/criticalcss/'
        },
        urls: [
            { url: 's/1/ludieke-triviaquiz?noasynccss=1', template: 'series-view' },
            { url: 'e/30/alors-on-quiz?noasynccss=1', template: 'events-view' },
            { url: 'calendar?noasynccss=1', template: 'calendar' },
            { url: 'competitions?noasynccss=1', template: 'competitions' },
            { url: 'events/register?noasynccss=1', template: 'register' },
        ],
        options: {
            width: 1200,
            height: 1000,
            timeout: 30000
        }
    })
    .version()
;
