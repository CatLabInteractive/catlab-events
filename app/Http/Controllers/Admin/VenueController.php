<?php

namespace App\Http\Controllers\Admin;

use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Controllers\Controller;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Http\Request;

/**
 * Class VenueController
 * @package App\Http\Controllers\Admin
 */
class VenueController extends Controller
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
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\VenueController();
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        return null;
    }
}