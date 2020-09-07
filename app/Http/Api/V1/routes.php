<?php

use CatLab\Charon\Collections\RouteCollection;

$routes = new RouteCollection();

$routes
    ->get('/api/v1/description.{format?}', 'Api\V1\Controllers\DescriptionController@description')
    ->summary('Get swagger API description')
    ->tag('swagger')
;

/**
 * Everything related to the API.
 */
$routes->group(
    [
        'prefix' => '/api/v1/',
        'suffix' => '.{format?}',
        'namespace' => 'Api\V1\Controllers',
        'middleware' => [
            'web',
            'auth', // temporary
            \CatLab\Charon\Laravel\Middleware\ResourceToOutput::class
        ],
        'security' => [
            [
                'oauth2' => [
                    'full'
                ]
            ]
        ]
    ],
    function(RouteCollection $routes)
    {

        // Format parameter goes for all endpoints.
        $routes->parameters()->path('format')->enum(['json'])->describe('Output format')->default('json');

        \App\Http\Api\V1\Controllers\Events\EventController::setRoutes($routes);
        \App\Http\Api\V1\Controllers\OrganisationController::setRoutes($routes);
        \App\Http\Api\V1\Controllers\VenueController::setRoutes($routes);
        \App\Http\Api\V1\Controllers\CompetitionController::setRoutes($routes);
        \App\Http\Api\V1\Controllers\SeriesController::setRoutes($routes);

        \App\Http\Api\V1\Controllers\Events\TicketCategoryController::setRoutes($routes);

        \App\Http\Api\V1\Controllers\Groups\GroupController::setRoutes($routes);
        \App\Http\Api\V1\Controllers\Groups\GroupMemberController::setRoutes($routes);

        \App\Http\Api\V1\Controllers\PeopleController::setRoutes($routes);
        \App\Http\Api\V1\Controllers\LiveStreamController::setRoutes($routes);

    }
);

return $routes;
