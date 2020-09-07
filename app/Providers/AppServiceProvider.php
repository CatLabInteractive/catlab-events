<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (isset($_SERVER['HTTP_HOST'])) {

            // set app url
            $rootUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];

            config(['app.url' => $rootUrl]);

            // set redirect url
            config(['services.catlab.redirect' => config('app.url') . '/login/callback']);

            setlocale(LC_TIME, config('app.locale'));
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
