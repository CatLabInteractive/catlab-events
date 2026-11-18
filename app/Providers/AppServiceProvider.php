<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2017 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class AppServiceProvider
 * @package App\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // html() fields are cleaned on save. HTMLPurifier caches its
        // definitions inside vendor/ by default, which isn't writeable in
        // production.
        \CatLab\Charon\CharonConfig::instance()->setHtmlPurifierFactory(function () {
            $cachePath = storage_path('framework/cache/htmlpurifier');
            if (!is_dir($cachePath)) {
                @mkdir($cachePath, 0775, true);
            }

            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', $cachePath);
            $config->set('Attr.AllowedFrameTargets', [ '_blank' ]);
            // Anchors such as the series FAQ (<h2 id="faq">, see Series::hasFaq()).
            $config->set('Attr.EnableID', true);
            // Embedded video, music and maps from known players only.
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/|open\.spotify\.com/embed/|w\.soundcloud\.com/player/|www\.google\.com/maps/embed|www\.facebook\.com/plugins/)%');

            return new class($config) extends \HTMLPurifier {
                // An emptied editor (<p><br></p>, or nothing at all) is
                // stored empty, so @if($event->description) stays false.
                public function purify($html, $config = null)
                {
                    $clean = parent::purify($html, $config);
                    $text = trim(str_replace([ '&nbsp;', "\xc2\xa0" ], ' ', strip_tags($clean, '<img><iframe>')));
                    return $text === '' ? '' : $clean;
                }
            };
        });

        if (isset($_SERVER['HTTP_HOST'])) {

            $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? (request()->isSecure() ? 'https' : 'http');
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];

            // set app url
            $rootUrl = $protocol . '://' . $host;

            config(['app.url' => $rootUrl]);

            // set redirect url
            config(['services.catlab.redirect' => config('app.url') . '/login/callback']);

            \Carbon\Carbon::setLocale(mb_substr(config('app.locale'), 0, 2));
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
