const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .postCss('resources/css/app.css', 'public/css', [
        //
    ]);

mix.copyDirectory('resources/public_js_css/admin_dep', 'public/admin_dep');
mix.copyDirectory('resources/public_js_css/captcha', 'public/captcha');
mix.copyDirectory('resources/public_js_css/css', 'public/css');
mix.copyDirectory('resources/public_js_css/excel_upload', 'public/excel_upload');
mix.copyDirectory('resources/public_js_css/plugins', 'public/plugins');
