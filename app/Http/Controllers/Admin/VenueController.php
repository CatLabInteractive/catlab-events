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

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\VenueController::RESOURCE_ID;
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
