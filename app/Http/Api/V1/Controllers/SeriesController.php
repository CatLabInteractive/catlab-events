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

namespace App\Http\Api\V1\Controllers;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\CompetitionResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\SeriesResourceDefinition;
use App\Models\Organisation;
use CatLab\Charon\Collections\RouteCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class SeriesController
 * @package App\Http\Api\V1\Controllers
 */
class SeriesController extends ResourceController
{
    const RESOURCE_DEFINITION = SeriesResourceDefinition::class;
    const RESOURCE_ID = 'series';
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
            'organisations/{' . self::PARENT_RESOURCE_ID . '}/series',
            'series',
            'SeriesController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('series');
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Organisation $organisation */
        $organisation = $this->getParent($request);
        return $organisation->series();
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
}
