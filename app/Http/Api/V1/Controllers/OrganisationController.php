<?php

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\OrganisationResourceDefinition;
use CatLab\Charon\Collections\RouteCollection;

/**
 * Class EventController
 * @package App\Http\Api\V1\Controllers
 */
class OrganisationController extends ResourceController
{
    const RESOURCE_DEFINITION = OrganisationResourceDefinition::class;
    const RESOURCE_ID = 'organisation';

    use \CatLab\Charon\Laravel\Controllers\CrudController;

    /**
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->resource(
            static::RESOURCE_DEFINITION,
            'organisations',
            'OrganisationController',
            [
                'id' => self::RESOURCE_ID
            ]
        )->tag('organisation');
    }
}