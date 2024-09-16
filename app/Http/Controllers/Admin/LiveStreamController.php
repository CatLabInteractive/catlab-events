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

use App\Exceptions\LivestreamNotFoundException;
use App\Http\Api\V1\Controllers\Base\ResourceController;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use CatLab\CharonFrontend\Models\Table\ResourceAction;
use CatLab\Laravel\Table\Table;
use Illuminate\Http\Request;

/**
 * Class LiveStreamController
 * @package App\Http\Controllers\Admin
 */
class LiveStreamController extends Controller implements FrontCrudControllerContract
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
        return new \App\Http\Api\V1\Controllers\LiveStreamController();
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return \App\Http\Api\V1\Controllers\LiveStreamController::RESOURCE_ID;
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
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     */
    public function getTableForResourceCollection (
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        ContextContract $context
    ): Table {
        $table = $this->traitGetTableForResourceCollection($request, $collection, $resourceDefinition, $context);

        $table->modelAction(
            (new ResourceAction('Admin\LiveStreamController@generateUrlsForm', 'Generated named routes'))
                ->setRouteParameters($this->getShowRouteParameters($request))
                ->setQueryParameters($this->getShowQueryParameters($request))

        );

        return $table;
    }

    /**
     * @param Request $request
     * @param $streamId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function generateUrlsForm(Request $request, $streamId)
    {
        /** @var LiveStream $stream */
        $stream = LiveStream::findOrFail($streamId);
        return view('admin.generateUrls', [
            'livestream' => $stream,
            'organisation' => $stream->organisation,
            'embed' => false,
            'action' => action('Admin\LiveStreamController@processGenerateUrls', [ $stream->id ])
        ]);
    }

    /**
     * @param Request $request
     * @param $streamId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function processGenerateUrls(Request $request, $streamId)
    {
        /** @var LiveStream $stream */
        $stream = LiveStream::findOrFail($streamId);
        $body = $request->post('reservation');

        $players = [];
        $isProcessingPlayers = false;

        $rows = explode(PHP_EOL, $body);
        foreach ($rows as $row) {
            if (!$isProcessingPlayers) {
                if (trim($row) === 'Players:') {
                    $isProcessingPlayers = true;
                }
                continue;
            }

            $parts = explode(':', $row);

            $token = trim($parts[0]);
            $name = trim($parts[1]);

            $players[] = [
                'name' => $name,
                'token' => $token,
                'url' => $stream->getLivestreamUrl([
                    'n' => $name
                ])
            ];
        }

        return view('admin.generatedUrls', [
            'livestream' => $stream,
            'organisation' => $stream->organisation,
            'embed' => false,
            'players' => $players
        ]);
    }
}
