<?php

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\VenueResourceDefinition;
use App\Models\Venue;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Http\Request;

/**
 * Class VenueController
 * @package App\Http\Api\V1\Controllers
 */
class VenueController extends ResourceController
{
    const RESOURCE_DEFINITION = VenueResourceDefinition::class;
    const RESOURCE_ID = 'venue';

    use \CatLab\Charon\Laravel\Controllers\CrudController;

    /**
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->resource(
            static::RESOURCE_DEFINITION,
            'venues',
            'VenueController',
            [
                'id' => self::RESOURCE_ID
            ]
        )->tag('venues');
    }

    /**
     * Called before saveEntity
     */
    protected function beforeSaveEntity(Request $request, Venue $venue)
    {
        $user = \Auth::getUser();
        $venue->user()->associate($user);

        return $venue;
    }
}
