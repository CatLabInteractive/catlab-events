<?php

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\SeriesPhotoResourceDefinition;
use App\Models\Series;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * The hero slideshow photos of a series.
 */
class SeriesPhotoController extends ResourceController
{
    const RESOURCE_DEFINITION = SeriesPhotoResourceDefinition::class;
    const RESOURCE_ID = 'id';
    const PARENT_RESOURCE_ID = 'series';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController;

    public static function setRoutes(RouteCollection $routes)
    {
        $routes->childResource(
            static::RESOURCE_DEFINITION,
            'series/{' . self::PARENT_RESOURCE_ID . '}/photos',
            'photos',
            'SeriesPhotoController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('series');
    }

    public function getRelationship(Request $request): Relation
    {
        /** @var Series $series */
        $series = $this->getParent($request);
        return $series->photos();
    }

    public function getParent(Request $request): Model
    {
        return Series::findOrFail($request->route(self::PARENT_RESOURCE_ID));
    }

    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }
}
