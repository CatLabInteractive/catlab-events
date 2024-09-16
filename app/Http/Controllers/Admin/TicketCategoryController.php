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

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDate;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Http\Request;

/**
 * Class TicketCategoryController
 * @package App\Http\Controllers\Admin
 */
class TicketCategoryController extends Controller implements FrontCrudControllerContract
{
    use FrontCrudController;

    /**
     * EventController constructor.
     */
    public function __construct()
    {
        $this->setLayout('layouts.admin');
    }

    /**
     * @return \App\Http\Api\V1\Controllers\Events\TicketCategoryController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\Events\TicketCategoryController();
    }

    public static function getRouteIdParameterName()
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName()
    {
        return \App\Http\Api\V1\Controllers\Events\TicketCategoryController::RESOURCE_ID;
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param $method
     * @return array
     */
    protected function getRouteParameters(Request $request, $method)
    {
        return [
            'event' => $request->route('event')
        ];
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    'event' => $request->route('event')
                ];

                break;
        }
    }


    /**
     * Get any parameters that might be required by the controller.
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getAuthorizeParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    Event::find($request->route('event'))
                ];

                break;
        }
    }

    /**
     * @param RelationshipField $field
     * @return array
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    protected function resolveValues(RelationshipField $field)
    {
        $childResource = $field->getChildResource();
        $entity = $childResource->getEntityClassName();

        $entities = [];
        switch ($entity) {
            case EventDate::class:
                /** @var Event $parent */
                $parent = Event::find(\Request::instance()->route('event'));
                if ($parent) {
                    $entities = $parent->eventDates()->get();
                }
                break;

            default:
                $entities = call_user_func([ $entity, 'all' ]);
                break;
        }

        $out = [];
        foreach ($entities as $entity) {
            $out[$entity->id] = $entity->name;
        }
        return $out;
    }
}
