<?php

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