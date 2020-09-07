<?php

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\PersonResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\SeriesResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\VenueResourceDefinition;
use App\Models\Organisation;
use App\Models\Person;
use App\Models\Venue;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class VenueController
 * @package App\Http\Api\V1\Controllers
 */
class PeopleController extends ResourceController
{
    const RESOURCE_DEFINITION = PersonResourceDefinition::class;
    const RESOURCE_ID = 'person';
    const PARENT_RESOURCE_ID = 'organisation';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController;

    /**
     * @var Organisation
     */
    protected $organisation;

    /**
     * @param RouteCollection $routes
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->childResource(
            static::RESOURCE_DEFINITION,
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/people',
            'people',
            'PeopleController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('people');
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);
        return $organisation->people();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $parentId = $request->route(self::PARENT_RESOURCE_ID);
        return Organisation::findOrFail($parentId);
    }


    /**
     * @return string
     */
    public function getRelationshipKey(): string
    {
        return self::PARENT_RESOURCE_ID;
    }

    /**
     * Called before saveEntity
     */
    protected function beforeSaveEntity(Request $request, Person $person)
    {
        $relationship = $this->getRelationship($request);
        if ($relationship instanceof HasMany) {
            $this->getInverseRelationship($person)->associate($this->getParent($request));
        }

        $user = \Auth::getUser();
        $person->user()->associate($user);

        return $person;
    }
}
