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
use App\Cms\PageWriter;
use App\Http\Api\V1\ResourceDefinitions\OrganisationResourceDefinition;
use App\Models\Organisation;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Class EventController
 * @package App\Http\Api\V1\Controllers
 */
class OrganisationController extends ResourceController
{
    const RESOURCE_DEFINITION = OrganisationResourceDefinition::class;
    const RESOURCE_ID = 'organisation';

    use \CatLab\Charon\Laravel\Controllers\CrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
    }

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

    /**
     * The home page must be one of the organisation's own pages.
     * @param Request $request
     * @param Model $entity
     * @param bool $isNew
     * @return Model
     * @throws ResourceValidationException
     */
    protected function beforeSaveEntity(Request $request, Model $entity, $isNew = false)
    {
        $entity = $this->traitBeforeSaveEntity($request, $entity, $isNew);

        /** @var Organisation $entity */
        if ($entity->isDirty('home_page_id')) {
            if (!$entity->home_page_id) {
                $entity->home_page_id = null;
            } elseif (!app(PageWriter::class)->isPageOfOrganisation($entity, $entity->home_page_id)) {
                $messages = new MessageCollection();
                $messages->add(new Message('De startpagina moet een pagina van deze organisatie zijn.', null, 'home_page_id'));
                throw ResourceValidationException::make($messages);
            }
        }

        return $entity;
    }
}
