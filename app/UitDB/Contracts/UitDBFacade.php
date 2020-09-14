<?php

namespace App\UitDB\Contracts;

use Illuminate\Support\Facades\Facade;

/**
 * Interface UitDbService
 * @package App\UitDB\Contracts
 */
class UitDBFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \App\UitDB\Contracts\UitDBService::class;
    }
}
