<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2021 Thijs Van der Schaeghe
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
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use CatLab\CharonFrontend\Models\Table\ResourceAction;
use CatLab\Laravel\Table\Table;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

/**
 * Class TicketCategoryController
 * @package App\Http\Controllers\Admin
 */
class EventDateController extends Controller implements FrontCrudControllerContract
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
     * @param $path
     * @param $controller
     * @param string $modelId
     */
    public static function routes($path, $controller, $modelId = 'id')
    {
        self::traitRoutes($path, $controller, $modelId);
        \Route::get($path . '/{' . $modelId . '}/fetchScore', $controller . '@fetchScore');
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
            (new ResourceAction('Admin\EventDateController@fetchScore', 'Update score'))
                ->setRouteParameters($this->getShowRouteParameters($request))
                ->setQueryParameters($this->getShowQueryParameters($request))
                ->setCondition(function ($model) use ($request) {
                    return !empty($model->getSource()->quizwitz_report_id);
                })
        );

        return $table;
    }

    /**
     * @return \App\Http\Api\V1\Controllers\Events\EventDateController
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\Events\EventDateController();
    }

    public static function getRouteIdParameterName()
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName()
    {
        return \App\Http\Api\V1\Controllers\Events\EventDateController::RESOURCE_ID;
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
     * @param Request $request
     * @param $eventId
     * @param $eventDateId
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function fetchScore(Request $request, $eventId, $eventDateId)
    {
        /** @var EventDate $eventDate */
        $eventDate = EventDate::findOrFail($eventDateId);

        /** @var Event $event */
        $event = $eventDate->event;

        $reportId = $eventDate->quizwitz_report_id;

        $url = config('services.quizwitz.url') . 'report';
        $url .= '/' . $reportId;
        $url .= '?output=json&client=' . urlencode(config('services.quizwitz.apiClient'));

        $client = new Client();
        $response = $client->get($url);

        $data = json_decode($response->getBody(), true);
        $players = $data['players'];

        // Sort ze players
        usort($players, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // dump all existing scores.
        $eventDate->dumpScores();

        $position = 1;
        foreach ($players as $player) {
            $name = $player['name'];
            $score = $player['score'];

            $group = $event->getAttendeeFromName($eventDate, $name);
            $eventDate->setScore($position, $name, $score, $group);

            $position ++;
        }

        return redirect()->back()
            ->with('message', 'Score was updated!')
            ->withInput();
    }
}
