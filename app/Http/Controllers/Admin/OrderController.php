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

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TicketCategory;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use FrontCrudController {
        index as frontIndex;
    }

    public function __construct()
    {
        $this->setLayout('layouts.admin');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $request->query->set('sort', '!id');
        return $this->frontIndex($request);
    }

    /**
     * @return ResourceController
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\OrdersController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\OrdersController::RESOURCE_ID;
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
                    'organisation' => \Request::user()->getActiveOrganisation()->id
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
                    \Request::user()->getActiveOrganisation()
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
            case TicketCategory::class:
                /** @var Order $parent */
                $parent = Order::find(\Request::instance()->route('order'));
                if ($parent && $parent->event) {
                    $entities = $parent->event->ticketCategories()->get();
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
