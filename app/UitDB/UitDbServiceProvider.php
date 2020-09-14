<?php

namespace App\UitDB;

use Illuminate\Support\ServiceProvider;

/**
 * Class UitDbServiceProvider
 * @package App\UitDB
 */
class UitDbServiceProvider extends ServiceProvider
{
    /**
     *
     */
    public function register()
    {
        $this->app->bind(
            \App\UitDB\Contracts\UitDBService::class,
            function () {
                return UitDatabankService::fromConfig();
            }
        );
    }
}
