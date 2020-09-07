<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use CatLab\Charon\Enums\Action;
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
}