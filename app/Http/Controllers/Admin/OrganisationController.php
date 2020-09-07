<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use CatLab\CharonFrontend\Controllers\FrontCrudController;

/**
 * Class OrganisationController
 * @package App\Http\Controllers
 */
class OrganisationController extends Controller
{
    use FrontCrudController;

    /**
     * OrganisationController constructor.
     */
    public function __construct()
    {
        $this->setLayout('layouts.admin');
    }

    function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\OrganisationController();
    }
}
