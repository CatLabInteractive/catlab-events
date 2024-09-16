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

namespace App\Http\Controllers;

use App\Http\Api\V1\ResourceDefinitions\Groups\GroupMemberResourceDefinition;
use App\Models\Group;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Http\Request;

/**
 * Class GroupMemberController
 * @package App\Http\Controllers
 */
class GroupMemberController implements FrontCrudControllerContract
{
    use FrontCrudController;

    /**
     * GroupMemberController constructor.
     */
    public function __construct()
    {
        $this->setLayout('layouts.front');
    }

    /**
     * @return \App\Http\Api\V1\Controllers\Groups\GroupMemberController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\Groups\GroupMemberController();
    }

    public static function getRouteIdParameterName()
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName()
    {
        return \App\Http\Api\V1\Controllers\Groups\GroupMemberController::RESOURCE_ID;
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param $method
     * @return array
     */
    protected function getRouteParameters(Request $request, $method)
    {
        $group = $request->route('group');
        return [
            'group' => $group
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
        $group = $request->route('group');
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:

                return [
                    'group' => Group::findOrFail($group)->id
                ];

                break;
        }
    }

    /**
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getAuthorizeParameters(Request $request, $method)
    {
        $group = $request->route('group');
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:

                return [
                    Group::findOrFail($group)
                ];

                break;
        }
    }

    /**
     * @param $action
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getActionText($action, ResourceDefinition $resourceDefinition)
    {
        switch ($action) {
            case Action::CREATE:
                return 'Groepslid toevoegen';
        }

        return $this->traitGetActionText($action, $resourceDefinition);
    }
}
