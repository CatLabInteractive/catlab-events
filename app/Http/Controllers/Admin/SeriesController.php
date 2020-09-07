<?php

namespace App\Http\Controllers\Admin;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Controllers\Controller;
use CatLab\Charon\Enums\Action;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Http\Request;

/**
 * Class CompetitionController
 * @package App\Http\Controllers\Admin
 */
class SeriesController extends Controller
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
     * @return ResourceController
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\SeriesController();
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
}