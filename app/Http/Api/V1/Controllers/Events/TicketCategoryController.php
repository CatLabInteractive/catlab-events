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

namespace App\Http\Api\V1\Controllers\Events;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Api\V1\ResourceDefinitions\Events\TicketCategoryResourceDefinition;
use App\Models\Event;
use App\UitDB\Exceptions\InvalidEventException;
use CatLab\Charon\Collections\RouteCollection;
use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Models\TranslatableMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Class TicketCategoryController
 * @package App\Http\Api\V1\Controllers
 */
class TicketCategoryController extends ResourceController
{
    const RESOURCE_DEFINITION = TicketCategoryResourceDefinition::class;
    const RESOURCE_ID = 'ticketCategory';
    const PARENT_RESOURCE_ID = 'event';

    use \CatLab\Charon\Laravel\Controllers\ChildCrudController {
        beforeSaveEntity as traitBeforeSaveEntity;
    }

    /**
     * @param RouteCollection $routes
     */
    public static function setRoutes(RouteCollection $routes)
    {
        $routes->childResource(
            static::RESOURCE_DEFINITION,
            'events/{' . self::PARENT_RESOURCE_ID . '}/ticketCategories',
            'ticketCategories',
            'Events\TicketCategoryController',
            [
                'id' => self::RESOURCE_ID,
                'parentId' => self::PARENT_RESOURCE_ID
            ]
        )->tag('events');
    }

    /**
     * @param Request $request
     * @return Relation
     */
    public function getRelationship(Request $request): Relation
    {
        /** @var Event $event */
        $event = $this->getParent($request);
        return $event->ticketCategories();
    }

    /**
     * @param Request $request
     * @return Model
     */
    public function getParent(Request $request): Model
    {
        $parentId = $request->route(self::PARENT_RESOURCE_ID);
        return Event::findOrFail($parentId);
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
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param bool $isNew
     * @return Model
     * @throws ResourceValidationException
     * @throws \App\UitDB\Exceptions\InvalidCardException
     * @throws \App\UitDB\Exceptions\InvalidEventException
     * @throws \App\UitDB\Exceptions\UitPASException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function beforeSaveEntity(Request $request, \Illuminate\Database\Eloquent\Model $entity, $isNew = false)
    {
        $this->traitBeforeSaveEntity($request, $entity, $isNew);

        // Verify uitpas event
        /** @var Event $event */
        $event = $entity->event;

        if (!$event->isFinished() && $event->uitdb_event_id) {
            // Check if we have a ticket price
            try {
                $uitPasService = \UitDb::getUitPasService();
                if ($uitPasService) {
                    if (!$uitPasService->hasApplicableUitPasPrice($entity)) {
                        $messages = new MessageCollection();
                        $messages->add(new TranslatableMessage('No applicable UiTPas tariff found. Please add UiTPas tariff first.', []));
                        throw ResourceValidationException::make($messages);
                    }
                }
            } catch (InvalidEventException $e) {
                $messages = new MessageCollection();
                $messages->add(new TranslatableMessage('No applicable UiTPas event found. Please make sure UiTPas organisation and pricing is set correctly.', []));
                throw ResourceValidationException::make($messages);
            }
        }

        return $entity;
    }
}
